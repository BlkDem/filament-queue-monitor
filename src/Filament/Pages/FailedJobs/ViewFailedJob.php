<?php

namespace BlkDem\FilamentQueueMonitor\Filament\Pages\FailedJobs;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use BlkDem\FilamentQueueMonitor\QueueMonitor\DTO\FailedJobInfo;
use BlkDem\FilamentQueueMonitor\QueueMonitor\QueueMonitorManager;
use BlkDem\FilamentQueueMonitor\Support\Access;

class ViewFailedJob extends Page
{
    public function getView(): string
    {
        return 'filament-queue-monitor::pages.view-failed-job';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-exclamation-triangle';
    }

    public static function getNavigationLabel(): string
    {
        return 'Failed Job';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static ?string $slug = 'queue-monitor/failed-jobs/{id}';

    public string $id;

    protected ?FailedJobInfo $cachedJob = null;

    public function mount(string $id): void
    {
        $this->id = $id;
    }

    public function getJob(): FailedJobInfo
    {
        if ($this->cachedJob !== null) {
            return $this->cachedJob;
        }

        abort_unless($job = app(QueueMonitorManager::class)->driver()->findFailedJob($this->id), 404);

        return $this->cachedJob = $job;
    }

    public function getJobName(): string
    {
        return $this->getJob()->resolveJobName() ?? 'Unknown';
    }

    public function getPayloadData(): array
    {
        return $this->getJob()->resolvePayloadData();
    }

    public function getViewData(): array
    {
        return array_merge(parent::getViewData(), [
            'job' => $this->getJob(),
            'jobName' => $this->getJobName(),
            'payloadData' => $this->getPayloadData(),
        ]);
    }

    public static function canAccess(): bool
    {
        return Access::canAccess();
    }
}