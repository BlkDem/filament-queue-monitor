<?php

namespace BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs;

use Filament\Pages\Page;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Models\CompletedJob;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Statistics\CompletedJobsStorage;
use BlkDem\FilamentQueueMonitor\Support\Access;
use BlkDem\FilamentQueueMonitor\Support\Trans;

/**
 * A single completed run: identity, timing and the payload it carried.
 */
class ViewCompletedJobRun extends Page
{
    public string $job = '';

    public string $minute = '';

    public int $id = 0;

    protected ?CompletedJob $cachedRun = null;

    public function mount(string $job, string $minute, int $id): void
    {
        $this->job = ViewCompletedJob::decodeJob($job);
        $this->minute = ListCompletedJobRuns::decodeMinute($minute);
        $this->id = $id;
    }

    public function getView(): string
    {
        return 'filament-queue-monitor::pages.view-completed-job-run';
    }

    protected static ?string $slug = 'queue-monitor/completed-jobs/{job}/runs/{minute}/{id}';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getHeading(): string
    {
        return $this->run()?->uuid ?: Trans::get('completed_jobs.run_heading');
    }

    public function getSubheading(): ?string
    {
        return $this->run()?->job ?? $this->job;
    }

    public function run(): ?CompletedJob
    {
        if ($this->cachedRun !== null) {
            return $this->cachedRun;
        }

        $storage = app(CompletedJobsStorage::class);

        if (! $storage->tableExists()) {
            return null;
        }

        return $this->cachedRun = CompletedJob::query()
            ->where('id', $this->id)
            ->where('connection', $this->currentConnection())
            ->where('job', $this->job)
            ->first();
    }

    public function getViewData(): array
    {
        $run = $this->run();

        return array_merge(parent::getViewData(), [
            'run' => $run,
            'formattedPayload' => $run?->payload ? $this->formatPayload((string) $run->payload) : null,
        ]);
    }

    /**
     * Redis payloads are JSON and can be pretty printed; a database payload is a
     * base64 encoded serialized command, which is shown verbatim.
     */
    protected function formatPayload(string $payload): string
    {
        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            return $payload;
        }

        $encoded = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return is_string($encoded) ? $encoded : $payload;
    }

    public function backUrl(): string
    {
        return ListCompletedJobRuns::getUrl([
            'job' => ViewCompletedJob::encodeJob($this->job),
            'minute' => ListCompletedJobRuns::encodeMinute($this->minute),
        ]);
    }

    protected function currentConnection(): string
    {
        $connection = config('queue.default', 'database');

        return is_string($connection) && $connection !== '' ? $connection : 'database';
    }

    public static function canAccess(): bool
    {
        return Access::canAccess();
    }
}
