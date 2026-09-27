<?php

use Illuminate\Support\Facades\Schema;

it('reads the refresh interval and master switch from the environment', function () {
    $previous = [
        'interval' => getenv('QUEUE_MONITOR_REFRESH_INTERVAL'),
        'enabled' => getenv('QUEUE_MONITOR_ENABLED'),
    ];

    putenv('QUEUE_MONITOR_REFRESH_INTERVAL=25');
    putenv('QUEUE_MONITOR_ENABLED=false');
    $_ENV['QUEUE_MONITOR_REFRESH_INTERVAL'] = '25';
    $_ENV['QUEUE_MONITOR_ENABLED'] = 'false';
    $_SERVER['QUEUE_MONITOR_REFRESH_INTERVAL'] = '25';
    $_SERVER['QUEUE_MONITOR_ENABLED'] = 'false';

    try {
        $config = require __DIR__ . '/../../src/resources/config/filament-queue-monitor.php';

        // Both keys used to be literals, so the documented variables did nothing.
        expect((int) $config['refresh_interval'])->toBe(25)
            ->and((bool) $config['enabled'])->toBeFalse();
    } finally {
        foreach (['QUEUE_MONITOR_REFRESH_INTERVAL', 'QUEUE_MONITOR_ENABLED'] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }

        if ($previous['interval'] !== false) {
            putenv("QUEUE_MONITOR_REFRESH_INTERVAL={$previous['interval']}");
        }

        if ($previous['enabled'] !== false) {
            putenv("QUEUE_MONITOR_ENABLED={$previous['enabled']}");
        }
    }
});

it('declares the completed jobs table next to the metrics table', function () {
    $config = require __DIR__ . '/../../src/resources/config/filament-queue-monitor.php';

    // The code read this key, but it was absent from the published config, so
    // there was no way to discover or change it.
    expect($config['metrics'])->toHaveKey('table_completed_jobs')
        ->and($config['metrics']['table_completed_jobs'])->toBe('queue_monitor_completed_jobs');
});

it('creates the metrics table under the configured name', function () {
    config([
        'filament-queue-monitor.metrics.table' => 'custom_metrics_table',
        'filament-queue-monitor.metrics.table_completed_jobs' => 'custom_runs_table',
    ]);

    $create = require __DIR__ . '/../../src/Database/Migrations/2024_01_01_000000_create_queue_monitor_metrics_table.php';
    $runs = require __DIR__ . '/../../src/Database/Migrations/2026_09_26_000001_create_queue_monitor_completed_jobs_table.php';

    $create->up();
    $runs->up();

    // The migrations used to hardcode the default names, so overriding the
    // config produced a table-not-found at runtime.
    expect(Schema::hasTable('custom_metrics_table'))->toBeTrue()
        ->and(Schema::hasTable('custom_runs_table'))->toBeTrue();

    $create->down();
    $runs->down();
});

it('adds the payload column to the configured completed jobs table', function () {
    config(['filament-queue-monitor.metrics.table_completed_jobs' => 'custom_runs_table']);

    $create = require __DIR__ . '/../../src/Database/Migrations/2026_09_26_000001_create_queue_monitor_completed_jobs_table.php';
    $payload = require __DIR__ . '/../../src/Database/Migrations/2026_09_26_000002_add_payload_to_queue_monitor_completed_jobs_table.php';

    $create->up();
    $payload->up();

    expect(Schema::hasTable('custom_runs_table'))->toBeTrue()
        ->and(Schema::hasColumn('custom_runs_table', 'payload'))->toBeTrue();

    $payload->down();
    $create->down();
});
