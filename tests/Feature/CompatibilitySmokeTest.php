<?php

use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Panel;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Illuminate\Database\Eloquent\Builder;
use BlkDem\FilamentQueueMonitor\Filament\Pages\Dashboard;
use BlkDem\FilamentQueueMonitor\Filament\Pages\FailedJobs\ListFailedJobs;
use BlkDem\FilamentQueueMonitor\Filament\Pages\FailedJobs\ViewFailedJob;
use BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ListJobs;
use BlkDem\FilamentQueueMonitor\Filament\Pages\Queues\ListQueues;
use BlkDem\FilamentQueueMonitor\Filament\Widgets\QueueActivityWidget;
use BlkDem\FilamentQueueMonitor\Filament\Widgets\JobBreakdownWidget;
use BlkDem\FilamentQueueMonitor\Filament\Widgets\QueueStatsOverviewWidget;
use BlkDem\FilamentQueueMonitor\QueueMonitor\DTO\FailedJobInfo;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Models\QueueJob;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Models\FailedJob as QueueMonitorFailedJob;

it('builds queue monitor tables', function () {
    config()->set('filament-queue-monitor.driver', 'database');
    config()->set('filament-queue-monitor.refresh_interval', 0);

    $pages = [
        new ListQueues(),
        new ListJobs(),
        new ListFailedJobs(),
    ];

    foreach ($pages as $page) {
        $table = $page->table(Table::make($page));

        expect($table)->toBeInstanceOf(Table::class);
    }
});

it('groups the dashboard tables by queue', function () {
    config()->set('filament-queue-monitor.driver', 'database');
    config()->set('filament-queue-monitor.refresh_interval', 0);

    $activity = new QueueActivityWidget();
    $activityTable = $activity->table(Table::make($activity));

    expect($activityTable)->toBeInstanceOf(Table::class)
        ->and($activityTable->getDefaultGroup())->not->toBeNull()
        ->and($activityTable->getDefaultGroup()->getId())->toBe('queue')
        ->and($activityTable->getColumns())->not->toBeEmpty();

    $breakdown = new JobBreakdownWidget();
    $breakdown->selectedPeriod = '7d';
    $breakdownTable = $breakdown->table(Table::make($breakdown));

    expect($breakdownTable)->toBeInstanceOf(Table::class)
        ->and($breakdownTable->getDefaultGroup())->not->toBeNull()
        ->and($breakdownTable->getDefaultGroup()->getId())->toBe('queue')
        ->and($breakdownTable->getColumns())->not->toBeEmpty();
});

it('exposes the statistics period options on the job breakdown widget', function () {
    $widget = new JobBreakdownWidget();

    $widget->boot();

    expect($widget->selectedPeriod)->toBe('today')
        ->and($widget->periods)->toMatchArray([
            'hour' => 'Last hour',
            'today' => 'Today',
            '24h' => 'Last 24 hours',
            '7d' => 'Last 7 days',
        ]);
});

it('excludes delayed jobs from the queue activity query so it matches the counters', function () {
    config()->set('filament-queue-monitor.driver', 'database');
    config()->set('filament-queue-monitor.refresh_interval', 0);

    $widget = new QueueActivityWidget();
    $activityTable = $widget->table(Table::make($widget));

    $sql = $activityTable->getQuery()->toSql();

    expect($sql)
        ->toContain('available_at')
        ->toContain('reserved_at')
        ->toContain('is null')
        ->toContain('is not null');
});

it('polls the queue activity using the shared refresh interval', function () {
    config()->set('filament-queue-monitor.refresh_interval', 10);

    $widget = new QueueActivityWidget();

    $poll = \Closure::bind(
        fn () => $widget->getPollingInterval(),
        $widget,
        QueueActivityWidget::class,
    );

    expect($poll())->toBe('10s');

    config()->set('filament-queue-monitor.refresh_interval', 0);
    expect($poll())->toBeNull();
});

it('lets the queue activity polling be overridden from the toolbar dropdown', function () {
    config()->set('filament-queue-monitor.refresh_interval', 10);

    $widget = new QueueActivityWidget();

    $poll = \Closure::bind(
        fn () => $widget->getPollingInterval(),
        $widget,
        QueueActivityWidget::class,
    );

    $widget->pollingInterval = '15s';
    expect($poll())->toBe('15s');

    $widget->pollingInterval = 'off';
    expect($poll())->toBeNull();

    $widget->pollingInterval = '';
    expect($poll())->toBe('10s');
});

