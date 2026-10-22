<?php

namespace BlkDem\FilamentQueueMonitor\QueueMonitor\Listeners;

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
        $enabled = config('filament-queue-monitor.enabled', true);

        if (is_string($enabled)) {
            $enabled = ! in_array(strtolower($enabled), ['0', 'false', 'no', 'off'], true);
        }

        try {
            $this->isEnabled = (bool) $enabled
                && $storage->isEnabled()
                && $storage->tableExists();
        } catch (Throwable $e) {
            // The listener is built for every queue event, JobProcessing
            // included, which fires before the job runs. An exception there
            // released or failed the job without ever running it.
            report($e);
            $this->isEnabled = false;
        }
    }

    public function handleProcessing(JobProcessing $event): void
    {
        if (! $this->shouldRecord($event->connectionName)) {
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
        if (! $this->shouldRecord($event->connectionName)) {
            return;
        }

        $runtime = $this->takeRuntime($event->job);

        $this->safely(function () use ($event, $runtime): void {
            $this->storage->record(
                $event->connectionName,
                $event->job->getQueue(),
                now()->startOfMinute()->format('Y-m-d H:i:s'),
                processed: 1,
                avgRuntime: $runtime,
                maxRuntime: $runtime,
                job: $this->getJobName($event->job),
            );
        });

        // The aggregate above cannot be split back into individual runs, so the
        // per-run record is written separately while the job is still in hand.
        $this->safely(function () use ($event, $runtime): void {
            $this->completed->record(
                $event->connectionName,
                $event->job->getQueue(),
                $this->getJobName($event->job),
                $this->getJobUuid($event->job),
                $runtime,
                null,
                $this->getJobPayload($event->job),
            );
        });
    }

    public function handleFailed(JobFailed $event): void
    {
        if (! $this->shouldRecord($event->connectionName)) {
            return;
        }

        $runtime = $this->takeRuntime($event->job);

        $this->safely(function () use ($event, $runtime): void {
            $this->storage->record(
                $event->connectionName,
                $event->job->getQueue(),
                now()->startOfMinute()->format('Y-m-d H:i:s'),
                failed: 1,
                avgRuntime: $runtime,
                maxRuntime: $runtime,
                job: $this->getJobName($event->job),
            );
        });
    }

    /**
     * A sync job runs inside the request that dispatched it, so recording it
     * added inserts to web requests for a connection the monitor never shows.
     */
    protected function shouldRecord(?string $connectionName): bool
    {
        return $this->isEnabled && $connectionName !== 'sync';
    }

    protected function takeRuntime(object $job): ?float
    {
        $startedAt = self::$startedAt[$job] ?? null;
        unset(self::$startedAt[$job]);

        return $startedAt !== null ? (float) (microtime(true) - $startedAt) : null;
    }

    /**
     * The worker raises JobProcessed inside the same try block as the job, so
     * an exception here was handled as the job's own: JobExceptionOccurred
     * fired for a job that had succeeded, listeners registered after this one
     * never ran, and a lost database connection stopped the worker. Monitoring
     * must never change how a job is seen to end, so a failed write is
     * reported and dropped.
     */
    protected function safely(callable $write): void
    {
        try {
            $write();
        } catch (Throwable $e) {
            try {
                report($e);
            } catch (Throwable) {
                // Nothing left to do without risking the job.
            }
        }
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
