<?php

namespace BlkDem\FilamentQueueMonitor\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use BlkDem\FilamentQueueMonitor\QueueMonitor\QueueMonitorManager;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Models\QueueJob;
use BlkDem\FilamentQueueMonitor\Support\Trans;

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

        // Get total configured queues from Laravel queue config
        $defaultConnection = config('queue.default', 'database');
        $queueConfig = config("queue.connections.{$defaultConnection}.queue", 'default');
        $totalConfiguredQueues = is_array($queueConfig) ? count($queueConfig) : 1;

        // Get active queues count from the same source as QueueActivityWidget
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
            Stat::make(Trans::get('stats.queues'), $activeQueuesCount)
                ->description(Trans::get('stats.description.total_queues', ['count' => $totalConfiguredQueues]))
                ->icon('heroicon-o-queue-list')
                ->color('gray'),
            Stat::make(Trans::get('stats.pending'), $allStats['pending'])
                ->description(Trans::get('stats.description.processing_count', ['count' => $allStats['processing']]))
                ->icon('heroicon-o-clock')
                ->color($allStats['pending'] > 0 ? 'warning' : 'success'),
            Stat::make(Trans::get('stats.processing'), $allStats['processing'])
                ->icon('heroicon-o-arrow-path')
                ->color($allStats['processing'] > 0 ? 'info' : 'success'),
            Stat::make(Trans::get('stats.stuck_jobs'), $stuckCount)
                ->description(Trans::get('stats.description.stuck_jobs', ['hours' => $thresholdHours]))
                ->icon('heroicon-o-exclamation-triangle')
                ->color($stuckCount > 0 ? 'danger' : 'success'),
        ];
    }
}