<?php

return [

    'enabled' => true,

    'driver' => env('QUEUE_MONITOR_DRIVER'),

    'refresh_interval' => 10,

    'metrics' => [
        'enabled' => env('QUEUE_MONITOR_METRICS_ENABLED', true),
        'table' => env('QUEUE_MONITOR_METRICS_TABLE', 'queue_monitor_metrics'),
        'retention_days' => env('QUEUE_MONITOR_METRICS_RETENTION_DAYS', 30),
        'refresh_interval' => env('QUEUE_MONITOR_METRICS_REFRESH_INTERVAL', 30),
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
