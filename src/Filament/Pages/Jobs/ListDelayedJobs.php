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
use BlkDem\FilamentQueueMonitor\Support\Version;
use Carbon\Carbon;

class ListDelayedJobs extends BaseQueueTablePage
{
    public function getView(): string
    {
        return 'filament-queue-monitor::pages.list-delayed-jobs';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-clock';
    }

    public static function getNavigationLabel(): string
    {
        return 'Delayed Jobs';
    }

    public static function getNavigationSort(): ?int
    {
        return 25;
    }

    protected static ?string $slug = 'queue-monitor/delayed-jobs';

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
            $jobs = $driver->delayedJobs($queue);
        } else {
            $jobs = [];
            foreach ($queues as $queueInfo) {
                $jobs = array_merge($jobs, $driver->delayedJobs($queueInfo->name));
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
                    ->label('Job Class')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('queue')
                    ->label('Queue')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'processing' ? 'info' : 'gray')
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
            ->filters([
                SelectFilter::make('job')
                    ->label('Job')
                    ->options(function () {
                        $driver = $this->getDriver();
                        $queues = $driver->getQueues();
                        $jobs = [];
                        foreach ($queues as $queueInfo) {
                            $jobs = array_merge($jobs, $driver->delayedJobs($queueInfo->name));
                        }
                        $options = [];
                        foreach ($jobs as $job) {
                            $jobClass = $job->resolveJobClass() ?? $job->resolvePayloadData()['displayName'] ?? 'Unknown';
                            $options[$jobClass] = Str::afterLast($jobClass, '\\');
                        }
                        return array_unique($options);
                    })
                    ->searchable(),
                SelectFilter::make('queue')
                    ->label('Queue')
                    ->options(function () {
                        $queues = $this->getDriver()->getQueues();
                        $queues = $queues instanceof Collection ? $queues : collect($queues);

                        return $queues->mapWithKeys(fn ($q) => [$q->name => $q->name])->toArray();
                    })
                    ->searchable(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                    ])
                    ->searchable(),
                Filter::make('createdAt')
                    ->label('Pushed At')
                    ->form([
                        DateTimePicker::make('from')
                            ->label('From')
                            ->native(false),
                        DateTimePicker::make('until')
                            ->label('Until')
                            ->native(false),
                    ]),
                Filter::make('availableAt')
                    ->label('Available At')
                    ->form([
                        DateTimePicker::make('from')
                            ->label('From')
                            ->native(false),
                        DateTimePicker::make('until')
                            ->label('Until')
                            ->native(false),
                    ]),
            ])
            ->searchPlaceholder('Search jobs...')
            ->defaultSort('availableAt', 'asc')
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

        if ($column === 'availableAt') {
            return 'availableAt';
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