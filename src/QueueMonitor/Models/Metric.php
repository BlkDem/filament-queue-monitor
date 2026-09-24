<?php

namespace BlkDem\FilamentQueueMonitor\QueueMonitor\Models;

use Illuminate\Database\Eloquent\Model;

class Metric extends Model
{
    protected $guarded = [];

    protected $casts = [
        'processed' => 'integer',
        'failed' => 'integer',
        'avg_runtime' => 'float',
        'max_runtime' => 'float',
    ];

    public function getTable(): string
    {
        return config('filament-queue-monitor.metrics.table', 'queue_monitor_metrics')
            ?: 'queue_monitor_metrics';
    }
}