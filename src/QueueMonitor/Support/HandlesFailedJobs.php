<?php

namespace BlkDem\FilamentQueueMonitor\QueueMonitor\Support;

trait HandlesFailedJobs
{
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

    protected function countFailedJobsForQueue(string $queue): int
    {
        try {
            $failer = $this->getFailer();

            if (method_exists($failer, 'count')) {
                return (int) $failer->count(null, $queue);
            }

            $all = $failer->all() ?? [];

            return count(array_filter($all, fn ($job): bool => $this->failedJobValue($job, 'queue', '') === $queue));
        } catch (\Throwable) {
            return 0;
        }
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
