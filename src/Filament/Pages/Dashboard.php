<?php

namespace Kilo\FilamentQueueMonitor\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Kilo\FilamentQueueMonitor\Filament\Widgets;
use Kilo\FilamentQueueMonitor\Support\Access;

class Dashboard extends Page
{
    public function getView(): string
    {
        return 'filament-queue-monitor::pages.dashboard';
    }

    protected static ?string $title = 'Queue Monitor';

    public function getSubheading(): string | Htmlable | null
    {
        return 'Live view of your queues and recent job activity.';
    }

    public static function getNavigationGroup(): ?string
    {
        $group = config('filament-queue-monitor.navigation.group', 'Queue Monitor');

        return is_string($group) && $group !== '' ? $group : 'Queue Monitor';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-chart-bar';
    }

    public static function getNavigationLabel(): string
    {
        return 'Queue Monitor';
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-queue-monitor.navigation.sort', 0);

        return is_numeric($sort) ? (int) $sort : 0;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-queue-monitor.navigation.enabled', true);
    }

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
        $queueActivityWidgets = (bool) config('filament-queue-monitor.enabled', true)
            ? [Widgets\QueueActivityWidget::make()]
            : [];

        $historyWidgets = (bool) config('filament-queue-monitor.enabled', true)
            && (bool) config('filament-queue-monitor.metrics.enabled', true)
            ? [
                Widgets\JobBreakdownWidget::make([
                    'selectedPeriod' => $this->selectedPeriod,
                ]),
            ]
            : [];

        return [...$queueActivityWidgets, ...$historyWidgets];
    }

    public function updatedSelectedPeriod(): void
    {
        $this->dispatch('refreshDashboard', period: $this->selectedPeriod);
    }

    public static function canAccess(): bool
    {
        return Access::canAccess();
    }
}