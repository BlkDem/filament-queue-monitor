<?php

return [

    'navigation' => [
        'group' => 'Queue Monitor',
        'label' => 'Queue Monitor',
        'dashboard' => 'Dashboard',
        'queues' => 'Queues',
        'jobs' => 'Jobs',
        'delayed_jobs' => 'Delayed Jobs',
        'completed_jobs' => 'Completed Jobs',
        'failed_jobs' => 'Failed Jobs',
        'queue_details' => 'Queue Details',
        'failed_job' => 'Failed Job',
    ],

    'dashboard' => [
        'title' => 'Queue Monitor',
        'subtitle' => 'Live view of your queues and recent job activity.',
    ],

    'stats' => [
        'queues' => 'Queues',
        'pending' => 'Pending',
        'processing' => 'Processing',
        'delayed' => 'Delayed',
        'failed' => 'Failed',
        'total' => 'Total',
        'stuck_jobs' => 'Stuck Jobs',
        'delayed_jobs' => 'Delayed Jobs',
        'failed_jobs' => 'Failed Jobs',
        'processed_last_hour' => 'Processed (Last Hour)',

        'description' => [
            'total_queues' => 'Total queues: :count',
            'processing_count' => ':count processing',
            'awaiting_processing' => 'Awaiting processing',
            'currently_working' => 'Currently being worked on',
            'scheduled_later' => 'Scheduled for later',
            'failed_count' => 'Failed jobs',
            'stuck_jobs' => 'Stuck > :hours h (reserved_at)',
            'awaiting_delayed' => 'Jobs waiting to be processed',
            'has_failed' => 'Jobs that have failed',
            'processed_last_hour' => 'Jobs completed in the last hour',
        ],
    ],

    'queues' => [
        'title' => 'Queues',
        'search_placeholder' => 'Search queues...',
        'empty' => 'No queues found.',

        'columns' => [
            'queue' => 'Queue',
            'pending' => 'Pending',
            'processing' => 'Processing',
            'delayed' => 'Delayed',
            'failed' => 'Failed',
            'total' => 'Total',
            'last_activity' => 'Last Activity',
        ],
    ],

    'queue_details' => [
        'title' => 'Queue Details: :name',
        'subheading' => 'Statistics for the selected queue',
        'section_statistics' => 'Statistics',
        'section_details' => 'Details',
        'last_activity' => 'Last Activity',
        'view_jobs' => 'View Queued Jobs',
        'not_available' => 'N/A',
    ],

    'jobs' => [
        'title' => 'Jobs',
        'job_class' => 'Job Class',
        'pushed_at' => 'Pushed At',
        'available_at' => 'Available At',
        'delayed_for' => 'Delayed For',
        'search_placeholder' => 'Search jobs...',
        'empty' => 'No jobs found.',
        'unknown' => 'Unknown',
    ],

    'delayed_jobs' => [
        'title' => 'Delayed Jobs',
    ],

    'completed_jobs' => [
        'title' => 'Completed Jobs',
        'subtitle' => 'Jobs that ran successfully, grouped by class over the selected period',
        'job' => 'Job Class',
        'processed' => 'Completed',
        'failed' => 'Failed',
        'avg_time' => 'Avg time',
        'max_time' => 'Max time',
        'last_activity' => 'Last activity',
        'period' => 'Period',
        'search_placeholder' => 'Search job classes...',
        'empty' => 'No completed jobs were recorded for this period.',
    ],

    'failed_jobs' => [
        'title' => 'Failed Jobs',
        'payload' => 'Payload',
        'exception' => 'Exception',
        'failed_at' => 'Failed At',
        'search_placeholder' => 'Search failed jobs...',
        'empty' => 'No failed jobs found.',
    ],

    'failed_job_detail' => [
        'breadcrumb' => 'Queue Monitor / Failed Jobs',
        'back' => 'Back to Failed Jobs',
        'job_information' => 'Job information',
        'job_information_subtitle' => 'Details of the failed job record',
        'queue' => 'Queue',
        'connection' => 'Connection',
        'payload' => 'Payload',
        'payload_subtitle' => 'Original job payload',
        'no_payload' => 'No payload.',
        'error' => 'Error',
        'error_subtitle' => 'Exception thrown by the job',
        'show_error' => 'Show error...',
        'unknown' => 'Unknown',
    ],

    'actions' => [
        'retry' => 'Retry',
        'retry_heading' => 'Retry Failed Job',
        'retry_description' => 'Are you sure you want to retry this failed job?',
        'delete' => 'Delete',
        'delete_heading' => 'Delete Failed Job',
        'delete_description' => 'Are you sure you want to delete this failed job? This action cannot be undone.',
        'retried_title' => 'Job Retried',
        'retried_body' => 'Failed job #:id has been retried.',
        'deleted_title' => 'Job Deleted',
        'deleted_body' => 'Failed job #:id has been deleted.',
    ],

    'filters' => [
        'pushed_at' => 'Pushed At',
        'available_at' => 'Available At',
        'failed_at' => 'Failed At',
        'from_pushed_at' => 'From Pushed At',
        'until_pushed_at' => 'Until Pushed At',
        'from_available_at' => 'From Available At',
        'until_available_at' => 'Until Available At',
        'from_failed_at' => 'From Failed At',
        'until_failed_at' => 'Until Failed At',
    ],

    'common' => [
        'id' => 'ID',
        'uuid' => 'UUID',
        'job' => 'Job',
        'queue' => 'Queue',
        'status' => 'Status',
        'attempts' => 'Attempts',
        'connection' => 'Connection',
        'empty_value' => '—',
    ],

    'status' => [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'unknown' => 'Unknown',
    ],

    'delayed' => [
        'minutes' => ':minutes min|:minutes mins',
        'hours' => ':hours h',
    ],

    'activity' => [
        'heading' => 'Queue Activity',
        'description' => 'Current tasks in each queue — the same counts as the stats above',
        'running_since' => 'Running since',
        'queued' => 'Queued',
        'reserved_tooltip' => 'Reserved by a worker',
        'queued_tooltip' => 'Queued, ready to run',
        'empty_heading' => 'No active tasks',
        'empty_description' => 'No pending or processing jobs right now.',
    ],

    'breakdown' => [
        'heading' => 'Job Breakdown',
        'description' => 'Processed and failed jobs grouped by queue',
        'processed' => 'Processed',
        'failed' => 'Failed',
        'avg_time' => 'Avg time',
        'max_time' => 'Max time',
        'seconds' => ':seconds s',
        'total_processed' => 'Total processed',
        'total_failed' => 'Total failed',
        'empty_heading' => 'No job activity',
        'empty_description' => 'Nothing was processed or failed for the selected period.',
    ],

    'periods' => [
        'label' => 'Period',
        'hour' => 'Last hour',
        'today' => 'Today',
        '24h' => 'Last 24 hours',
        '7d' => 'Last 7 days',
    ],

    'polling' => [
        'label' => 'Polling',
        'default' => 'Default (:seconds s)',
        'default_off' => 'Default (off)',
        '5s' => '5 seconds',
        '10s' => '10 seconds',
        '30s' => '30 seconds',
        '60s' => '60 seconds',
        'off' => 'Off',
    ],

];
