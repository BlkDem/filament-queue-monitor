<?php

namespace Kilo\FilamentQueueMonitor\QueueMonitor\Drivers;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Kilo\FilamentQueueMonitor\QueueMonitor\Contracts\QueueMonitorDriver;
use Kilo\FilamentQueueMonitor\QueueMonitor\DTO\FailedJobInfo;
use Kilo\FilamentQueueMonitor\QueueMonitor\DTO\JobInfo;
use Kilo\FilamentQueueMonitor\QueueMonitor\DTO\QueueInfo;
use Kilo\FilamentQueueMonitor\QueueMonitor\DTO\QueueStats;
use Kilo\FilamentQueueMonitor\QueueMonitor\Support\HandlesFailedJobs;

class DatabaseQueueMonitorDriver implements QueueMonitorDriver
{
    use HandlesFailedJobs;

    protected ConnectionInterface $database;

    protected string $table;

    protected string $connection;

    public function __construct()
    {
        $this->table = config('queue.connections.database.table', 'jobs');
        $this->connection = config('queue.connections.database.connection')
            ?? config('database.default');
        $this->database = app('db')->connection($this->connection);
    }

    public function getQueues(): array
    {
        $rows = $this->database->select(
            "SELECT DISTINCT queue FROM {$this->table} ORDER BY queue ASC"
        );

        $queues = [];

        foreach ($rows as $row) {
            $queues[] = $this->info($row->queue);
        }

        if (empty($queues)) {
            $queues[] = $this->info(config('queue.connections.database.queue', 'default'));
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
            $lastActivityAt = Carbon::parse($lastJob->created_at ?? $lastJob->available_at ?? null);
        }

        if ($stats->failed > 0) {
            $lastActivityAt = $lastActivityAt ?? Carbon::now();
        }

        return new QueueInfo(
            name: $queue,
            pending: $stats->pending,
            processing: $stats->processing,
            delayed: $stats->delayed,
            failed: $stats->failed,
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

    public function failedJobs(): iterable
    {
        $failer = $this->getFailer();
        $jobs = [];

        foreach (($failer->all() ?? []) as $record) {
            $jobs[] = $this->parseFailedRecord($record);
        }

        return $jobs;
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

        app('queue')->connection($job->connection)->pushRaw($payload, $job->queue);

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

        return new FailedJobInfo(
            id: $record->id ?? null,
            uuid: $record->uuid ?? ($data['uuid'] ?? null),
            connection: $record->connection ?? '',
            queue: $record->queue ?? '',
            payload: $record->payload ?? '',
            exception: $record->exception ?? '',
            failedAt: isset($record->failed_at) ? Carbon::parse($record->failed_at) : null,
        );
    }

    protected function safeJsonDecode(string $payload): array
    {
        if (blank($payload)) {
            return [];
        }

        try {
            return json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
    }
}
