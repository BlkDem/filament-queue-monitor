<?php

namespace Kilo\FilamentQueueMonitor\Filament\Pages\Jobs;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Kilo\FilamentQueueMonitor\Filament\Pages\BaseQueueTablePage;
use Kilo\FilamentQueueMonitor\QueueMonitor\DTO\JobInfo;

class ListJobs extends BaseQueueTablePage
{
    protected static string $view = 'filament-queue-monitor::pages.list-jobs';

    protected static ?string $navigationLabel = 'Jobs';

    protected static ?string $navigationIcon = 'heroicon-o-cog';

    protected static ?string $navigationGroup = 'Queue Monitor';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'queue-monitor/jobs';

    public ?string $queue = null;

    protected function getSearchableFields(): array
    {
        return ['job', 'queue'];
    }

    protected function resolveAllRecords(): array
    {
        $driver = $this->getDriver();
        $queues = $driver->getQueues();

        $allRecords = [];
        $queue = $this->queue;

        if ($queue) {
            $jobs = $driver->pendingJobs($queue);
        } else {
            $jobs = [];
            foreach ($queues as $queueInfo) {
                $jobs = array_merge($jobs, $driver->pendingJobs($queueInfo->name));
            }
        }

        foreach ($jobs as $job) {
            $allRecords[] = $this->jobInfoToArray($job);
        }

        return $allRecords;
    }

    protected function jobInfoToArray(JobInfo $job): array
    {
        $payloadData = $job->resolvePayloadData();
        $jobClass = $job->resolveJobClass() ?? $payloadData['displayName'] ?? $payloadData['job'] ?? 'Unknown';

        return [
            'id' => (string) ($job->uuid ?? $job->id ?? ''),
            'uuid' => $job->uuid ?? $job->id ?? '',
            'queue' => $job->queue,
            'job' => $jobClass,
            'attempts' => $job->attempts,
            'createdAt' => $job->createdAt?->toDateTimeString(),
            'availableAt' => $job->availableAt?->toDateTimeString() ?? $job->createdAt?->toDateTimeString(),
            'payload' => $job->payload,
        ];
    }

    protected function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->poll($this->getTablePollingInterval())
            ->columns([
                TextColumn::make('job')
                    ->label('Job Class')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('queue')
                    ->label('Queue')
                    ->badge()
                    ->sortable(),
                TextColumn::make('attempts')
                    ->label('Attempts')
                    ->sortable()
                    ->badge(),
                TextColumn::make('createdAt')
                    ->label('Pushed At')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('availableAt')
                    ->label('Available At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->searchPlaceholder('Search jobs...')
            ->defaultSort('createdAt', 'desc')
            ->paginated([10, 25, 50]);
    }

    protected function getSortField(string $column): string
    {
        if ($column === 'job') {
            return 'job';
        }

        if ($column === 'createdAt') {
            return 'createdAt';
        }

        return $column;
    }

    public function getViewData(): array
    {
        return array_merge(parent::getViewData(), [
            'queues' => $this->getDriver()->getQueues(),
        ]);
    }
}
