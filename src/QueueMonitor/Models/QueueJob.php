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
        $table = config('queue.connections.database.table');

        if (! $table) {
            return 'jobs';
        }

        return $table;
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
