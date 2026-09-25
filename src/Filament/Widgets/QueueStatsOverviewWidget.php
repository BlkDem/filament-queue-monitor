<?php

namespace BlkDem\FilamentQueueMonitor\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use BlkDem\FilamentQueueMonitor\QueueMonitor\QueueMonitorManager;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Models\QueueJob;

class QueueStatsOverviewWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    public ?string $pollingOverride = null;

    protected $listeners = ['queueActivityPollingIntervalChanged' => 'setPollingInterval'];

    public function setPollingInterval(string $interval): void
    {
        $this->pollingOverride = $interval;
    }

    public function getColumns(): int
    {
        return 4;
    }

    protected function getPollingInterval(): ?string
    {
        if ($this->pollingOverride === 'off') {
            return null;
        }

        if (filled($this->pollingOverride)) {
            return $this->pollingOverride;
        }

        $interval = config('filament-queue-monitor.refresh_interval', 10);

        if ($interval <= 0) {
            return null;
        }

        return "{$interval}s";
    }

    protected function getStats(): array
    {
        $manager = app(QueueMonitorManager::class);
        $driver = $manager->driver();

        $allStats = [
            'queues' => 0,
            'pending' => 0,
            'processing' => 0,
            'failed' => 0,
        ];

        foreach ($driver->getQueues() as $queueInfo) {
            $allStats['queues']++;
            $allStats['pending'] += $queueInfo->pending;
            $allStats['processing'] += $queueInfo->processing;
            $allStats['failed'] += $queueInfo->failed;
        }

        // Get queue count from the same source as QueueActivityWidget (active jobs query)
        // This ensures the queue count matches the queues shown in the activity table
        $activeQueuesCount = QueueJob::query()
            ->where(function ($query) {
                $query->whereNull('reserved_at')
                    ->where('available_at', '<=', now()->timestamp)
                    ->orWhereNotNull('reserved_at');
            })
            ->distinct('queue')
            ->count('queue');

        $thresholdHours = config('filament-queue-monitor.stuck_jobs.threshold_hours', 12);
        $stuckCount = $driver->stuckJobsCount($thresholdHours);

        return [
            Stat::make('Queues', $activeQueuesCount)
                ->description('Active queues: ' . $activeQueuesCount)
                ->icon('heroicon-o-queue-list')
                ->color('gray'),
            Stat::make('Pending', $allStats['pending'])
                ->description($allStats['processing'] . ' processing')
                ->icon('heroicon-o-clock')
                ->color($allStats['pending'] > 0 ? 'warning' : 'success'),
            Stat::make('Processing', $allStats['processing'])
                ->icon('heroicon-o-arrow-path')
                ->color($allStats['processing'] > 0 ? 'info' : 'success'),
            Stat::make('Stuck Jobs', $stuckCount)
                ->description('Stuck > ' . $thresholdHours . 'h (reserved_at)')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($stuckCount > 0 ? 'danger' : 'success'),
        ];
    }
}