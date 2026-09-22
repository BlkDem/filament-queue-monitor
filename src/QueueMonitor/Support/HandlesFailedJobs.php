<?php

namespace Kilo\FilamentQueueMonitor\QueueMonitor\Support;

trait HandlesFailedJobs
{
    protected function getFailer()
    {
        return app('queue.failer');
    }

    protected function resetAttempts(string $payload): string
    {
        $payload = json_decode($payload, true);

        if (isset($payload['attempts'])) {
            $payload['attempts'] = 0;
        }

        return json_encode($payload);
    }

    protected function refreshRetryUntil(string $payload): string
    {
        $payload = json_decode($payload, true);

        if (! isset($payload['data']['command'])) {
            return json_encode($payload);
        }

        if (str_starts_with($payload['data']['command'], 'O:')) {
            $instance = @unserialize($payload['data']['command']);
        } else {
            $instance = null;

            if (app()->bound(\Illuminate\Contracts\Encryption\Encrypter::class)) {
                try {
                    $encrypted = app()->make(\Illuminate\Contracts\Encryption\Encrypter::class);
                    $instance = @unserialize($encrypted->decrypt($payload['data']['command']));
                } catch (\Throwable) {
                    $instance = null;
                }
            }
        }

        if (isset($instance) && is_object($instance) && ! $instance instanceof \__PHP_Incomplete_Class && method_exists($instance, 'retryUntil')) {
            $retryUntil = $instance->retryUntil();

            $payload['retryUntil'] = $retryUntil instanceof \DateTimeInterface
                ? $retryUntil->getTimestamp()
                : $retryUntil;
        }

        return json_encode($payload);
    }

    protected function countFailedJobsForQueue(string $queue): int
    {
        try {
            $failer = $this->getFailer();

            if (method_exists($failer, 'count')) {
                return $failer->count(null, $queue);
            }

            $all = $failer->all() ?? [];

            return count(array_filter($all, fn ($job) => ($job->queue ?? '') === $queue));
        } catch (\Throwable) {
            return 0;
        }
    }
}
