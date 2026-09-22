<?php

namespace Kilo\FilamentQueueMonitor\QueueMonitor\Models;

use Illuminate\Database\Eloquent\Model;

class FailedJob extends Model
{
    public $timestamps = false;

    protected $table;

    protected $guarded = [];

    protected $casts = [
        'failed_at' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = config('filament-queue-monitor.failed_jobs.table', 'failed_jobs');
        $this->connection = config('filament-queue-monitor.failed_jobs.database', config('database.default'));

        parent::__construct($attributes);
    }

    public function getKeyName()
    {
        return 'id';
    }

    public $incrementing = false;
}
