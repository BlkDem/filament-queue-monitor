<?php

return [

    'enabled' => env('QUEUE_MONITOR_ENABLED', true),

    'driver' => env('QUEUE_MONITOR_DRIVER'),

    'refresh_interval' => env('QUEUE_MONITOR_REFRESH_INTERVAL', 10),

    // The most jobs a list page loads per queue and in total. Lists are
    // searched and sorted in memory, so this caps what a backlog costs.
    'list_limit' => env('QUEUE_MONITOR_LIST_LIMIT', 1000),

    'metrics' => [
        'enabled' => env('QUEUE_MONITOR_METRICS_ENABLED', true),
        'table' => env('QUEUE_MONITOR_METRICS_TABLE', 'queue_monitor_metrics'),
        'table_completed_jobs' => env('QUEUE_MONITOR_COMPLETED_JOBS_TABLE', 'queue_monitor_completed_jobs'),
        'retention_days' => env('QUEUE_MONITOR_METRICS_RETENTION_DAYS', 30),
        'refresh_interval' => env('QUEUE_MONITOR_METRICS_REFRESH_INTERVAL', 30),

        // Off by default. A payload holds the serialized command, so whatever
        // a job carries (tokens, personal data) would be copied into this
        // table and shown in the panel. It is also around 5 KB per run:
        // roughly 40 GB a month at ten thousand jobs an hour. The per-run rows
        // are kept either way; this only adds the payload to them.
        'store_payload' => env('QUEUE_MONITOR_STORE_PAYLOAD', false),

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

    // Who may retry or delete failed jobs. Same values as authorize: a bool or
    // a Gate ability. Unset, anyone allowed to see the pages may also act.
    'authorize_actions' => env('QUEUE_MONITOR_AUTHORIZE_ACTIONS'),

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
