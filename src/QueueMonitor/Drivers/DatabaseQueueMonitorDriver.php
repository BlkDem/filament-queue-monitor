<?php

namespace BlkDem\FilamentQueueMonitor\QueueMonitor\Drivers;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Contracts\QueueMonitorDriver;
use BlkDem\FilamentQueueMonitor\QueueMonitor\DTO\FailedJobInfo;
use BlkDem\FilamentQueueMonitor\QueueMonitor\DTO\JobInfo;
use BlkDem\FilamentQueueMonitor\QueueMonitor\DTO\QueueInfo;
use BlkDem\FilamentQueueMonitor\QueueMonitor\DTO\QueueStats;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Support\HandlesFailedJobs;

class DatabaseQueueMonitorDriver implements QueueMonitorDriver
{
    use HandlesFailedJobs;

    protected ConnectionInterface $database;

    protected string $table;

    protected string $connection;

    protected string $queueConnection;

    public function __construct()
    {
        $queueConnection = config('queue.default', 'database');

        if (! is_string($queueConnection) || $queueConnection === 'sync') {
            $queueConnection = 'database';
        }

        if (
            config('filament-queue-monitor.driver') === 'database'
            && config("queue.connections.{$queueConnection}.driver") === 'redis'
        ) {
            $queueConnection = 'database';
        }

        $this->table = config("queue.connections.{$queueConnection}.table") ?: 'jobs';
        $this->queueConnection = $queueConnection;
        $this->connection = config("queue.connections.{$queueConnection}.connection")
            ?? config('database.default');
        $this->database = app('db')->connection($this->connection);
    }

    public function getQueues(): array
    {
        $queueNames = $this->database->table($this->table)
            ->distinct()
            ->orderBy('queue', 'asc')
            ->pluck('queue');

        $queues = [];

        foreach ($queueNames as $queue) {
            $queues[] = $this->info((string) $queue);
        }

        if (empty($queues)) {
            $queues[] = $this->info(config("queue.connections.{$this->queueConnection}.queue") ?: 'default');
        }

        return $queues;
    }

    public function stats(string $queue): QueueStats
    {
        $pending = (int) $this->database->table($this->table)
            ->where('queue', $queue)
            ->whereNull('reserved_at')
            ->where('available_at', '<=', now()->timestamp)
            ->count();

        $delayed = (int) $this->database->table($this->table)
            ->where('queue', $queue)
            ->whereNull('reserved_at')
            ->where('available_at', '>', now()->timestamp)
            ->count();

        $reserved = (int) $this->database->table($this->table)
            ->where('queue', $queue)
            ->whereNotNull('reserved_at')
            ->count();

        $failed = $this->countFailedJobsForQueue($queue);

        return new QueueStats(
            pending: $pending,
            processing: $reserved,
            delayed: $delayed,
            completed: 0,
            failed: $failed,
            total: $pending + $delayed + $reserved,
        );
    }

    public function info(string $queue): QueueInfo
    {
        $stats = $this->stats($queue);

        $lastJob = $this->database->table($this->table)
            ->where('queue', $queue)
            ->orderBy('id', 'desc')
            ->limit(1)
            ->first();

        $lastActivityAt = null;

        if ($lastJob) {
            $lastActivity = $lastJob->created_at ?? $lastJob->available_at;

            if ($lastActivity !== null) {
                $lastActivityAt = Carbon::parse($lastActivity);
            }
        }

        if ($stats->failed > 0) {
            $lastActivityAt = $lastActivityAt ?? Carbon::now();
        }

        return new QueueInfo(
            name: $queue,
            pending: $stats->pending,
            processing: $stats->processing,
            delayed: $stats->delayed,
            completed: $stats->completed,
            failed: $stats->failed,
            total: $stats->total,
            lastActivityAt: $lastActivityAt,
        );
    }

