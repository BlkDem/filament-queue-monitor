<?php

use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ListCompletedJobs;
use BlkDem\FilamentQueueMonitor\QueueMonitor\QueueMonitorManager;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Statistics\MetricsStorage;
use BlkDem\FilamentQueueMonitor\Support\Trans;

beforeEach(function () {
    config()->set('filament-queue-monitor.metrics.enabled', true);
    config()->set('queue.default', 'database');
});

function insertMetricRow(string $queue, string $job, int $processed, int $failed = 0, ?float $avg = null, ?float $max = null, ?string $period = null, string $connection = 'database'): void
{
    $period ??= now()->startOfMinute()->format('Y-m-d H:i:s');

    DB::table('queue_monitor_metrics')->insert([
        'connection' => $connection,
        'queue' => $queue,
        'job' => $job,
        'period' => $period,
        'processed' => $processed,
        'failed' => $failed,
        'avg_runtime' => $avg,
        'max_runtime' => $max,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('aggregates completed jobs per job class and queue', function () {
    // Metrics are upserted per minute, so distinct rows need distinct minutes.
    insertMetricRow('emails', 'App\Jobs\SendMail', 5, 0, 0.20, 0.90, now()->format('Y-m-d H:i:s'));
    insertMetricRow('emails', 'App\Jobs\SendMail', 3, 0, 0.40, 1.10, now()->subMinute()->format('Y-m-d H:i:s'));
    insertMetricRow('reports', 'App\Jobs\SendMail', 2, 0, 1.00, 2.00, now()->subMinutes(2)->format('Y-m-d H:i:s'));
    insertMetricRow('emails', 'App\Jobs\BuildReport', 4, 1, 5.00, 5.00, now()->subMinutes(3)->format('Y-m-d H:i:s'));

    $rows = (new ListCompletedJobs())
        ->getTableQuery()
        ->get()
        ->keyBy(fn ($row) => $row->queue.'|'.$row->job);

    expect($rows)->toHaveCount(3)
        ->and($rows['emails|App\Jobs\SendMail']->processed)->toBe(8)
        ->and($rows['emails|App\Jobs\SendMail']->max_runtime)->toBe(1.10)
        ->and($rows['reports|App\Jobs\SendMail']->processed)->toBe(2)
        ->and($rows['emails|App\Jobs\BuildReport']->failed)->toBe(1)
        ->and($rows['emails|App\Jobs\BuildReport']->avg_runtime)->toBe(5.00);
});

it('scopes the page to the queue connection in use', function () {
    insertMetricRow('emails', 'App\Jobs\SendMail', 7, 0, 0.10, 0.10, connection: 'database');

    config()->set('queue.default', 'redis');

    expect((new ListCompletedJobs())->getTableQuery()->get())->toHaveCount(0);

    insertMetricRow('emails', 'App\Jobs\SendMail', 3, 0, 0.10, 0.10, connection: 'redis');

    $rows = (new ListCompletedJobs())->getTableQuery()->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->processed)->toBe(3);
});

it('defaults to today and narrows with the period filter', function () {
    insertMetricRow('emails', 'App\Jobs\SendMail', 2, 0, 0.10, 0.10, now()->format('Y-m-d H:i:s'));
    insertMetricRow('emails', 'App\Jobs\SendMail', 9, 0, 0.10, 0.10, now()->subDays(2)->format('Y-m-d H:i:s'));

    $page = new ListCompletedJobs();
    expect($page->getTableQuery()->get()->sum('processed'))->toBe(2);

    $page = new ListCompletedJobs();
    $page->tableFilters = ['period' => ['value' => '7d']];
    expect($page->getTableQuery()->get()->sum('processed'))->toBe(11);

    $page = new ListCompletedJobs();
    $page->tableFilters = ['period' => ['value' => 'hour']];
    expect($page->getTableQuery()->get()->sum('processed'))->toBe(2);
});

it('offers only queues that have metrics in the selected period', function () {
    insertMetricRow('emails', 'App\Jobs\SendMail', 1, 0, 0.10, 0.10);
    insertMetricRow('reports', 'App\Jobs\SendMail', 1, 0, 0.10, 0.10, now()->subDays(3)->format('Y-m-d H:i:s'));

    $page = new ListCompletedJobs();
    $options = new ReflectionMethod($page, 'queueOptions');
    $options->setAccessible(true);

    expect($options->invoke($page))->toBe(['emails' => 'emails']);
});

it('returns nothing when metrics are disabled', function () {
    insertMetricRow('emails', 'App\Jobs\SendMail', 5, 0, 0.10, 0.10);

    config()->set('filament-queue-monitor.metrics.enabled', false);

    expect((new ListCompletedJobs())->getTableQuery()->get())->toHaveCount(0);
});

it('returns rows through the filament filter pipeline, not just the raw query', function () {
    insertMetricRow('emails', 'App\Jobs\SendMail', 6, 0, 0.30, 0.90);
    insertMetricRow('reports', 'App\Jobs\BuildReport', 2, 0, 1.00, 1.00, now()->subMinute()->format('Y-m-d H:i:s'));

    $page = new ListCompletedJobs();
    $page->bootedInteractsWithTable();
    $page->table(\Filament\Tables\Table::make($page));

    $records = $page->getTableRecords();

    // A SelectFilter named after a real column makes Filament append
    // `where period = 'today'`, which silently empties the table. Assert the
    // records survive the filter pipeline, not just the hand-built query.
    expect($records)->toHaveCount(2);

    $sql = preg_replace('/\s+/', ' ', $page->getTableQuery()->toSql());

    expect($sql)->not->toContain('period` = ?');
});

it('narrows with the queue filter through filament', function () {
    insertMetricRow('emails', 'App\Jobs\SendMail', 3, 0, 0.10, 0.10);
    insertMetricRow('reports', 'App\Jobs\SendMail', 8, 0, 0.10, 0.10, now()->subMinute()->format('Y-m-d H:i:s'));

    $page = new ListCompletedJobs();
    $page->bootedInteractsWithTable();
    $page->tableFilters = ['queue' => ['value' => 'reports']];
    $page->table(\Filament\Tables\Table::make($page));

    $records = $page->getFilteredSortedTableQuery()->get();

    expect($records)->toHaveCount(1)
        ->and($records->first()->queue)->toBe('reports')
        ->and($records->first()->processed)->toBe(8);
});

it('exposes translated columns, filters and placeholders', function () {
    config()->set('filament-queue-monitor.driver', 'database');
    App::setLocale('ru');

    $page = new ListCompletedJobs();
    $table = $page->table(Table::make($page));

    $labels = array_map(fn ($column) => $column->getLabel(), $table->getColumns());

    expect($labels)->toContain(
        'Класс задания',
        'Очередь',
        'Выполнено',
        'Неудачно',
        'Среднее время',
        'Макс. время',
        'Последняя активность',
    )->and($table->getSearchPlaceholder())->toBe('Поиск классов заданий...')
        ->and($table->getFilter('period')->getOptions())->toBe([
            'hour' => 'Последний час',
            'today' => 'Сегодня',
            '24h' => 'Последние 24 часа',
            '7d' => 'Последние 7 дней',
        ])
        ->and($table->getFilter('period')->getDefaultState())->toBe('today')
        ->and($table->getEmptyStateDescription())->toBe('За выбранный период выполненных заданий не зафиксировано.')
        ->and(ListCompletedJobs::getNavigationLabel())->toBe('Выполненные задания');

    App::setLocale('en');
});

it('keeps the navigation group in step with the other pages', function () {
    App::setLocale('ru');

    // The panel registers this group, so if the pages disagreed the sidebar
    // would group them under a different label.
    expect(ListCompletedJobs::getNavigationGroup())->toBe(Trans::navigationGroup())
        ->toBe(\BlkDem\FilamentQueueMonitor\Filament\Pages\Dashboard::getNavigationGroup())
        ->toBe(\BlkDem\FilamentQueueMonitor\Filament\Pages\Queues\ListQueues::getNavigationGroup());

    App::setLocale('en');
});

it('shares the period window with the metrics storage', function () {
    $storage = app(MetricsStorage::class);

    expect($storage->periodStart('hour'))->toBe(now()->subHour()->format('Y-m-d H:i:s'))
        ->and($storage->periodStart('24h'))->toBe(now()->subHours(24)->format('Y-m-d H:i:s'))
        ->and($storage->periodStart('7d'))->toBe(now()->subDays(7)->format('Y-m-d H:i:s'))
        ->and($storage->periodStart('today'))->toBe(today()->toDateString())
        ->and($storage->periodStart('nonsense'))->toBe(today()->toDateString());
});
