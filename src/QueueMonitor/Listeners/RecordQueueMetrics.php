<?php

namespace Kilo\FilamentQueueMonitor\QueueMonitor\Listeners;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Cache;
use Kilo\FilamentQueueMonitor\QueueMonitor\Statistics\MetricsStorage;

class RecordQueueMetrics
{
    protected MetricsStorage $storage;

    protected bool $isEnabled;

    public function __construct(MetricsStorage $storage)
    {
        $this->storage = $storage;
        $this->isEnabled = $storage->isEnabled() && $storage->tableExists();
    }

    public function handleProcessing(JobProcessing $event): void
    {
        if (! $this->isEnabled) {
            return;
        }

        $key = $this->getStartKey($event->connectionName, $event->job->getQueue());
        Cache::put($key, microtime(true), 3600);
    }

    public function handleProcessed(JobProcessed $event): void
    {
        if (! $this->isEnabled) {
            return;
        }

        $startKey = $this->getStartKey($event->connectionName, $event->job->getQueue());
        $startedAt = Cache::pull($startKey);
        $runtime = $startedAt !== null ? (float) (microtime(true) - $startedAt) : null;

        $period = now()->startOfMinute()->format('Y-m-d H:i:s');

        $this->storage->record(
            $event->connectionName,
            $event->job->getQueue(),
            $period,
            processed: 1,
            avgRuntime: $runtime,
            maxRuntime: $runtime,
        );
    }

    public function handleFailed(JobFailed $event): void
    {
        if (! $this->isEnabled) {
            return;
        }

        $startKey = $this->getStartKey($event->connectionName, $event->job->getQueue());
        $startedAt = Cache::pull($startKey);
        $runtime = $startedAt !== null ? (float) (microtime(true) - $startedAt) : null;

        $period = now()->startOfMinute()->format('Y-m-d H:i:s');

        $this->storage->record(
            $event->connectionName,
            $event->job->getQueue(),
            $period,
            failed: 1,
            avgRuntime: $runtime,
            maxRuntime: $runtime,
        );
    }

    public function handleExceptionOccurred(JobExceptionOccurred $event): void
    {
        // JobExceptionOccurred is fired during processing - the job may be retried.
        // We don't count this as a separate failure here; JobFailed handles that.
    }

    protected function getStartKey(string $connectionName, string $queue): string
    {
        return "queue-monitor:start:{$connectionName}:{$queue}";
    }
}
