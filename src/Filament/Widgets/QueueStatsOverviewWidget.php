<?php

namespace BlkDem\FilamentQueueMonitor\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ListJobs;
use BlkDem\FilamentQueueMonitor\Filament\Pages\Queues\ListQueues;
use BlkDem\FilamentQueueMonitor\QueueMonitor\QueueMonitorManager;
use BlkDem\FilamentQueueMonitor\QueueMonitor\DTO\QueueInfo;
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

        $driverQueues = $driver->getQueues();

        $allStats = [
            'queues' => 0,
            'pending' => 0,
            'processing' => 0,
            'failed' => 0,
        ];

        $activeQueuesCount = 0;

        foreach ($driverQueues as $queueInfo) {
            $allStats['queues']++;
            $allStats['pending'] += $queueInfo->pending;
            $allStats['processing'] += $queueInfo->processing;
            $allStats['failed'] += $queueInfo->failed;

            if ($this->queueHasWork($queueInfo)) {
                $activeQueuesCount++;
            }
        }

        // A queue is active when it holds work right now. Counting the queues
        // the driver merely knows about made the headline number drift with
        // the allowlist instead of with the load, and left the description
        // showing the same figure as the headline.
        $knownQueues = $this->knownQueueNames($driverQueues);
        $totalQueuesCount = count($knownQueues);
        $inactiveQueuesCount = max(0, $totalQueuesCount - $activeQueuesCount);

        $thresholdHours = config('filament-queue-monitor.stuck_jobs.threshold_hours', 12);
        $stuckCount = $driver->stuckJobsCount($thresholdHours);

        return [
            Stat::make(Trans::get('stats.queues'), $activeQueuesCount)
                ->description(Trans::get('stats.description.queues_breakdown', [
                    'total' => $totalQueuesCount,
                    'inactive' => $inactiveQueuesCount,
                ]))
                ->icon('heroicon-o-queue-list')
                ->color($activeQueuesCount > 0 ? 'gray' : 'success')
                ->url(ListQueues::getUrl()),
            Stat::make(Trans::get('stats.pending'), $allStats['pending'])
                ->description(Trans::get('stats.description.processing_count', ['count' => $allStats['processing']]))
                ->icon('heroicon-o-clock')
                ->color($allStats['pending'] > 0 ? 'warning' : 'success')
                ->url(ListJobs::getUrl(['status' => 'pending'])),
            Stat::make(Trans::get('stats.processing'), $allStats['processing'])
                ->icon('heroicon-o-arrow-path')
                ->color($allStats['processing'] > 0 ? 'info' : 'success')
                ->url(ListJobs::getUrl(['status' => 'processing'])),
            // A stuck job is a reserved one that has been held past the
            // threshold, so the processing view is where they can be seen.
            Stat::make(Trans::get('stats.stuck_jobs'), $stuckCount)
                ->description(Trans::get('stats.description.stuck_jobs', ['hours' => $thresholdHours]))
                ->icon('heroicon-o-exclamation-triangle')
                ->color($stuckCount > 0 ? 'danger' : 'success')
                ->url(ListJobs::getUrl(['status' => 'processing'])),
        ];
    }

    /**
     * Anything waiting, running, scheduled for later or holding a failure
     * counts as work. A queue the driver can name but that holds none of these
     * is idle, which is what the headline number leaves out.
     */
    protected function queueHasWork(QueueInfo $queueInfo): bool
    {
        return $queueInfo->pending > 0
            || $queueInfo->processing > 0
            || $queueInfo->delayed > 0
            || $queueInfo->failed > 0;
    }

    /**
     * Every queue the package can account for: the ones declared in the
     * connection config, the ones listed in the redis allowlist, and the ones
     * the driver found. The union is what "total" means, so a queue that has
     * gone quiet still shows up as inactive instead of disappearing.
     *
     * @param  iterable<QueueInfo>  $discovered
     * @return list<string>
     */
    protected function knownQueueNames(iterable $discovered): array
    {
        $names = [];

        $add = function (mixed $name) use (&$names): void {
            if (is_string($name) && trim($name) !== '') {
                $names[trim($name)] = true;
            }
        };

        $connection = config('queue.default', 'database');

        foreach ((array) config("queue.connections.{$connection}.queue", 'default') as $name) {
            $add($name);
        }

        $allowlist = config('filament-queue-monitor.redis.queues', []);

        if (is_string($allowlist)) {
            $allowlist = explode(',', $allowlist);
        }

        foreach ((array) $allowlist as $name) {
            $add($name);
        }

        foreach ($discovered as $queueInfo) {
            $add($queueInfo->name);
        }

        return array_keys($names);
    }
}