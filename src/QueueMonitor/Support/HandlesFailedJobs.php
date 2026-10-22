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

    /**
     * Failed job records, newest first. The database providers expose their
     * query, so a limit is applied in SQL; any other provider only offers
     * all(), which is read and then cut down.
     *
     * @return list<object|array>
     */
    protected function failedRecords(?int $limit = null): array
    {
        $failer = $this->getFailer();
        $limit = $limit !== null && $limit > 0 ? $limit : null;

        if ($limit !== null && is_callable([$failer, 'getTable'])) {
            $query = $failer->getTable();

            if ($query instanceof \Illuminate\Database\Query\Builder) {
                return $query->orderBy('id', 'desc')->limit($limit)->get()->all();
            }
        }

        $records = array_values((array) ($failer->all() ?? []));

        return $limit !== null ? array_slice($records, 0, $limit) : $records;
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

        if (! is_array($decoded) || ! is_array($decoded['data'] ?? null) || ! is_string($decoded['data']['command'] ?? null)) {
            return $payload;
        }

        $command = $decoded['data']['command'];

        if (! str_starts_with($command, 'O:')) {
            $command = null;

            if (app()->bound(\Illuminate\Contracts\Encryption\Encrypter::class)) {
                try {
                    $command = app()->make(\Illuminate\Contracts\Encryption\Encrypter::class)
                        ->decrypt($decoded['data']['command']);
                } catch (\Throwable) {
                    $command = null;
                }
            }
        }

        $instance = is_string($command)
            ? $this->unserializeCommand($command, $decoded['data']['commandName'] ?? null)
            : null;

        if ($instance !== null && method_exists($instance, 'retryUntil')) {
            try {
                $retryUntil = $instance->retryUntil();
                $decoded['retryUntil'] = $retryUntil instanceof \DateTimeInterface
                    ? $retryUntil->getTimestamp()
                    : $retryUntil;
            } catch (\Throwable) {
                // Keep the deadline the payload already carries.
            }
        }

        $encoded = json_encode($decoded);

        return is_string($encoded) ? $encoded : $payload;
    }

    /**
     * The command is only unserialized to ask it for retryUntil(), so only the
     * command's own class may be built. Anything nested, models included,
     * stays an incomplete placeholder instead of running its wakeup or
     * destructor from a page request; a command that cannot be restored that
     * way simply keeps the deadline already in its payload.
     */
    protected function unserializeCommand(string $command, mixed $commandName): ?object
    {
        if (! is_string($commandName) || $commandName === '' || ! class_exists($commandName)) {
            return null;
        }

        try {
            $instance = @unserialize($command, ['allowed_classes' => [$commandName]]);
        } catch (\Throwable) {
            return null;
        }

        return $instance instanceof $commandName ? $instance : null;
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
        return \BlkDem\FilamentQueueMonitor\Support\MonitoredConnection::name();
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
