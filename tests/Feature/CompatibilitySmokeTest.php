<?php

use Filament\Tables\Table;
use Filament\Widgets\WidgetConfiguration;
use Kilo\FilamentQueueMonitor\Filament\Pages\Dashboard;
use Kilo\FilamentQueueMonitor\Filament\Pages\FailedJobs\ListFailedJobs;
use Kilo\FilamentQueueMonitor\Filament\Pages\Jobs\ListJobs;
use Kilo\FilamentQueueMonitor\Filament\Pages\Queues\ListQueues;
use Kilo\FilamentQueueMonitor\Filament\Widgets\QueueActivityWidget;
use Kilo\FilamentQueueMonitor\Filament\Widgets\JobBreakdownWidget;
use Kilo\FilamentQueueMonitor\QueueMonitor\Models\QueueJob;

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

it('passes the selected period to the job breakdown widget', function () {
    $page = new Dashboard();
    $page->selectedPeriod = '7d';

    $widgets = $page->getVisibleFooterWidgets();

    expect($widgets)->toHaveCount(2)
        ->and($widgets[0])->toBeInstanceOf(WidgetConfiguration::class)
        ->and($widgets[1])->toBeInstanceOf(WidgetConfiguration::class)
        ->and($widgets[1]->getProperties())->toMatchArray([
            'selectedPeriod' => '7d',
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