    public function pendingJobs(string $queue): iterable
    {
        $rows = $this->database->table($this->table)
            ->where('queue', $queue)
            ->whereNull('reserved_at')
            ->where('available_at', '<=', now()->timestamp)
            ->orderBy('id', 'asc')
            ->get();

        return $this->rowsToJobInfo($rows);
    }

    public function processingJobs(string $queue): iterable
    {
        $rows = $this->database->table($this->table)
            ->where('queue', $queue)
            ->whereNotNull('reserved_at')
            ->orderBy('reserved_at', 'desc')
            ->get();

        return $this->rowsToJobInfo($rows);
    }

    public function delayedJobs(string $queue): iterable
    {
        $rows = $this->database->table($this->table)
            ->where('queue', $queue)
            ->whereNull('reserved_at')
            ->where('available_at', '>', now()->timestamp)
            ->orderBy('available_at', 'asc')
            ->get();

        return $this->rowsToJobInfo($rows);
    }

    public function failedJobs(): iterable
    {
        $failer = $this->getFailer();
        $jobs = [];

        foreach (($failer->all() ?? []) as $record) {
            $jobs[] = $this->parseFailedRecord($record);
        }

        return $jobs;
    }

    public function stuckJobsCount(int $thresholdHours): int
    {
        $threshold = now()->subHours($thresholdHours)->timestamp;

        return $this->database->table($this->table)
            ->whereNotNull('reserved_at')
            ->where('reserved_at', '<', $threshold)
            ->count();
    }

    public function findFailedJob(string $id): ?FailedJobInfo
    {
        $record = $this->getFailer()->find($id);

        if ($record === null) {
            return null;
        }

        return $this->parseFailedRecord($record);
    }

    public function retryFailedJob(string $id): void
    {
        $failer = $this->getFailer();
        $job = $failer->find($id);

        if ($job === null) {
            return;
        }

        $payload = $this->resetAttempts($job->payload);
        $payload = $this->refreshRetryUntil($payload);

        if ($payload === '') {
            return;
        }

        app('queue')->connection($this->failedStringValue($job, 'connection'))->pushRaw(
            $payload,
            $this->failedStringValue($job, 'queue')
        );

        $failer->forget($id);
    }

    public function deleteFailedJob(string $id): void
    {
        $this->getFailer()->forget($id);
    }

    protected function rowsToJobInfo(iterable $rows): array
    {
        $jobs = [];

        foreach ($rows as $row) {
            $data = $this->safeJsonDecode($row->payload ?? '');

            $jobs[] = new JobInfo(
                id: $row->id ?? null,
                uuid: $data['uuid'] ?? null,
                queue: $row->queue ?? '',
                job: $data['job'] ?? $data['displayName'] ?? '',
                attempts: $row->attempts ?? 0,
                createdAt: isset($row->created_at) ? Carbon::parse($row->created_at) : null,
                availableAt: isset($row->available_at) ? Carbon::createFromTimestamp($row->available_at) : null,
                reservedAt: isset($row->reserved_at) && $row->reserved_at ? Carbon::parse($row->reserved_at) : null,
                payload: $row->payload ?? '',
            );
        }

        return $jobs;
    }

    protected function parseFailedRecord(object $record): FailedJobInfo
    {
        $data = $this->safeJsonDecode($record->payload ?? '');

        $id = $this->failedJobValue($record, 'id');
        $uuid = $this->failedJobValue($record, 'uuid') ?? ($data['uuid'] ?? null);

        return new FailedJobInfo(
            id: is_scalar($id) ? (string) $id : null,
            uuid: is_scalar($uuid) ? (string) $uuid : null,
            connection: $this->failedStringValue($record, 'connection'),
            queue: $this->failedStringValue($record, 'queue'),
            payload: $this->failedStringValue($record, 'payload'),
            exception: $this->failedStringValue($record, 'exception'),
            failedAt: isset($record->failed_at) ? Carbon::parse($record->failed_at) : null,
        );
    }

    protected function safeJsonDecode(string $payload): array
    {
        if (blank($payload)) {
            return [];
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
