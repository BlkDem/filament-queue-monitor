<?php

return [

    'enabled' => env('QUEUE_MONITOR_ENABLED', true),

    'driver' => env('QUEUE_MONITOR_DRIVER'),

    'refresh_interval' => env('QUEUE_MONITOR_REFRESH_INTERVAL', 10),

    'metrics' => [
        'enabled' => env('QUEUE_MONITOR_METRICS_ENABLED', true),
        'table' => env('QUEUE_MONITOR_METRICS_TABLE', 'queue_monitor_metrics'),
        'table_completed_jobs' => env('QUEUE_MONITOR_COMPLETED_JOBS_TABLE', 'queue_monitor_completed_jobs'),
        'retention_days' => env('QUEUE_MONITOR_METRICS_RETENTION_DAYS', 30),
        'refresh_interval' => env('QUEUE_MONITOR_METRICS_REFRESH_INTERVAL', 30),

        // Runs queue-monitor:prune daily. The completed jobs table keeps full
        // job payloads, so without this it only grows. Turn this off if you
        // schedule the command yourself, or you will run it twice.
        'auto_prune' => env('QUEUE_MONITOR_AUTO_PRUNE', true),
    ],

    'navigation' => [
        'enabled' => env('QUEUE_MONITOR_NAV_ENABLED', true),
        'group' => env('QUEUE_MONITOR_NAV_GROUP'),
        'sort' => env('QUEUE_MONITOR_NAV_SORT', 0),
    ],

    'authorize' => env('QUEUE_MONITOR_AUTHORIZE', false),

    'failed_jobs' => [
        'table' => env('QUEUE_MONITOR_FAILED_JOBS_TABLE', 'failed_jobs'),
        'database' => env('QUEUE_MONITOR_FAILED_JOBS_DATABASE', null),
    ],

    'stuck_jobs' => [
        'threshold_hours' => env('QUEUE_MONITOR_STUCK_JOBS_THRESHOLD_HOURS', 12),
    ],

    'redis' => [
        'connection' => env('QUEUE_MONITOR_REDIS_CONNECTION', null),
        'queues' => env('QUEUE_MONITOR_REDIS_QUEUES', []),
    ],

];
