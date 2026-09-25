<?php

namespace BlkDem\FilamentQueueMonitor\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use BlkDem\FilamentQueueMonitor\Filament\Widgets;
use BlkDem\FilamentQueueMonitor\Support\Access;
use BlkDem\FilamentQueueMonitor\Support\Trans;

class Dashboard extends Page
{
    public function getView(): string
    {
        return 'filament-queue-monitor::pages.dashboard';
    }

    protected static ?string $slug = 'queue-monitor';

    protected static ?string $title = null;

    public function getTitle(): string
    {
        return Trans::get('dashboard.title');
    }

    public function getSubheading(): string | Htmlable | null
    {
        return Trans::get('dashboard.subtitle');
    }

    public static function getNavigationGroup(): ?string
    {
        return Trans::navigationGroup();
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-chart-bar';
    }

    public static function getNavigationLabel(): string
    {
        return Trans::get('navigation.label');
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

    protected function getHeaderWidgets(): array
    {
        return [
            Widgets\QueueStatsOverviewWidget::class,
            Widgets\QueueCountersWidget::class,
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
                Widgets\JobBreakdownWidget::make(),
            ]
            : [];

        return [...$queueActivityWidgets, ...$historyWidgets];
    }

    public static function canAccess(): bool
    {
        return Access::canAccess();
    }
}