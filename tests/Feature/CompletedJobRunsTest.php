<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Table;
use BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ListCompletedJobRuns;
use BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ListCompletedJobs;
use BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ViewCompletedJob;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Listeners\RecordQueueMetrics;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Models\Metric;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Statistics\CompletedJobsStorage;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Statistics\MetricsStorage;

beforeEach(function () {
    config()->set('filament-queue-monitor.metrics.enabled', true);
    config()->set('queue.default', 'database');
});

function insertCompletedRun(string $job, string $queue, ?string $uuid = null, ?float $runtime = null, ?string $finishedAt = null, string $connection = 'database'): void
{
    $now = now();

    DB::table('queue_monitor_completed_jobs')->insert([
        'connection' => $connection,
        'queue' => $queue,
        'job' => $job,
        'uuid' => $uuid,
        'runtime' => $runtime,
        'finished_at' => $finishedAt ?? $now->toDateTimeString(),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

it('records one row per processed job alongside the aggregate', function () {
    $storage = new MetricsStorage;
    $completed = new CompletedJobsStorage;

    expect($completed->tableExists())->toBeTrue();

    $job = new class extends \Illuminate\Queue\Jobs\Job
    {
        public function uuid(): ?string
        {
            return 'run-uuid-1';
        }

        public function getJobId(): string
        {
            return 'run-uuid-1';
        }

        public function getRawBody(): string
        {
            return '{}';
        }

        public function resolveName(): string
        {
            return 'App\Jobs\SendMail';
        }

        public function getQueue(): string
        {
            return 'emails';
        }
    };

    $started = microtime(true);
    cache()->put('queue-monitor:start:'.md5('database:run-uuid-1'), $started - 0.5, 60);

    (new RecordQueueMetrics($storage, $completed))->handleProcessed(
        new \Illuminate\Queue\Events\JobProcessed('database', $job)
    );

    $row = DB::table('queue_monitor_completed_jobs')->first();

    expect($row)->not->toBeNull()
        ->and($row->job)->toBe('App\Jobs\SendMail')
        ->and($row->queue)->toBe('emails')
        ->and($row->connection)->toBe('database')
        ->and($row->uuid)->toBe('run-uuid-1')
        ->and((float) $row->runtime)->toBeGreaterThan(0.0);

    // the aggregate is still written as before
    expect(DB::table('queue_monitor_metrics')->where('job', 'App\Jobs\SendMail')->sum('processed'))->toBe(1);
});

it('prunes completed runs with the metrics retention setting', function () {
    insertCompletedRun('App\Jobs\Old', 'emails', null, 0.1, now()->subDays(40)->toDateTimeString());
    insertCompletedRun('App\Jobs\New', 'emails', null, 0.1, now()->subDay()->toDateTimeString());

    $completed = new CompletedJobsStorage;

    expect($completed->prune(30))->toBe(1)
        ->and(DB::table('queue_monitor_completed_jobs')->pluck('job')->all())->toBe(['App\Jobs\New']);
});

it('keeps everything when retention is zero', function () {
    insertCompletedRun('App\Jobs\Old', 'emails', null, 0.1, now()->subDays(400)->toDateTimeString());

    expect((new CompletedJobsStorage)->prune(0))->toBe(0)
        ->and(DB::table('queue_monitor_completed_jobs')->count())->toBe(1);
});

it('encodes and decodes the minute for the url', function () {
    $minute = Carbon::parse('2026-09-26 06:10:00');

    expect(ListCompletedJobRuns::encodeMinute($minute))->toBe('202609260610')
        ->and(ListCompletedJobRuns::encodeMinute('2026-09-26 06:10:00'))->toBe('202609260610')
        ->and(ListCompletedJobRuns::decodeMinute('202609260610'))->toBe('2026-09-26 06:10:00')
        ->and(ListCompletedJobRuns::encodeMinute($minute))->not->toContain(':')
        ->and(ListCompletedJobRuns::encodeMinute($minute))->not->toContain(' ');
});

it('lists the individual runs of one job class in one minute', function () {
    $minute = '2026-09-26 06:10:00';

    insertCompletedRun('App\Jobs\SendMail', 'emails', 'a-1', 0.12, $minute);
    insertCompletedRun('App\Jobs\SendMail', 'emails', 'a-2', 0.20, $minute);
    insertCompletedRun('App\Jobs\SendMail', 'reports', 'a-3', 0.30, $minute);
    insertCompletedRun('App\Jobs\SendMail', 'emails', 'other-minute', 0.10, '2026-09-26 06:11:00');
    insertCompletedRun('App\Jobs\BuildReport', 'emails', 'b-1', 0.40, $minute);

    $page = new ListCompletedJobRuns;
    $page->mount(ViewCompletedJob::encodeJob('App\Jobs\SendMail'), '202609260610');

    $rows = $page->getTableQuery()->orderBy('finished_at')->get();

    expect($page->job)->toBe('App\Jobs\SendMail')
        ->and($page->minute)->toBe('2026-09-26 06:10:00')
        ->and($rows)->toHaveCount(3)
        ->and($rows->pluck('uuid')->all())->toBe(['a-1', 'a-2', 'a-3'])
        ->and($rows->pluck('queue')->unique()->sort()->values()->all())->toBe(['emails', 'reports']);
});

it('scopes the runs list to the queue connection in use', function () {
    insertCompletedRun('App\Jobs\SendMail', 'emails', 'redis-run', 0.1, '2026-09-26 06:10:00', connection: 'redis');

    $page = new ListCompletedJobRuns;
    $page->mount(ViewCompletedJob::encodeJob('App\Jobs\SendMail'), '202609260610');

    expect($page->getTableQuery()->get())->toHaveCount(0);

    config()->set('queue.default', 'redis');

    $redis = new ListCompletedJobRuns;
    $redis->mount(ViewCompletedJob::encodeJob('App\Jobs\SendMail'), '202609260610');

    expect($redis->getTableQuery()->get())->toHaveCount(1);
});

it('links each minute on the job class page to the runs list', function () {
    app()->instance('filament', new \Filament\FilamentManager);
    app()->alias('filament', \Filament\FilamentManager::class);

    $panel = \Filament\Panel::make('admin')->id('admin')->path('admin')->pages([
        ListCompletedJobs::class,
        ViewCompletedJob::class,
        ListCompletedJobRuns::class,
    ]);

    \Filament\Facades\Filament::registerPanel($panel);
    \Filament\Facades\Filament::setCurrentPanel($panel);

    $job = ViewCompletedJob::encodeJob('App\Jobs\SendMail');
    $minute = ListCompletedJobRuns::encodeMinute('2026-09-26 06:10:00');

    foreach ([
        ListCompletedJobs::class => [],
        ViewCompletedJob::class => ['job' => $job],
        ListCompletedJobRuns::class => ['job' => $job, 'minute' => $minute],
    ] as $pageClass => $parameters) {
        Illuminate\Support\Facades\Route::get(
            $pageClass::getRoutePath($panel, $parameters),
            fn () => 'ok',
        )->name($pageClass::getRouteName('admin'));
    }

    $page = new ViewCompletedJob;
    $page->mount($job);
    $column = collect($page->table(Table::make($page))->getColumns())
        ->first(fn ($column) => $column->getName() === 'period');

    $record = new Metric;
    $record->setRawAttributes(['period' => '2026-09-26 06:10:00', 'queue' => 'emails', 'processed' => 10]);
    $column->record($record);

    expect($column->getUrl())->toContain('/runs/202609260610')
        ->toContain('App~Jobs~SendMail');
});

it('translates the runs page', function () {
    App::setLocale('ru');

    $page = new ListCompletedJobRuns;
    $page->mount(ViewCompletedJob::encodeJob('App\Jobs\SendMail'), '202609260610');
    $table = $page->table(Table::make($page));

    $labels = array_map(fn ($column) => $column->getLabel(), $table->getColumns());

    expect($labels)->toContain('Очередь', 'ID задания', 'Длительность', 'Завершено')
        ->and($page->getHeading())->toBe('Запуски App\Jobs\SendMail')
        ->and($page->getSubheading())->toContain('26.09.2026 06:10')
        ->and($table->getEmptyStateDescription())->toBe('За эту минуту отдельные запуски этого класса не зафиксированы.')
        ->and($table->getSearchPlaceholder())->toBe('Поиск идентификаторов...')
        ->and(ListCompletedJobRuns::shouldRegisterNavigation())->toBeFalse();

    App::setLocale('en');
});

it('returns nothing on the runs page when the table does not exist', function () {
    config()->set('filament-queue-monitor.metrics.table_completed_jobs', 'missing_table_xyz');

    $page = new ListCompletedJobRuns;
    $page->mount(ViewCompletedJob::encodeJob('App\Jobs\SendMail'), '202609260610');

    expect($page->getTableQuery()->get())->toHaveCount(0);
});
