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
use RuntimeException;
use Throwable;

class RedisQueueMonitorDriver implements QueueMonitorDriver
{
    use HandlesFailedJobs;

    protected ?string $connection;

    public function __construct(
        protected ?RedisFactory $redis = null,
        ?string $connection = null,
    ) {
        $root = Redis::getFacadeRoot();

        $this->redis = $redis ?? ($root instanceof RedisFactory ? $root : null);
        $this->connection = $connection ?? config('filament-queue-monitor.redis.connection', 'default') ?: 'default';
    }

    public function getQueues(): array
    {
        $names = [];

        foreach ($this->configuredQueues() as $queue) {
            $names[$queue] = true;
        }

        foreach ($this->scanQueueKeys() as $queue) {
            $names[$queue] = true;
        }

        if ($names === []) {
            return [];
        }

        return array_values(array_map(
            fn (string $queue): QueueInfo => $this->info($queue),
            array_keys($names),
        ));
    }

    public function stats(string $queue): QueueStats
    {
        $redis = $this->redis();
        $queueKey = $this->getQueueKey($queue);
        $pending = max(0, (int) $redis->llen($queueKey));
        $processing = max(0, (int) $redis->zcard($queueKey.':reserved'));
        $delayed = max(0, (int) $redis->zcard($queueKey.':delayed'));

        return new QueueStats(
            pending: $pending,
            processing: $processing,
            delayed: $delayed,
            completed: 0,
            failed: $this->countFailedJobsForQueue($queue),
            total: $pending + $processing + $delayed,
        );
    }

    public function pendingJobs(string $queue): array
    {
        $payloads = $this->redis()->lrange($this->getQueueKey($queue), 0, -1);
        $jobs = [];

        foreach ((array) $payloads as $payload) {
            $jobs[] = $this->parseJob((string) $payload, $queue);
        }

        return $jobs;
    }

    public function processingJobs(string $queue): array
    {
        $entries = $this->redis()->zrange($this->getQueueKey($queue).':reserved', 0, -1, true);
        $jobs = [];

        foreach ($this->scoredEntries($entries) as [$payload, $score]) {
            $job = $this->parseJob($payload, $queue);

            $jobs[] = new JobInfo(
                id: $job->id,
                uuid: $job->uuid,
                queue: $job->queue,
                job: $job->job,
                attempts: $job->attempts,
                createdAt: $job->createdAt,
                availableAt: null,
                reservedAt: $this->timestampToCarbon($score),
                payload: $job->payload,
            );
        }

        return $jobs;
    }

