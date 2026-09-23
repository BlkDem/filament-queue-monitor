<?php

use Filament\Tables\Table;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Support\Facades\DB;
use Kilo\FilamentQueueMonitor\Filament\Pages\Dashboard;
use Kilo\FilamentQueueMonitor\Filament\Widgets\MetricsChartWidget;
use Kilo\FilamentQueueMonitor\Filament\Pages\FailedJobs\ListFailedJobs;
use Kilo\FilamentQueueMonitor\Filament\Pages\Jobs\ListJobs;
use Kilo\FilamentQueueMonitor\Filament\Pages\Queues\ListQueues;

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

it('passes the selected period to the metrics widget', function () {
    $page = new Dashboard();
    $page->selectedPeriod = '7d';

    $widgets = $page->getVisibleFooterWidgets();

    expect($widgets)->toHaveCount(1)
        ->and($widgets[0])->toBeInstanceOf(WidgetConfiguration::class)
        ->and($widgets[0]->getProperties())->toMatchArray([
            'selectedPeriod' => '7d',
        ]);
});

it('filters the metrics chart using the selected period', function () {
    $recent = now()->startOfMinute()->format('Y-m-d H:i:s');
    $old = now()->subDays(8)->startOfMinute()->format('Y-m-d H:i:s');

    DB::table('queue_monitor_metrics')->insert([
        [
            'connection' => 'database',
            'queue' => 'default',
            'period' => $old,
            'processed' => 1,
            'failed' => 0,
            'avg_runtime' => null,
            'max_runtime' => null,
            'created_at' => $old,
            'updated_at' => $old,
        ],
        [
            'connection' => 'database',
            'queue' => 'default',
            'period' => $recent,
            'processed' => 2,
            'failed' => 0,
            'avg_runtime' => null,
            'max_runtime' => null,
            'created_at' => $recent,
            'updated_at' => $recent,
        ],
    ]);

    $widget = new MetricsChartWidget();
    $widget->refreshDashboard('7d');
    $getData = \Closure::bind(
        fn () => $widget->getData(),
        $widget,
        MetricsChartWidget::class,
    );

    expect($getData()['labels'])->toBe([$recent]);
});