it('lets the queue activity polling drive the counters widgets', function () {
    config()->set('filament-queue-monitor.refresh_interval', 10);

    $widget = new QueueStatsOverviewWidget();

    $widget->setPollingInterval('30s');

    $poll = \Closure::bind(
        fn () => $widget->getPollingInterval(),
        $widget,
        QueueStatsOverviewWidget::class,
    );

    expect($poll())->toBe('30s');

    $widget->setPollingInterval('');

    expect($poll())->toBe('10s');

    $widget->setPollingInterval('off');

    expect($poll())->toBeNull();

    expect(\Closure::bind(
        fn () => $widget->listeners,
        $widget,
        QueueStatsOverviewWidget::class,
    )())->toHaveKey('queueActivityPollingIntervalChanged', 'setPollingInterval');
});

it('polls the job breakdown using the metrics refresh interval', function () {
    config()->set('filament-queue-monitor.metrics.refresh_interval', 30);

    $widget = new JobBreakdownWidget();

    $poll = \Closure::bind(
        fn () => $widget->getPollingInterval(),
        $widget,
        JobBreakdownWidget::class,
    );

    expect($poll())->toBe('30s');

    config()->set('filament-queue-monitor.metrics.refresh_interval', 0);
    expect($poll())->toBeNull();
});

it('lets the job breakdown polling be overridden from the widget dropdown', function () {
    config()->set('filament-queue-monitor.metrics.refresh_interval', 30);

    $widget = new JobBreakdownWidget();

    $poll = \Closure::bind(
        fn () => $widget->getPollingInterval(),
        $widget,
        JobBreakdownWidget::class,
    );

    expect($poll())->toBe('30s');

    $widget->pollingInterval = '10s';
    expect($poll())->toBe('10s');

    $widget->pollingInterval = 'off';
    expect($poll())->toBeNull();
});

it('makes the queue groups collapsible', function () {
    config()->set('filament-queue-monitor.driver', 'database');

    $activity = new QueueActivityWidget();
    $activityTable = $activity->table(Table::make($activity));

    expect($activityTable->getDefaultGroup()->isCollapsible())->toBeTrue();

    $breakdown = new JobBreakdownWidget();
    $breakdownTable = $breakdown->table(Table::make($breakdown));

    expect($breakdownTable->getDefaultGroup()->isCollapsible())->toBeTrue();
});

