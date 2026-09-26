<?php

namespace BlkDem\FilamentQueueMonitor\QueueMonitor\Models;

use Illuminate\Database\Eloquent\Model;

class CompletedJob extends Model
{
    protected $guarded = [];

    protected $casts = [
        'runtime' => 'float',
        'finished_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('filament-queue-monitor.metrics.table_completed_jobs', 'queue_monitor_completed_jobs')
            ?: 'queue_monitor_completed_jobs';
    }
}
