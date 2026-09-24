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
        return config("queue.connections.{$this->resolveQueueConnectionName()}.connection")
            ?? config('database.default');
    }

    protected function resolveQueueConnectionName(): string
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

        return $queueConnection;
    }

    protected function resolveQueueTable(): string
    {
        return config("queue.connections.{$this->resolveQueueConnectionName()}.table") ?: 'jobs';
    }
}