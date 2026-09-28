<?php

namespace BlkDem\FilamentQueueMonitor\QueueMonitor\Support;

trait HandlesFailedJobs
{
    /**
     * Per request, keyed by connection and queue. The failer counts hit the
     * database, and every widget that lists queues asks for a per queue figure,
     * so without this the same query ran once per widget per queue.
     *
     * @var array<string, int>
     */
    protected array $failedCountCache = [];

    public function forgetCachedFailedCounts(): void
    {
        $this->failedCountCache = [];
    }

    protected function getFailer()
    {
        return app('queue.failer');
    }

    protected function resetAttempts(string $payload): string
    {
        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            return $payload;
        }

        if (isset($decoded['attempts'])) {
            $decoded['attempts'] = 0;
        }

        $encoded = json_encode($decoded);

        return is_string($encoded) ? $encoded : $payload;
    }

    protected function refreshRetryUntil(string $payload): string
    {
        $decoded = json_decode($payload, true);

        if (! is_array($decoded) || ! is_array($decoded['data'] ?? null) || ! isset($decoded['data']['command'])) {
            return $payload;
        }

        if (str_starts_with($decoded['data']['command'], 'O:')) {
            $instance = @unserialize($decoded['data']['command']);
        } else {
            $instance = null;

            if (app()->bound(\Illuminate\Contracts\Encryption\Encrypter::class)) {
                try {
                    $encrypted = app()->make(\Illuminate\Contracts\Encryption\Encrypter::class);
                    $instance = @unserialize($encrypted->decrypt($decoded['data']['command']));
                } catch (\Throwable) {
                    $instance = null;
                }
            }
        }

        if (isset($instance) && is_object($instance) && ! $instance instanceof \__PHP_Incomplete_Class && method_exists($instance, 'retryUntil')) {
            $retryUntil = $instance->retryUntil();
            $decoded['retryUntil'] = $retryUntil instanceof \DateTimeInterface
                ? $retryUntil->getTimestamp()
                : $retryUntil;
        }

        $encoded = json_encode($decoded);

        return is_string($encoded) ? $encoded : $payload;
    }

    public function failedJobsCount(?string $connection = null): int
    {
        $connection ??= $this->monitoredQueueConnection();
        $key = $connection.'|';

        if (array_key_exists($key, $this->failedCountCache)) {
            return $this->failedCountCache[$key];
        }

        try {
            $failer = $this->getFailer();

            if (method_exists($failer, 'count')) {
                return $this->failedCountCache[$key] = (int) $failer->count($connection);
            }

            return $this->failedCountCache[$key] = count(array_filter(
                $failer->all() ?? [],
                fn ($job): bool => $this->failedJobValue($job, 'connection', '') === $connection,
            ));
        } catch (\Throwable) {
            return $this->failedCountCache[$key] = 0;
        }
    }

    protected function countFailedJobsForQueue(string $queue, ?string $connection = null): int
    {
        $connection ??= $this->monitoredQueueConnection();
        $key = $connection.'|'.$queue;

        if (array_key_exists($key, $this->failedCountCache)) {
            return $this->failedCountCache[$key];
        }

        try {
            $failer = $this->getFailer();

            if (method_exists($failer, 'count')) {
                return $this->failedCountCache[$key] = (int) $failer->count($connection, $queue);
            }

            $all = $failer->all() ?? [];

            return $this->failedCountCache[$key] = count(array_filter(
                $all,
                fn ($job): bool => $this->failedJobValue($job, 'connection', '') === $connection
                    && $this->failedJobValue($job, 'queue', '') === $queue,
            ));
        } catch (\Throwable) {
            return $this->failedCountCache[$key] = 0;
        }
    }

    /**
     * The queue connection this driver reports on. Failed jobs are recorded per
     * connection, so an app that has switched connections keeps the previous
     * connection's failures in the same table; scoping keeps them out of the
     * totals and out of the per-queue columns.
     */
    protected function monitoredQueueConnection(): string
    {
        $connection = config('queue.default', 'database');

        return is_string($connection) && $connection !== '' ? $connection : 'database';
    }

    protected function failedStringValue(object|array $record, string $property, string $default = ''): string
    {
        $value = $this->failedJobValue($record, $property);

        return is_scalar($value) ? (string) $value : $default;
    }

    protected function failedJobValue(object|array $job, string $key, mixed $default = null): mixed
    {
        if (is_array($job)) {
            return $job[$key] ?? $default;
        }

        return $job->{$key} ?? $default;
    }
}
