<?php

use BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ViewCompletedJobRun;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Statistics\MetricsStorage;
use BlkDem\FilamentQueueMonitor\Support\Access;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('denies access when the package enabled flag is a false string', function (string $enabled) {
    config()->set('filament-queue-monitor.enabled', $enabled);
    config()->set('filament-queue-monitor.authorize', true);

    expect(Access::canAccess())->toBeFalse()
        ->and(ViewCompletedJobRun::canAccess())->toBeFalse();
})->with(['false', 'FALSE', 'off', 'no', '0']);

it('reauthorizes sensitive pages on Livewire hydration', function () {
    config()->set('filament-queue-monitor.authorize', false);

    (new ViewCompletedJobRun)->hydrateCanAuthorizeAccess();
})->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);

it('does not inspect job payloads when the package is disabled by a string flag', function () {
    config()->set('filament-queue-monitor.enabled', 'false');
    $job = Mockery::mock(\Illuminate\Queue\Jobs\Job::class);
    $job->shouldNotReceive('getQueue');
    $job->shouldNotReceive('getRawBody');

    $listener = new \BlkDem\FilamentQueueMonitor\QueueMonitor\Listeners\RecordQueueMetrics(new MetricsStorage);
    $listener->handleProcessed(new \Illuminate\Queue\Events\JobProcessed('database', $job));

    expect(DB::table('queue_monitor_completed_jobs')->count())->toBe(0);
});

it('keeps metrics when retention is zero', function () {
    $storage = new MetricsStorage;
    $storage->record('database', 'default', now()->subDays(400)->toDateTimeString(), processed: 1);

    expect($storage->prune(0))->toBe(0)
        ->and(DB::table($storage->getTable())->count())->toBe(1);
});

it('allows separate job aggregates with a long configured metrics table name', function (bool $legacy) {
    $table = 'custom_queue_monitor_metrics_with_a_long_table_name';
    config()->set('filament-queue-monitor.metrics.table', $table);

    $create = require __DIR__.'/../../src/Database/Migrations/2024_01_01_000000_create_queue_monitor_metrics_table.php';
    $addJob = require __DIR__.'/../../src/Database/Migrations/2026_09_23_000001_add_job_to_queue_monitor_metrics_table.php';
    $create->up();

    if ($legacy) {
        Schema::table($table, function ($blueprint) use ($table) {
            $blueprint->dropUnique(substr($table, 0, 60 - strlen('connection_queue_period_unique') - 1).'_connection_queue_period_unique');
            $blueprint->unique(['connection', 'queue', 'period'], substr($table, 0, 30).'_connection_queue_period_unique');
        });
    }

    $addJob->up();
    $storage = new MetricsStorage;
    $period = now()->startOfMinute()->toDateTimeString();
    $storage->record('database', 'default', $period, processed: 1, job: 'FirstJob');
    $storage->record('database', 'default', $period, processed: 1, job: 'SecondJob');

    expect(DB::table($table)->count())->toBe(2);

    Schema::drop($table);
})->with([false, true]);
