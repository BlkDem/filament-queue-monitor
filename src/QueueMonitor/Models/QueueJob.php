<?php

namespace Kilo\FilamentQueueMonitor\QueueMonitor\Models;

use Illuminate\Database\Eloquent\Model;

class QueueJob extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    public function getTable()
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

        return config("queue.connections.{$queueConnection}.table") ?: 'jobs';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getKey()
    {
        return $this->getAttribute($this->getKeyName());
    }
}
