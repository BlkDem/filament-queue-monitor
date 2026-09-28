<?php

namespace BlkDem\FilamentQueueMonitor\QueueMonitor\Listeners;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\Job;
use Throwable;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Statistics\CompletedJobsStorage;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Statistics\MetricsStorage;

class RecordQueueMetrics
{
    /**
     * Start time per job object. Static because the container hands out a new
     * listener per event, and a WeakMap so a job that is never finished does
     * not leave anything behind.
     *
     * @var \WeakMap<object, float>
     */
    protected static \WeakMap $startedAt;

    protected MetricsStorage $storage;

    protected CompletedJobsStorage $completed;

    protected bool $isEnabled;

    public function __construct(MetricsStorage $storage, ?CompletedJobsStorage $completed = null)
    {
        self::$startedAt ??= new \WeakMap();

        $this->storage = $storage;
        $this->completed = $completed ?? app(CompletedJobsStorage::class);
        $this->isEnabled = (bool) config('filament-queue-monitor.enabled', true)
            && $storage->isEnabled()
            && $storage->tableExists();
    }

    public function handleProcessing(JobProcessing $event): void
    {
        if (! $this->isEnabled) {
            return;
        }

        // Held against the job object itself rather than in the cache. The
        // cache cost the host application three queries per job whenever
        // CACHE_DRIVER=database, and a WeakMap entry disappears with the job
        // instead of lingering for a retry_after window.
        self::$startedAt[$event->job] = microtime(true);
    }

    public function handleProcessed(JobProcessed $event): void
    {
        if (! $this->isEnabled) {
            return;
        }

        $startedAt = self::$startedAt[$event->job] ?? null;
        unset(self::$startedAt[$event->job]);

        $runtime = $startedAt !== null ? (float) (microtime(true) - $startedAt) : null;

        $period = now()->startOfMinute()->format('Y-m-d H:i:s');

        $this->storage->record(
            $event->connectionName,
            $event->job->getQueue(),
            $period,
            processed: 1,
            avgRuntime: $runtime,
            maxRuntime: $runtime,
            job: $this->getJobName($event->job),
        );

        // The aggregate above cannot be split back into individual runs, so the
        // per-run record is written separately while the job is still in hand.
        $this->completed->record(
            $event->connectionName,
            $event->job->getQueue(),
            $this->getJobName($event->job),
            $this->getJobUuid($event->job),
            $runtime,
            null,
            $this->getJobPayload($event->job),
        );
    }

    public function handleFailed(JobFailed $event): void
    {
        if (! $this->isEnabled) {
            return;
        }

        $startedAt = self::$startedAt[$event->job] ?? null;
        unset(self::$startedAt[$event->job]);

        $runtime = $startedAt !== null ? (float) (microtime(true) - $startedAt) : null;

        $period = now()->startOfMinute()->format('Y-m-d H:i:s');

        $this->storage->record(
            $event->connectionName,
            $event->job->getQueue(),
            $period,
            failed: 1,
            avgRuntime: $runtime,
            maxRuntime: $runtime,
            job: $this->getJobName($event->job),
        );
    }

    public function handleExceptionOccurred(JobExceptionOccurred $event): void
    {
        // JobExceptionOccurred is fired during processing - the job may be retried.
        // We don't count this as a separate failure here; JobFailed handles that.
    }

    /**
     * The raw payload, kept so the completed run can still be inspected. It
     * holds the serialized command, which is why the column is longText and
     * why the same data Laravel keeps in failed_jobs.
     *
     * Job::payload() is not used here: it decodes to an array, and the raw
     * body is what has to be stored verbatim.
     */
    protected function getJobPayload(Job $job): ?string
    {
        try {
            $payload = $job->getRawBody();
        } catch (Throwable) {
            return null;
        }

        return is_string($payload) && $payload !== '' ? $payload : null;
    }

    protected function getJobUuid(Job $job): ?string
    {
        try {
            $uuid = $job->uuid();
        } catch (Throwable) {
            return null;
        }

        return is_string($uuid) && $uuid !== '' ? $uuid : null;
    }

    protected function getJobName(Job $job): string
    {
        try {
            $name = $job->resolveName();
        } catch (Throwable) {
            $name = null;
        }

        if (! is_string($name) || $name === '') {
            try {
                $payload = $job->payload();
                $name = $payload['displayName'] ?? null;
            } catch (Throwable) {
                $name = null;
            }
        }

        return is_string($name) && $name !== '' ? $name : 'unknown';
    }
}
