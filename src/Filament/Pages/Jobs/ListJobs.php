<?php

namespace BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs;

use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use BlkDem\FilamentQueueMonitor\Filament\Pages\BaseQueueTablePage;
use BlkDem\FilamentQueueMonitor\QueueMonitor\DTO\JobInfo;
use BlkDem\FilamentQueueMonitor\Support\Trans;
use BlkDem\FilamentQueueMonitor\Support\Version;
use Carbon\Carbon;

class ListJobs extends BaseQueueTablePage
{
    public function getView(): string
    {
        return 'filament-queue-monitor::pages.list-jobs';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-cog';
    }

    public static function getNavigationLabel(): string
    {
        return Trans::get('navigation.jobs');
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }

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
        $jobClass = $job->resolveJobClass() ?? $payloadData['displayName'] ?? $payloadData['job'] ?? Trans::get('jobs.unknown');
        $status = $job->reservedAt !== null ? 'processing' : 'pending';

        return [
            'id' => (string) ($job->uuid ?? $job->id ?? ''),
            'uuid' => $job->uuid ?? $job->id ?? '',
            'queue' => $job->queue,
            'job' => $jobClass,
            'attempts' => $job->attempts,
            'createdAt' => $job->createdAt?->toDateTimeString(),
            'availableAt' => $job->availableAt?->toDateTimeString() ?? $job->createdAt?->toDateTimeString(),
            'isDelayed' => $job->availableAt && $job->availableAt->gt(now()),
            'status' => $status,
            'payload' => $job->payload,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->poll($this->getTablePollingInterval())
            ->columns([
                TextColumn::make('job')
                    ->label(Trans::get('jobs.job_class'))
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('queue')
                    ->label(Trans::get('common.queue'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(Trans::get('common.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Trans::status($state))
                    ->color(fn (string $state): string => $state === 'processing' ? 'info' : 'gray')
                    ->sortable(),
                TextColumn::make('attempts')
                    ->label(Trans::get('common.attempts'))
                    ->sortable()
                    ->badge(),
                TextColumn::make('createdAt')
                    ->label(Trans::get('jobs.pushed_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('availableAt')
                    ->label(Trans::get('jobs.available_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('job')
                    ->label(Trans::get('common.job'))
                    ->options(function () {
                        $driver = $this->getDriver();
                        $queues = $driver->getQueues();
                        $jobs = [];
                        foreach ($queues as $queueInfo) {
                            $jobs = array_merge($jobs, $driver->pendingJobs($queueInfo->name));
                        }
                        $options = [];
                        foreach ($jobs as $job) {
                            $jobClass = $job->resolveJobClass() ?? $job->resolvePayloadData()['displayName'] ?? Trans::get('jobs.unknown');
                            $options[$jobClass] = Str::afterLast($jobClass, '\\');
                        }
                        return array_unique($options);
                    })
                    ->searchable(),
                SelectFilter::make('queue')
                    ->label(Trans::get('common.queue'))
                    ->options(function () {
                        $queues = $this->getDriver()->getQueues();
                        $queues = $queues instanceof Collection ? $queues : collect($queues);

                        return $queues->mapWithKeys(fn ($q) => [$q->name => $q->name])->toArray();
                    })
                    ->searchable(),
                SelectFilter::make('status')
                    ->label(Trans::get('common.status'))
                    ->options([
                        'pending' => Trans::get('status.pending'),
                        'processing' => Trans::get('status.processing'),
                    ])
                    ->searchable(),
            ])
            ->searchPlaceholder(Trans::get('jobs.search_placeholder'))
            ->defaultSort('createdAt', 'desc')
            ->paginated([10, 25, 50]);

        if (Version::isFilament4()) {
            $table->defaultKeySort(false);
        }

        return $table;
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

    protected function applyFilterToTableRecords(Collection $records, string $field, array $filter): ?Collection
    {
        if (! in_array($field, ['createdAt', 'availableAt'])) {
            return parent::applyFilterToTableRecords($records, $field, $filter);
        }

        $value = $filter['value'] ?? [];

        $from = $value['from'] ?? null;
        $until = $value['until'] ?? null;

        if (blank($from) && blank($until)) {
            return $records;
        }

        return $records->filter(function ($record) use ($field, $from, $until) {
            $dateStr = $record[$field] ?? null;

            if (blank($dateStr)) {
                return false;
            }

            $date = Carbon::parse($dateStr);

            if ($from !== null) {
                $fromDate = Carbon::parse($from);
                if ($date->lt($fromDate)) {
                    return false;
                }
            }

            if ($until !== null) {
                $untilDate = Carbon::parse($until);
                if ($date->gt($untilDate)) {
                    return false;
                }
            }

            return true;
        });
    }
}
