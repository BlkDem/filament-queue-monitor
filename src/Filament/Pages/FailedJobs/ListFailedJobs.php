<?php

namespace BlkDem\FilamentQueueMonitor\Filament\Pages\FailedJobs;

use Filament\Notifications\Notification;
use Filament\Forms\Components\DateTimePicker;
use BlkDem\FilamentQueueMonitor\Support\Version;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use BlkDem\FilamentQueueMonitor\Filament\Pages\BaseQueueTablePage;
use BlkDem\FilamentQueueMonitor\Filament\Pages\FailedJobs\ViewFailedJob;
use BlkDem\FilamentQueueMonitor\QueueMonitor\DTO\FailedJobInfo;
use BlkDem\FilamentQueueMonitor\Support\Trans;

class ListFailedJobs extends BaseQueueTablePage
{
    public function getView(): string
    {
        return 'filament-queue-monitor::pages.list-failed-jobs';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-exclamation-triangle';
    }

    public static function getNavigationLabel(): string
    {
        return Trans::get('navigation.failed_jobs');
    }

    public static function getNavigationSort(): ?int
    {
        return 30;
    }

    protected static ?string $slug = 'queue-monitor/failed-jobs';

    protected function getSearchableFields(): array
    {
        return ['id', 'uuid', 'payload', 'exception'];
    }

    protected function resolveAllRecords(): array
    {
        $driver = $this->getDriver();
        $jobs = $driver->failedJobs();

        return array_map(function (FailedJobInfo $job): array {
            return [
                'id' => (string) ($job->id ?? ''),
                'uuid' => $job->uuid ?? '',
                'job' => $job->resolveJobName() ?? '',
                'queue' => $job->queue,
                'connection' => $job->connection,
                'exception' => $job->exception,
                'failedAt' => $job->failedAt?->toDateTimeString() ?? now()->toDateTimeString(),
                'payload' => $job->payload,
            ];
        }, $jobs);
    }

    public function table(Table $table): Table
    {
        $actionClass = Version::getActionClass();

        return $table
            ->query($this->getTableQuery())
            ->poll($this->getTablePollingInterval())
            ->filters([
                SelectFilter::make('job')
                    ->label(Trans::get('common.job'))
                    ->options(fn (): array => collect($this->resolveAllRecords())
                        ->pluck('job', 'job')
                        ->reject(fn (?string $job): bool => blank($job))
                        ->mapWithKeys(fn (string $job): array => [$job => (string) Str::afterLast($job, '\\')])
                        ->sort()
                        ->toArray()),
                SelectFilter::make('queue')
                    ->label(Trans::get('common.queue'))
                    ->options(fn (): array => collect($this->resolveAllRecords())
                        ->pluck('queue', 'queue')
                        ->reject(fn (?string $queue): bool => blank($queue))
                        ->sort()
                        ->toArray()),
                Filter::make('failedAt')
                    ->label(Trans::get('filters.failed_at'))
                    ->form([
                        DateTimePicker::make('from')
                            ->label(Trans::get('filters.from_failed_at'))
                            ->native(false),
                        DateTimePicker::make('until')
                            ->label(Trans::get('filters.until_failed_at'))
                            ->native(false),
                    ])
                    ->query(function () {}),
            ])
            ->columns([
                TextColumn::make('job')
                    ->label(Trans::get('common.job'))
                    ->url(fn ($record): ?string => ViewFailedJob::getUrl(['id' => (string) $record->id]))
                    ->formatStateUsing(fn (string $state) => Str::afterLast($state, '\\'))
                    ->tooltip(fn ($record): string => (string) $record->job)
                    ->description(fn ($record): string => '#'.$record->id
                        .(filled($record->uuid) ? ' · '.$record->uuid : ''))
                    ->extraAttributes(['style' => 'min-width: 24rem;'])
                    ->wrap()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('queue')
                    ->label(Trans::get('common.queue'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('payload')
                    ->label(Trans::get('failed_jobs.payload'))
                    ->wrap()
                    ->limit(100)
                    ->placeholder(Trans::get('common.empty_value'))
                    ->copyable(),
                TextColumn::make('exception')
                    ->label(Trans::get('failed_jobs.exception'))
                    ->searchable()
                    ->wrap()
                    ->limit(100),
                TextColumn::make('failedAt')
                    ->label(Trans::get('failed_jobs.failed_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                $actionClass::make('retry')
                    ->label(Trans::get('actions.retry'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading(Trans::get('actions.retry_heading'))
                    ->modalDescription(Trans::get('actions.retry_description'))
                    ->action(function ($record) {
                        $this->getDriver()->retryFailedJob($record->id);

                        Notification::make()
                            ->title(Trans::get('actions.retried_title'))
                            ->body(Trans::get('actions.retried_body', ['id' => $record->id]))
                            ->success()
                            ->send();
                    }),
                $actionClass::make('delete')
                    ->label(Trans::get('actions.delete'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(Trans::get('actions.delete_heading'))
                    ->modalDescription(Trans::get('actions.delete_description'))
                    ->modalSubmitActionLabel(Trans::get('actions.delete'))
                    ->action(function ($record) {
                        $this->getDriver()->deleteFailedJob($record->id);

                        Notification::make()
                            ->title(Trans::get('actions.deleted_title'))
                            ->body(Trans::get('actions.deleted_body', ['id' => $record->id]))
                            ->success()
                            ->send();
                    }),
            ])
            ->searchPlaceholder(Trans::get('failed_jobs.search_placeholder'))
            ->emptyStateHeading(Trans::get('failed_jobs.title'))
            ->emptyStateDescription(Trans::get('failed_jobs.empty'))
            ->defaultSort('failedAt', 'desc')
            ->paginated([10, 25, 50]);

        if (Version::isFilament4()) {
            $table->defaultKeySort(false);
        }

        return $table;
    }

    protected function applyFilterToTableRecords(Collection $records, string $field, array $filter): ?Collection
    {
        if ($field !== 'failedAt') {
            return parent::applyFilterToTableRecords($records, $field, $filter);
        }

        $from = $this->parseFilterDateTime($filter['from'] ?? null, isFrom: true);
        $until = $this->parseFilterDateTime($filter['until'] ?? null, isFrom: false);

        if ($from === null && $until === null) {
            return null;
        }

        return $records->filter(function ($record) use ($from, $until): bool {
            $failedAt = Carbon::parse((string) $this->getFieldValue($record, 'failedAt'));

            if ($from !== null && $failedAt->lt($from)) {
                return false;
            }

            if ($until !== null && $failedAt->gt($until)) {
                return false;
            }

            return true;
        })->values();
    }

    protected function parseFilterDateTime(mixed $date, bool $isFrom): ?Carbon
    {
        if (blank($date)) {
            return null;
        }

        try {
            $parsed = Carbon::parse($date);

            if ($parsed->format('H:i:s') === '00:00:00') {
                return $isFrom ? $parsed->startOfDay() : $parsed->endOfDay();
            }

            return $parsed;
        } catch (\Exception) {
            return null;
        }
    }

    protected function getSortField(string $column): string
    {
        return match ($column) {
            'id' => 'id',
            'uuid' => 'uuid',
            'job' => 'job',
            'queue' => 'queue',
            'failedAt' => 'failedAt',
            default => $column,
        };
    }
}
