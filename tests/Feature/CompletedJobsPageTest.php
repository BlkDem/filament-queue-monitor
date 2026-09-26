<?php

use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ListCompletedJobs;
use BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ViewCompletedJob;
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

it('encodes the job class safely for the url', function () {
    // A backslash is not escaped by Laravel's route parameter encoder and can
    // be normalised away by a browser, so the namespace separator is swapped.
    expect(ViewCompletedJob::encodeJob('App\Jobs\ProcessDataJob'))->toBe('App~Jobs~ProcessDataJob')
        ->and(ViewCompletedJob::encodeJob('App\Jobs\ProcessDataJob'))->not->toContain('\\')
        ->and(ViewCompletedJob::decodeJob('App~Jobs~ProcessDataJob'))->toBe('App\Jobs\ProcessDataJob')
        ->and(ViewCompletedJob::decodeJob(ViewCompletedJob::encodeJob('App\Jobs\SendMail')))
        ->toBe('App\Jobs\SendMail');
});

it('links each job class on the list to its detail page', function () {
    insertMetricRow('emails', 'App\Jobs\SendMail', 4, 0, 0.10, 0.20);

    // getUrl() needs a panel to resolve the route name.
    app()->instance('filament', new \Filament\FilamentManager);
    app()->alias('filament', \Filament\FilamentManager::class);

    $panel = \Filament\Panel::make('admin')->id('admin')->path('admin')->pages([
        ListCompletedJobs::class,
        ViewCompletedJob::class,
    ]);

    \Filament\Facades\Filament::registerPanel($panel);
    \Filament\Facades\Filament::setCurrentPanel($panel);

    foreach ([ListCompletedJobs::class, ViewCompletedJob::class] as $pageClass) {
        $parameters = $pageClass === ViewCompletedJob::class
            ? ['job' => ViewCompletedJob::encodeJob('App\Jobs\SendMail')]
            : [];

        Illuminate\Support\Facades\Route::get(
            $pageClass::getRoutePath($panel, $parameters),
            fn () => 'ok',
        )->name($pageClass::getRouteName('admin'));
    }

    $page = new ListCompletedJobs();
    $column = collect($page->table(Table::make($page))->getColumns())
        ->first(fn ($column) => $column->getName() === 'job');

    $record = new \BlkDem\FilamentQueueMonitor\QueueMonitor\Models\Metric;
    $record->setRawAttributes(['job' => 'App\Jobs\SendMail', 'queue' => 'emails', 'processed' => 4]);
    $column->record($record);

    $url = $column->getUrl();

    expect($url)->toContain('completed-jobs/App~Jobs~SendMail')
        ->and($url)->not->toContain('\\');
});

it('lists the minute by minute activity of one job class', function () {
    insertMetricRow('emails', 'App\Jobs\SendMail', 5, 1, 0.30, 0.90, now()->format('Y-m-d H:i:s'));
    insertMetricRow('emails', 'App\Jobs\SendMail', 2, 0, 0.40, 0.60, now()->subMinute()->format('Y-m-d H:i:s'));
    insertMetricRow('reports', 'App\Jobs\BuildReport', 9, 0, 1.00, 1.00, now()->subMinutes(2)->format('Y-m-d H:i:s'));
    insertMetricRow('emails', 'App\Jobs\SendMail', 7, 0, 0.10, 0.10, now()->subDays(10)->format('Y-m-d H:i:s'));

    $page = new ViewCompletedJob();
    $page->mount(ViewCompletedJob::encodeJob('App\Jobs\SendMail'));

    $rows = $page->getTableQuery()->get();

    expect($page->job)->toBe('App\Jobs\SendMail')
        ->and($rows)->toHaveCount(2, 'the row from three days ago is outside the default 7d window filter')
        ->and($rows->pluck('queue')->unique()->all())->toBe(['emails'])
        ->and($rows->sum('processed'))->toBe(7)
        ->and($rows->sum('failed'))->toBe(1)
        ->and($rows->max('max_runtime'))->toBe(0.90);
});

it('scopes the detail page to the queue connection in use', function () {
    insertMetricRow('emails', 'App\Jobs\SendMail', 4, 0, 0.10, 0.20, connection: 'database');

    $page = new ViewCompletedJob();
    $page->mount(ViewCompletedJob::encodeJob('App\Jobs\SendMail'));

    expect($page->getTableQuery()->get())->toHaveCount(1);

    config()->set('queue.default', 'redis');

    $redis = new ViewCompletedJob();
    $redis->mount(ViewCompletedJob::encodeJob('App\Jobs\SendMail'));

    expect($redis->getTableQuery()->get())->toHaveCount(0);
});

it('translates the detail page and keeps it out of the navigation', function () {
    App::setLocale('ru');

    $page = new ViewCompletedJob();
    $page->mount(ViewCompletedJob::encodeJob('App\Jobs\SendMail'));
    $table = $page->table(Table::make($page));

    $labels = array_map(fn ($column) => $column->getLabel(), $table->getColumns());

    expect($labels)->toContain('Минута', 'Очередь', 'Выполнено', 'Неудачно', 'Среднее время', 'Макс. время')
        ->and($table->getEmptyStateDescription())->toBe('За выбранный период этот класс заданий не выполнялся.')
        ->and($table->getSearchPlaceholder())->toBe('Поиск очередей...')
        ->and($page->getSubheading())->toBe('Активность этого класса заданий по минутам')
        ->and($page->getHeading())->toBe('App\Jobs\SendMail')
        ->and(ViewCompletedJob::shouldRegisterNavigation())->toBeFalse();

    App::setLocale('en');
});

it('returns nothing on the detail page when metrics are disabled', function () {
    insertMetricRow('emails', 'App\Jobs\SendMail', 4, 0, 0.10, 0.20);

    config()->set('filament-queue-monitor.metrics.enabled', false);

    $page = new ViewCompletedJob();
    $page->mount(ViewCompletedJob::encodeJob('App\Jobs\SendMail'));

    expect($page->getTableQuery()->get())->toHaveCount(0);
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
