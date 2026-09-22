<?php

return [

    'enabled' => true,

    'driver' => env(
        'QUEUE_MONITOR_DRIVER',
        env('QUEUE_CONNECTION', 'database')
    ),

    'refresh_interval' => 10,

    'metrics' => [
        'enabled' => env('QUEUE_MONITOR_METRICS_ENABLED', true),
        'retention_days' => env('QUEUE_MONITOR_METRICS_RETENTION_DAYS', 30),
    ],

    'navigation' => [
        'enabled' => env('QUEUE_MONITOR_NAV_ENABLED', true),
        'group' => env('QUEUE_MONITOR_NAV_GROUP', 'Tools'),
        'sort' => env('QUEUE_MONITOR_NAV_SORT', 100),
    ],

    'authorize' => function ($user) {
        return true;
    },

    'failed_jobs' => [
        'table' => env('QUEUE_MONITOR_FAILED_JOBS_TABLE', 'failed_jobs'),
        'database' => env('QUEUE_MONITOR_FAILED_JOBS_DATABASE', null),
    ],

    'redis' => [
        'connection' => env('QUEUE_MONITOR_REDIS_CONNECTION', null),
    ],

];