it('shows the task count in the queue group header', function () {
    config()->set('filament-queue-monitor.driver', 'database');

    DB::table('jobs')->insert([
        [
            'queue' => 'calculations',
            'payload' => json_encode(['data' => []]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ],
        [
            'queue' => 'calculations',
            'payload' => json_encode(['data' => []]),
            'attempts' => 1,
            'reserved_at' => now()->timestamp,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ],
    ]);

    $widget = new QueueActivityWidget();
    $activityTable = $widget->table(Table::make($widget));

    $record = QueueJob::query()->where('queue', 'calculations')->first();

    expect($record)->not->toBeNull()
        ->and($activityTable->getDefaultGroup()->getTitle($record))->toBe('calculations (2)');
});

it('shows the task count in the status group header', function () {
    config()->set('filament-queue-monitor.driver', 'database');

    DB::table('jobs')->insert([
        [
            'queue' => 'calculations',
            'payload' => json_encode(['data' => []]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ],
        [
            'queue' => 'calculations',
            'payload' => json_encode(['data' => []]),
            'attempts' => 1,
            'reserved_at' => now()->timestamp,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ],
    ]);

    $widget = new QueueActivityWidget();
    $activityTable = $widget->table(Table::make($widget));

    $statusGroup = $activityTable->getGroup('status');

    $pending = QueueJob::query()->whereNull('reserved_at')->first();
    $processing = QueueJob::query()->whereNotNull('reserved_at')->first();

    expect($pending)->not->toBeNull()
        ->and($processing)->not->toBeNull()
        ->and($statusGroup)->not->toBeNull()
        ->and($statusGroup->getTitle($pending))->toBe('Pending (1)')
        ->and($statusGroup->getTitle($processing))->toBe('Processing (1)');

    $ordered = $statusGroup->orderQuery(QueueJob::query(), 'asc');

    expect($ordered->toSql())->toContain('CASE WHEN reserved_at IS NOT NULL');
});

it('adds a queue filter to the dashboard tables', function () {
    config()->set('filament-queue-monitor.driver', 'database');

    DB::table('jobs')->insert([
        [
            'queue' => 'calculations',
            'payload' => json_encode(['data' => []]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ],
        [
            'queue' => 'notifications-high',
            'payload' => json_encode(['data' => []]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ],
    ]);

    $activity = new QueueActivityWidget();
    $activityTable = $activity->table(Table::make($activity));

    $filter = $activityTable->getFilter('queue');

    expect($filter)->not->toBeNull()
        ->and($filter)->toBeInstanceOf(SelectFilter::class)
        ->and($filter->getOptions())->toBe([
            'calculations' => 'calculations',
            'notifications-high' => 'notifications-high',
        ]);

    $breakdown = new JobBreakdownWidget();
    $breakdown->selectedPeriod = '7d';
    $breakdownTable = $breakdown->table(Table::make($breakdown));

    expect($breakdownTable->getFilter('queue'))->not->toBeNull()
        ->and($breakdownTable->getFilter('queue')->getOptions())->toBeEmpty();

    DB::table('queue_monitor_metrics')->insert([
        [
            'connection' => 'database',
            'queue' => 'calculations',
            'period' => today()->toDateString(),
            'processed' => 5,
            'failed' => 0,
            'avg_runtime' => 1.5,
            'max_runtime' => 2.1,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'connection' => 'database',
            'queue' => 'notifications',
            'period' => today()->toDateString(),
            'processed' => 3,
            'failed' => 1,
            'avg_runtime' => 0.9,
            'max_runtime' => 1.2,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $breakdownTable = $breakdown->table(Table::make($breakdown));

    expect($breakdownTable->getFilter('queue')->getOptions())->toBe([
        'calculations' => 'calculations',
        'notifications' => 'notifications',
    ]);
});

it('adds a status filter to the queue activity table', function () {
    config()->set('filament-queue-monitor.driver', 'database');

    $activity = new QueueActivityWidget();
    $activityTable = $activity->table(Table::make($activity));

    $filter = $activityTable->getFilter('status');

    expect($filter)->not->toBeNull()
        ->and($filter)->toBeInstanceOf(SelectFilter::class)
        ->and($filter->getOptions())->toBe([
            'pending' => 'Pending',
            'processing' => 'Processing',
        ]);
});

it('keeps processing jobs visible when filtered by status', function () {
    config()->set('filament-queue-monitor.driver', 'database');

    foreach (range(1, 6) as $i) {
        DB::table('jobs')->insert([
            'queue' => 'errors',
            'payload' => json_encode(['data' => [$i]]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp - $i,
        ]);
    }

    foreach (range(1, 3) as $i) {
        DB::table('jobs')->insert([
            'queue' => 'errors',
            'payload' => json_encode(['data' => ['reserved' => $i]]),
            'attempts' => 0,
            'reserved_at' => now()->timestamp - 60,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp - 3600 - $i,
        ]);
    }

    $counterProcessing = collect(
        app(BlkDem\FilamentQueueMonitor\QueueMonitor\QueueMonitorManager::class)->driver()->getQueues()
    )->sum(fn ($queueInfo) => $queueInfo->processing);

    $widget = new QueueActivityWidget();
    $widget->tableFilters = [
        'queue' => ['value' => null],
        'status' => ['value' => 'processing'],
    ];
    $activityTable = $widget->table(Table::make($widget));

    $rows = $activityTable->getQuery()->get();

    expect($rows)->toHaveCount($counterProcessing)
        ->and($counterProcessing)->toBe(3)
        ->and($rows->every(fn ($row): bool => $row->reserved_at !== null))->toBeTrue();

    $sample = $rows->first();

    expect($sample)->not->toBeNull()
        ->and($activityTable->getDefaultGroup()->getTitle($sample))->toBe('errors (3)');
});

it('narrows the queue activity by status', function () {
    config()->set('filament-queue-monitor.driver', 'database');

    DB::table('jobs')->insert([
        [
            'queue' => 'calculations',
            'payload' => json_encode(['data' => []]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ],
        [
            'queue' => 'calculations',
            'payload' => json_encode(['data' => []]),
            'attempts' => 1,
            'reserved_at' => now()->timestamp,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ],
        [
            'queue' => 'errors',
            'payload' => json_encode(['data' => []]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ],
    ]);

    $activity = new QueueActivityWidget();
    $activityTable = $activity->table(Table::make($activity));

    $base = QueueJob::query()->where(function (Builder $query): void {
        $query
            ->whereNull('reserved_at')
            ->where('available_at', '<=', now()->timestamp)
            ->orWhereNotNull('reserved_at');
    });

    $pending = $activityTable->getFilter('status')->apply($base->clone(), ['value' => 'pending']);

    expect((clone $pending)->whereNull('reserved_at')->count())->toBe(2)
        ->and((clone $pending)->whereNotNull('reserved_at')->count())->toBe(0);

    $processing = $activityTable->getFilter('status')->apply($base->clone(), ['value' => 'processing']);

    expect((clone $processing)->whereNull('reserved_at')->count())->toBe(0)
        ->and((clone $processing)->whereNotNull('reserved_at')->count())->toBe(1);
});

it('shows every active queue in the queue activity table regardless of pagination', function () {
    config()->set('filament-queue-monitor.driver', 'database');

    foreach (range(1, 6) as $i) {
        DB::table('jobs')->insert([
            'queue' => 'calculations',
            'payload' => json_encode(['data' => [$i]]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp - $i,
        ]);
    }

    DB::table('jobs')->insert([
        'queue' => 'notifications-high',
        'payload' => json_encode(['data' => []]),
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->timestamp,
        'created_at' => now()->timestamp,
    ]);

    $widget = new QueueActivityWidget();
    $activityTable = $widget->table(Table::make($widget));

    $rows = $activityTable->getQuery()->get()->groupBy('queue')->map->count();

    expect($activityTable->isPaginated())->toBeFalse()
        ->and($rows->get('calculations'))->toBe(5)
        ->and($rows->get('notifications-high'))->toBe(1);
});

it('narrows the queue activity to the selected queue', function () {
    config()->set('filament-queue-monitor.driver', 'database');

    DB::table('jobs')->insert([
        [
            'queue' => 'calculations',
            'payload' => json_encode(['data' => []]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ],
        [
            'queue' => 'notifications-high',
            'payload' => json_encode(['data' => []]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ],
    ]);

    $activity = new QueueActivityWidget();
    $activityTable = $activity->table(Table::make($activity));

    $query = $activityTable->getFilter('queue')->apply(
        QueueJob::query()->where(function (Builder $query): void {
            $query
                ->whereNull('reserved_at')
                ->where('available_at', '<=', now()->timestamp)
                ->orWhereNotNull('reserved_at');
        }),
        ['value' => 'calculations'],
    );

    expect($query->pluck('queue')->unique()->values()->all())->toBe(['calculations']);
});

it('filters the failed jobs by queue and job', function () {
    config()->set('filament-queue-monitor.driver', 'database');
    config()->set('filament-queue-monitor.refresh_interval', 0);

    insertSmokeFailedJob('emails', 'App\\Jobs\\SendEmail', 'exc-email');
    insertSmokeFailedJob('default', 'App\\Jobs\\TestJob', 'exc-test');
    insertSmokeFailedJob('default', 'App\\Jobs\\OtherJob', 'exc-other');

    $page = new ListFailedJobs();
    $page->bootedInteractsWithTable();

    $page->tableFilters = ['queue' => ['value' => 'emails']];
    $page->flushCachedTableRecords();
    $records = $page->getTableRecords()->getCollection();

    expect($records)->toHaveCount(1)
        ->and($records->first()->queue)->toBe('emails');

    $page->tableFilters = [
        'queue' => ['value' => 'default'],
        'job' => ['value' => 'App\\Jobs\\OtherJob'],
    ];
    $page->flushCachedTableRecords();
    $records = $page->getTableRecords()->getCollection();

    expect($records)->toHaveCount(1)
        ->and($records->first()->job)->toBe('App\\Jobs\\OtherJob');
});

it('searches failed jobs by id, uuid, payload or exception', function () {
    config()->set('filament-queue-monitor.driver', 'database');
    config()->set('filament-queue-monitor.refresh_interval', 0);

    $uuidEmail = insertSmokeFailedJob('emails', 'App\\Jobs\\SendEmail', 'exc-unique-token', extraPayload: 'payload-token-email');
    $uuidTest = insertSmokeFailedJob('default', 'App\\Jobs\\TestJob', 'exc-other');

    $page = new ListFailedJobs();
    $page->bootedInteractsWithTable();

    $page->tableSearch = 'exc-unique-token';
    $page->flushCachedTableRecords();
    expect($page->getTableRecords()->total())->toBe(1);

    $page->tableSearch = 'payload-token-email';
    $page->flushCachedTableRecords();
    expect($page->getTableRecords()->total())->toBe(1);

    $page->tableSearch = (string) $uuidEmail;
    $page->flushCachedTableRecords();
    expect($page->getTableRecords()->total())->toBe(1);

    $page->tableSearch = 'nothing-matches-this';
    $page->flushCachedTableRecords();
    expect($page->getTableRecords()->total())->toBe(0);
});

it('filters the failed jobs by failed at range', function () {
    config()->set('filament-queue-monitor.driver', 'database');
    config()->set('filament-queue-monitor.refresh_interval', 0);

    insertSmokeFailedJob('default', 'App\\Jobs\\OldJob', 'exc-old', failedAt: now()->subDays(10));
    insertSmokeFailedJob('default', 'App\\Jobs\\RecentJob', 'exc-recent', failedAt: now()->subDay(1));
    insertSmokeFailedJob('default', 'App\\Jobs\\TodayJob', 'exc-today', failedAt: now());

    $page = new ListFailedJobs();
    $page->bootedInteractsWithTable();

    $page->tableFilters = [
        'failedAt' => ['from' => now()->subDays(2)->toDateString(), 'until' => now()->toDateString()],
    ];
    $page->flushCachedTableRecords();
    $records = $page->getTableRecords()->getCollection();

    expect($records)->toHaveCount(2)
        ->and($records->pluck('job')->sort()->values()->all())
        ->toBe(['App\\Jobs\\RecentJob', 'App\\Jobs\\TodayJob']);

    $page->tableFilters = ['failedAt' => ['from' => now()->subYears(1)->toDateString()]];
    $page->flushCachedTableRecords();
    expect($page->getTableRecords()->total())->toBe(3);

    $page->tableFilters = ['failedAt' => ['until' => now()->subDays(5)->toDateString()]];
    $page->flushCachedTableRecords();
    expect($page->getTableRecords()->total())->toBe(1);

    $page->tableFilters = ['failedAt' => ['from' => null, 'until' => null]];
    $page->flushCachedTableRecords();
    expect($page->getTableRecords()->total())->toBe(3);
});

it('filters the failed jobs by failed at time boundaries', function () {
    config()->set('filament-queue-monitor.driver', 'database');
    config()->set('filament-queue-monitor.refresh_interval', 0);

    $instant = now()->subHours(2)->setMinute(30)->setSecond(15);

    insertSmokeFailedJob('default', 'App\\Jobs\\Before', 'exc-before', failedAt: $instant->copy()->subMinutes(10));
    insertSmokeFailedJob('default', 'App\\Jobs\\Mid', 'exc-mid', failedAt: $instant->copy()->addMinutes(5));
    insertSmokeFailedJob('default', 'App\\Jobs\\After', 'exc-after', failedAt: $instant->copy()->addMinutes(30));

    $page = new ListFailedJobs();
    $page->bootedInteractsWithTable();

    $page->tableFilters = [
        'failedAt' => [
            'from' => $instant->toDateTimeString(),
            'until' => $instant->copy()->addMinutes(10)->toDateTimeString(),
        ],
    ];
    $page->flushCachedTableRecords();
    $records = $page->getTableRecords()->getCollection();

    expect($records)->toHaveCount(1)
        ->and($records->first()->job)->toBe('App\\Jobs\\Mid');

    $page->tableFilters = [
        'failedAt' => ['from' => $instant->copy()->addMinutes(5)->toDateTimeString()],
    ];
    $page->flushCachedTableRecords();
    expect($page->getTableRecords()->total())->toBe(2); // Mid + After
});

it('shows real queue names on the queues page (not Unknown)', function () {
    config()->set('filament-queue-monitor.driver', 'database');
    config()->set('filament-queue-monitor.refresh_interval', 0);

    DB::table('jobs')->insert([
        'queue' => 'test-queue',
        'payload' => json_encode(['data' => []]),
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->timestamp,
        'created_at' => now()->timestamp,
    ]);

    $page = new ListQueues();
    $page->bootedInteractsWithTable();

    $records = $page->getTableRecords()->getCollection();

    expect($records)->not->toBeEmpty()
        ->and($records->first()->queue)->toBe('test-queue')
        ->and($records->first()->queue)->not->toBe('Unknown');
});

it('resolves the failed job detail page with readable payload and exception', function () {
    config()->set('filament-queue-monitor.driver', 'database');
    config()->set('filament-queue-monitor.refresh_interval', 0);

    $uuid = insertSmokeFailedJob(
        'errors',
        'App\\Jobs\\SimulateErrorJob',
        "RuntimeException: boom\n    at line 42",
        extraPayload: '{"orderId":1234,"meta":{"tags":["a","b"]}}',
    );

    $page = new ViewFailedJob();
    $page->mount($uuid);

    $job = $page->getJob();

    expect($job)->toBeInstanceOf(FailedJobInfo::class)
        ->and($page->getJobName())->toBe('App\\Jobs\\SimulateErrorJob')
        ->and($job->exception)->toContain('RuntimeException');

    $viewData = $page->getViewData();

    expect($viewData['payloadData']['displayName'])->toBe('App\\Jobs\\SimulateErrorJob')
        ->and($viewData['payloadData']['extra'])->toContain('orderId')
        ->and($viewData['jobName'])->toBe('App\\Jobs\\SimulateErrorJob');
});

it('links the failed job name to the job detail page', function () {
    config()->set('filament-queue-monitor.driver', 'database');
    config()->set('filament-queue-monitor.refresh_interval', 0);

    $uuid = insertSmokeFailedJob('default', 'App\\Jobs\\SomeJob', 'exc');

    bindTestFilamentManager([Dashboard::class, ListFailedJobs::class, ViewFailedJob::class]);

    $page = new ListFailedJobs();
    $page->bootedInteractsWithTable();

    $column = collect($page->getTable()->getColumns())->first(fn ($c) => $c->getName() === 'job');

    expect($column)->not->toBeNull();

    $model = new QueueMonitorFailedJob();
    $model->id = $uuid;
    $model->uuid = $uuid;

    $column->record($model);

    expect($column->getUrl())->toContain('/queue-monitor/failed-jobs/' . $uuid);
});

it('renders the failed job detail view with payload and collapsible exception', function () {
    config()->set('filament-queue-monitor.driver', 'database');
    config()->set('filament-queue-monitor.refresh_interval', 0);

    $uuid = insertSmokeFailedJob(
        'errors',
        'App\\Jobs\\SimulateErrorJob',
        "RuntimeException: boom\n    at line 42",
        extraPayload: '{"orderId":1234}',
    );

    bindTestFilamentManager([Dashboard::class, ListFailedJobs::class, ViewFailedJob::class]);

    $page = new ViewFailedJob();
    $page->mount($uuid);

    $html = view('filament-queue-monitor::pages.view-failed-job', $page->getViewData())->render();

    expect($html)->toContain('Job information')
        ->and($html)->toContain('App\\Jobs\\SimulateErrorJob')
        ->and($html)->toContain('Payload')
        ->and($html)->toContain('Error')
        ->and($html)->toContain('fqm-codebox')
        ->and($html)->toContain('RuntimeException')
        ->and($html)->toContain('&quot;displayName&quot;');
});

function bindTestFilamentManager(array $pages): void
{
    app()->instance('filament', new \Filament\FilamentManager);
    app()->alias('filament', \Filament\FilamentManager::class);

    $panel = Panel::make('admin')
        ->id('admin')
        ->path('admin')
        ->pages($pages);

    Filament::registerPanel($panel);
    Filament::setCurrentPanel($panel);

    foreach ($pages as $pageClass) {
        $name = $pageClass::getRouteName('admin');
        $path = $pageClass::getRoutePath($panel);

        Route::get($path, fn () => 'ok')->name($name);
    }
}

function insertSmokeFailedJob(
    string $queue,
    string $jobClass,
    string $exception,
    ?string $uuid = null,
    ?string $extraPayload = null,
    ?\Illuminate\Support\Carbon $failedAt = null,
): string {
    $uuid ??= (string) \Illuminate\Support\Str::uuid();

    $payloadData = [
        'displayName' => $jobClass,
        'uuid' => $uuid,
        'job' => $jobClass,
        'data' => ['commandName' => $jobClass, 'command' => ''],
    ];

    if ($extraPayload !== null) {
        $payloadData['extra'] = $extraPayload;
    }

    DB::table('failed_jobs')->insert([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => $queue,
        'payload' => json_encode($payloadData),
        'exception' => $exception,
        'failed_at' => ($failedAt ?? now())->toDateTimeString(),
    ]);

    return $uuid;
}