<?php

namespace Kilo\FilamentQueueMonitor\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Kilo\FilamentQueueMonitor\QueueMonitor\QueueMonitorManager;

class QueueStatsOverviewWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $pollingInterval = null;

    public function getColumns(): int
    {
        return 4;
    }

    protected function getPollingInterval(): ?string
    {
        $interval = config('filament-queue-monitor.refresh_interval', 10);

        if ($interval <= 0) {
            return null;
        }

        return "{$interval}s";
    }

    protected function getStats(): array
    {
        $manager = app(QueueMonitorManager::class);

        $allStats = [
            'queues' => 0,
            'pending' => 0,
            'processing' => 0,
            'failed' => 0,
        ];

        foreach ($manager->driver()->getQueues() as $queueInfo) {
            $allStats['queues']++;
            $allStats['pending'] += $queueInfo->pending;
            $allStats['processing'] += $queueInfo->processing;
            $allStats['failed'] += $queueInfo->failed;
        }

        return [
            Stat::make('Queues', $allStats['queues'])
                ->description('Total queues')
                ->icon('heroicon-o-queue-list')
                ->color('gray'),
            Stat::make('Pending', $allStats['pending'])
                ->description($allStats['processing'] . ' processing')
                ->icon('heroicon-o-clock')
                ->color($allStats['pending'] > 0 ? 'warning' : 'success'),
            Stat::make('Processing', $allStats['processing'])
                ->icon('heroicon-o-arrow-path')
                ->color($allStats['processing'] > 0 ? 'info' : 'success'),
            Stat::make('Failed', $allStats['failed'])
                ->icon('heroicon-o-exclamation-triangle')
                ->color($allStats['failed'] > 0 ? 'danger' : 'success'),
        ];
    }
}
