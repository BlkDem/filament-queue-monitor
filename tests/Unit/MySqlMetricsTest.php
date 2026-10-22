<?php

use BlkDem\FilamentQueueMonitor\QueueMonitor\Statistics\MetricsStorage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
 * The rest of the suite runs on SQLite, which reads the old row throughout an
 * upsert and has no index key length limit. MySQL differs on both, so these
 * need a real server:
 * QUEUE_MONITOR_TEST_MYSQL_HOST=127.0.0.1 vendor/bin/pest --filter=MySql
 */
beforeEach(function () {
    if (! env('QUEUE_MONITOR_TEST_MYSQL_HOST')) {
        $this->markTestSkipped('Set QUEUE_MONITOR_TEST_MYSQL_HOST to run the MySQL checks.');
    }

    config()->set('database.connections.mysql_it', [
        'driver' => 'mysql',
        'host' => env('QUEUE_MONITOR_TEST_MYSQL_HOST'),
        'port' => (int) env('QUEUE_MONITOR_TEST_MYSQL_PORT', 3306),
        'database' => env('QUEUE_MONITOR_TEST_MYSQL_DATABASE', 'queue_monitor_test'),
        'username' => env('QUEUE_MONITOR_TEST_MYSQL_USERNAME', 'root'),
        'password' => env('QUEUE_MONITOR_TEST_MYSQL_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
    ]);
    config()->set('database.default', 'mysql_it');
    DB::purge('mysql_it');

    // The package's own migrations, so the MySQL key length limit is checked
    // along with the arithmetic.
    Artisan::call('migrate:fresh', [
        '--database' => 'mysql_it',
        '--path' => realpath(__DIR__.'/../../src/Database/Migrations'),
        '--realpath' => true,
        '--force' => true,
    ]);
});

it('runs the migrations on MySql', function () {
    expect(DB::getSchemaBuilder()->hasColumn('queue_monitor_metrics', 'job'))->toBeTrue()
        ->and(DB::getSchemaBuilder()->hasTable('queue_monitor_completed_jobs'))->toBeTrue();
});

it('averages runtimes on MySql', function (bool $alias) {
    config()->set('database.connections.mysql_it.use_upsert_alias', $alias);
    DB::purge('mysql_it');

    $storage = new MetricsStorage;

    foreach ([10.0, 20.0, 30.0, null, 40.0] as $runtime) {
        $storage->record('redis', 'default', '2026-10-22 10:00:00', processed: 1, avgRuntime: $runtime, maxRuntime: $runtime, job: 'App\Jobs\Avg');
    }

    $storage->record('redis', 'default', '2026-10-22 10:00:00', failed: 1, avgRuntime: 50.0, maxRuntime: 50.0, job: 'App\Jobs\Avg');

    $row = DB::table('queue_monitor_metrics')->first();

    // Five known runtimes plus one run whose start was missed. The unknown run
    // still counts towards the weight, as it does on every other database.
    expect((int) $row->processed)->toBe(5)
        ->and((int) $row->failed)->toBe(1)
        ->and((float) $row->max_runtime)->toBe(50.0)
        ->and(round((float) $row->avg_runtime, 4))->toBe(round(sqliteReferenceAverage([10.0, 20.0, 30.0, null, 40.0, 50.0]), 4));
})->with(['values()' => false, 'row alias' => true]);

/**
 * The same weighting upsertUpdates() applies, computed in PHP the way SQLite
 * evaluates it, so both databases are held to one answer.
 */
function sqliteReferenceAverage(array $runtimes): float
{
    $avg = null;
    $count = 0;

    foreach ($runtimes as $runtime) {
        if ($runtime !== null) {
            $avg = $avg === null ? $runtime : ($avg * $count + $runtime) / ($count + 1);
        }

        $count++;
    }

    return (float) $avg;
}