    public function info(string $queue): QueueInfo
    {
        $stats = $this->stats($queue);
        $redis = $this->redis();
        $queueKey = $this->getQueueKey($queue);
        $pendingCreatedAt = null;
        $pendingPayload = $redis->lindex($queueKey, 0);

        if (is_string($pendingPayload) && $pendingPayload !== '') {
            $pending = $this->safeJsonDecode($pendingPayload);
            $pendingCreatedAt = $this->timestampToCarbon($pending['createdAt'] ?? $pending['pushedAt'] ?? null);
        }

        $reserved = $redis->zrange($queueKey.':reserved', 0, 0, true);
        $reservedAt = null;

        foreach ($this->scoredEntries($reserved) as [, $score]) {
            $reservedAt = $this->timestampToCarbon($score);

            break;
        }

        $delayed = $redis->zrange($queueKey.':delayed', 0, 0, true);
        $delayedAt = null;

        foreach ($this->scoredEntries($delayed) as [, $score]) {
            $delayedAt = $this->timestampToCarbon($score);

            break;
        }

        $lastActivityAt = null;

        foreach ([$pendingCreatedAt, $reservedAt, $delayedAt] as $activityAt) {
            if ($activityAt instanceof Carbon && ($lastActivityAt === null || $activityAt->greaterThan($lastActivityAt))) {
                $lastActivityAt = $activityAt;
            }
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

    public function failedJobs(): array
    {
        $jobs = [];
        $failer = $this->getFailer();

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

        $payload = $this->resetAttempts((string) $this->failedJobValue($job, 'payload', ''));
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

    protected function redis(): Connection
    {
        if (! $this->redis instanceof RedisFactory) {
            throw new RuntimeException('Redis queue monitoring requires a Laravel Redis connection.');
        }

        return $this->redis->connection($this->connection);
    }

    protected function getQueueKey(string $queue): string
    {
        return 'queues:'.$queue;
    }

    protected function configuredQueues(): array
    {
        $queues = config('filament-queue-monitor.redis.queues', []);

        if (is_string($queues)) {
            $queues = array_values(array_filter(
                array_map('trim', explode(',', $queues)),
                fn (string $queue): bool => $queue !== '',
            ));
        }

        return array_values(array_unique(array_filter(
            (array) $queues,
            fn (mixed $queue): bool => is_string($queue) && $queue !== '',
        )));
    }

    protected function scanQueueKeys(): array
    {
        $redis = $this->redis();
        $cursor = null;
        $queues = [];

        do {
            $result = $this->scan($redis, $cursor);

            if ($result === false) {
                break;
            }

            if (is_array($result) && array_key_exists(0, $result) && array_key_exists(1, $result)) {
                [$nextCursor, $keys] = $result;
            } elseif (is_array($result)) {
                $nextCursor = 0;
                $keys = $result;
            } else {
                break;
            }

            foreach ((array) $keys as $physicalKey) {
                if (! is_string($physicalKey)) {
                    continue;
                }

                $queue = $this->queueNameFromPhysicalKey($physicalKey);

                if ($queue !== null) {
                    $queues[$queue] = true;
                }
            }

            if ($nextCursor === null) {
                break;
            }

            $cursor = $nextCursor;
        } while ((string) $cursor !== '0');

        return array_keys($queues);
    }

    protected function scan(Connection $redis, mixed &$cursor): mixed
    {
        return $redis->scan($cursor, [
            'match' => $this->redisPrefix().'queues:*',
            'count' => 100,
        ]);
    }

    protected function queueNameFromPhysicalKey(string $physicalKey): ?string
    {
        $prefix = $this->redisPrefix();
        $logicalKey = $prefix !== '' && str_starts_with($physicalKey, $prefix)
            ? substr($physicalKey, strlen($prefix))
            : $physicalKey;

        if (! str_starts_with($logicalKey, 'queues:')) {
            return null;
        }

        $name = substr($logicalKey, strlen('queues:'));

        if ($name === '' || in_array($name, ['delayed', 'reserved', 'notify'], true)) {
            return null;
        }

        foreach (['delayed', 'reserved', 'notify'] as $suffix) {
            $suffix = ':'.$suffix;

            if (str_ends_with($name, $suffix)) {
                $name = substr($name, 0, -strlen($suffix));

                break;
            }
        }

        return $name === '' ? null : $name;
    }

    protected function redisPrefix(): string
    {
        $connections = config('database.redis', []);
        $connection = $connections[$this->connection] ?? [];

        return (string) ($connection['prefix'] ?? $connections['options']['prefix'] ?? '');
    }

    protected function parseJob(string $payload, string $queue): JobInfo
    {
        $data = $this->safeJsonDecode($payload);
        $jobData = is_array($data['data'] ?? null) ? $data['data'] : [];
        $command = is_string($jobData['commandName'] ?? null) ? $jobData['commandName'] : null;
        $displayName = is_string($data['displayName'] ?? null) ? $data['displayName'] : null;
        $jobName = is_string($data['job'] ?? null) ? $data['job'] : null;
        $id = is_scalar($data['id'] ?? null) ? (string) $data['id'] : '';
        $uuidValue = $data['uuid'] ?? null;
        $uuid = is_scalar($uuidValue) ? (string) $uuidValue : null;

        $queueName = is_scalar($data['queue'] ?? null) ? (string) $data['queue'] : $queue;
        $attempts = is_scalar($data['attempts'] ?? null) ? (int) $data['attempts'] : 0;

        return new JobInfo(
            id: $id,
            uuid: $uuid,
            queue: $queueName,
            job: $displayName ?? $jobName ?? $command ?? 'Unknown',
            attempts: $attempts,
            createdAt: $this->timestampToCarbon($data['createdAt'] ?? $data['pushedAt'] ?? null),
            availableAt: $this->timestampToCarbon($data['availableAt'] ?? null),
            payload: $payload,
        );
    }

    protected function parseFailedRecord(object|array $record): FailedJobInfo
    {
        $id = $this->failedJobValue($record, 'id');
        $uuid = $this->failedJobValue($record, 'uuid');

        return new FailedJobInfo(
            id: is_scalar($id) ? (string) $id : null,
            uuid: is_scalar($uuid) ? (string) $uuid : null,
            connection: $this->failedStringValue($record, 'connection'),
            queue: $this->failedStringValue($record, 'queue'),
            payload: $this->failedStringValue($record, 'payload'),
            exception: $this->failedStringValue($record, 'exception'),
            failedAt: $this->timestampToCarbon($this->failedJobValue($record, 'failed_at')),
        );
    }

    protected function scoredEntries(mixed $entries): array
    {
        if (! is_array($entries)) {
            return [];
        }

        if (array_is_list($entries)) {
            $result = [];

            for ($index = 0, $count = count($entries); $index + 1 < $count; $index += 2) {
                $result[] = [(string) $entries[$index], $entries[$index + 1]];
            }

            return $result;
        }

        $result = [];

        foreach ($entries as $payload => $score) {
            $result[] = [(string) $payload, $score];
        }

        return $result;
    }

    protected function safeJsonDecode(string $payload): array
    {
        if ($payload === '') {
            return [];
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    protected function timestampToCarbon(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (is_numeric($value)) {
            try {
                return Carbon::createFromTimestamp((int) $value);
            } catch (Throwable) {
                return null;
            }
        }

        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value);
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }
}
