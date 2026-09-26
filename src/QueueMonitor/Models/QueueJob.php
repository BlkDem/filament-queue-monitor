<?php

namespace BlkDem\FilamentQueueMonitor\QueueMonitor\Models;

use Illuminate\Database\Eloquent\Model;

class QueueJob extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    public function getTable(): string
    {
        return $this->resolveQueueTable();
    }

    public function getConnectionName(): ?string
    {
        return $this->resolveQueueConnection();
    }

    public function getKeyName(): string
    {
        return 'id';
    }

    public function getNameAttribute(): string
    {
        if (blank($this->payload)) {
            return 'Unknown';
        }

        try {
            $data = json_decode((string) $this->payload, true, 512, JSON_THROW_ON_ERROR);
            $jobData = is_array($data['data'] ?? null) ? $data['data'] : [];

            $name = $data['displayName']
                ?? $data['job']
                ?? $jobData['commandName']
                ?? null;

            return is_string($name) && $name !== '' ? $name : 'Unknown';
        } catch (\JsonException) {
            return 'Unknown';
        }
    }

    public function getStatusAttribute(): string
    {
        if ($this->reserved_at !== null) {
            return 'processing';
        }

        if ((int) $this->available_at > time()) {
            return 'delayed';
        }

        return 'pending';
    }

    protected function resolveQueueConnection(): string
    {
        $connection = config("queue.connections.{$this->resolveQueueConnectionName()}.connection");

        if (is_string($connection) && array_key_exists($connection, config('database.connections', []))) {
            return $connection;
        }

        return (string) config('database.default');
    }

    protected function resolveQueueConnectionName(): string
    {
        $queueConnection = config('queue.default', 'database');

        if (! is_string($queueConnection) || $queueConnection === 'sync') {
            $queueConnection = 'database';
        }

        // This model mirrors the database `jobs` table, so it only applies when
        // the monitored driver and the queue connection both read that table.
        // A redis queue connection has no `jobs` table and its `connection`
        // value names a redis connection, not a database one.
        if (
            config('filament-queue-monitor.driver') === 'redis'
            || config("queue.connections.{$queueConnection}.driver") === 'redis'
        ) {
            return 'database';
        }

        return $queueConnection;
    }

    protected function resolveQueueTable(): string
    {
        return config("queue.connections.{$this->resolveQueueConnectionName()}.table") ?: 'jobs';
    }
}