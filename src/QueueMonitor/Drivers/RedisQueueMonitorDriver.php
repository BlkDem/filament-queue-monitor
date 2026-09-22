<?php

namespace Kilo\FilamentQueueMonitor\QueueMonitor\Drivers;

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Kilo\FilamentQueueMonitor\QueueMonitor\Contracts\QueueMonitorDriver;
use Kilo\FilamentQueueMonitor\QueueMonitor\DTO\FailedJobInfo;
use Kilo\FilamentQueueMonitor\QueueMonitor\DTO\JobInfo;
use Kilo\FilamentQueueMonitor\QueueMonitor\DTO\QueueInfo;
use Kilo\FilamentQueueMonitor\QueueMonitor\DTO\QueueStats;
use Kilo\FilamentQueueMonitor\QueueMonitor\Support\HandlesFailedJobs;

class RedisQueueMonitorDriver implements QueueMonitorDriver
{
    use HandlesFailedJobs;

    public function __construct(
        protected ?RedisFactory $redis = null,
        protected ?string $connection = null,
    ) {
        $this->redis = $redis ?? Redis::getFacadeRoot();
        $this->connection = $connection
            ?? config('filament-queue-monitor.redis.connection')
            ?? 'default';
    }

    protected function redis(): Connection
    {
        return $this->redis->connection($this->connection);
    }

    public function getQueues(): array
    {
        $queues = [];
        $redis = $this->redis();

        $keys = $redis->keys('queues:*');

        $seenQueueNames = [];

        foreach ($keys as $key) {
            if (str_ends_with($key, ':delayed') || str_ends_with($key, ':reserved') || str_ends_with($key, ':notify')) {
                continue;
            }

            $queueName = str_replace('queues:', '', $key);

            if (in_array($queueName, $seenQueueNames)) {
                continue;
            }

            $seenQueueNames[] = $queueName;
            $queues[] = $this->info($queueName);
        }

        if (empty($queues)) {
            $defaultQueue = config('queue.connections.redis.queue', 'default');
            $queues[] = $this->info($defaultQueue);
        }

        return $queues;
    }

    public function stats(string $queue): QueueStats
    {
        $redis = $this->redis();
        $queueKey = $this->getQueueKey($queue);

        $pending = $redis->llen($queueKey) ?: 0;
        $delayed = $redis->zcard($queueKey . ':delayed') ?: 0;
        $reserved = $redis->zcard($queueKey . ':reserved') ?: 0;

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

        $redis = $this->redis();
        $queueKey = $this->getQueueKey($queue);

        $lastActivityAt = null;

        $oldestPending = $redis->lindex($queueKey, 0);
        if ($oldestPending) {
            try {
                $payload = json_decode($oldestPending, true, 512, JSON_THROW_ON_ERROR);
                if (isset($payload['createdAt'])) {
                    $lastActivityAt = Carbon::createFromTimestamp($payload['createdAt']);
                }
            } catch (\JsonException) {
            }
        }

        if (! $lastActivityAt && $redis->zcard($queueKey . ':reserved') > 0) {
            $lastActivityAt = Carbon::now();
        }

        if (! $lastActivityAt && $stats->failed > 0) {
            $lastActivityAt = Carbon::now();
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
        $redis = $this->redis();
        $queueKey = $this->getQueueKey($queue);

        $payloads = $redis->lrange($queueKey, 0, -1);

        $jobs = [];

        foreach ($payloads as $payload) {
            $jobs[] = $this->parsePayload($payload, $queue);
        }

        return $jobs;
    }

    public function processingJobs(string $queue): iterable
    {
        $redis = $this->redis();
        $queueKey = $this->getQueueKey($queue);

        $entries = $redis->zrange($queueKey . ':reserved', 0, -1, true);

        $jobs = [];

        foreach ($entries as $payload => $score) {
            $jobs[] = $this->parsePayload($payload, $queue, reservedAt: Carbon::createFromTimestamp($score));
        }

        return $jobs;
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

    protected function getQueueKey(string $queue): string
    {
        $prefix = config('database.redis.' . $this->connection . '.prefix', '');

        if ($prefix === '') {
            return 'queues:' . $queue;
        }

        return $prefix . $queue;
    }

    protected function parsePayload(string $payload, string $queue, ?Carbon $reservedAt = null): JobInfo
    {
        $data = $this->safeJsonDecode($payload);

        return new JobInfo(
            id: $data['id'] ?? null,
            uuid: $data['uuid'] ?? null,
            queue: $data['queue'] ?? $queue,
            job: $data['job'] ?? '',
            attempts: $data['attempts'] ?? 0,
            createdAt: isset($data['createdAt']) ? Carbon::createFromTimestamp($data['createdAt']) : null,
            availableAt: null,
            reservedAt: $reservedAt,
            payload: $payload,
        );
    }

    protected function parseFailedRecord(object $record): FailedJobInfo
    {
        return new FailedJobInfo(
            id: $record->id ?? null,
            uuid: $record->uuid ?? null,
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
