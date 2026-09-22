<?php

namespace Kilo\FilamentQueueMonitor\Filament\Pages;

use Filament\Pages\Page;
use Kilo\FilamentQueueMonitor\Filament\Widgets;

class Dashboard extends Page
{
    protected static string $view = 'filament-queue-monitor::pages.dashboard';

    protected static ?string $navigationLabel = 'Queue Monitor';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Queue Monitor';

    protected static ?int $navigationSort = 0;

    protected static bool $shouldRegisterNavigation = true;

    public string $selectedPeriod = 'today';

    public array $periods = [
        'hour' => 'Last hour',
        'today' => 'Today',
        '24h' => 'Last 24 hours',
        '7d' => 'Last 7 days',
    ];

    protected function getHeaderWidgets(): array
    {
        return [
            Widgets\QueueStatsOverviewWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return config('filament-queue-monitor.metrics.enabled', true)
            ? [Widgets\MetricsChartWidget::class]
            : [];
    }

    public function updatedSelectedPeriod(): void
    {
        $this->dispatch('refreshDashboard');
    }

    public static function canAccess(): bool
    {
        if (! config('filament-queue-monitor.enabled', true)) {
            return false;
        }

        $authorize = config('filament-queue-monitor.authorize');

        if ($authorize instanceof \Closure) {
            return (bool) $authorize(app('auth')->user());
        }

        return true;
    }
}
