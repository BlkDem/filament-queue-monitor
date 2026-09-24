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
        return 'Failed Jobs';
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
                    ->label('Job')
                    ->options(fn (): array => collect($this->resolveAllRecords())
                        ->pluck('job', 'job')
                        ->reject(fn (?string $job): bool => blank($job))
                        ->mapWithKeys(fn (string $job): array => [$job => (string) Str::afterLast($job, '\\')])
                        ->sort()
                        ->toArray()),
                SelectFilter::make('queue')
                    ->label('Queue')
                    ->options(fn (): array => collect($this->resolveAllRecords())
                        ->pluck('queue', 'queue')
                        ->reject(fn (?string $queue): bool => blank($queue))
                        ->sort()
                        ->toArray()),
                Filter::make('failedAt')
                    ->label('Failed At')
                    ->form([
                        DateTimePicker::make('from')
                            ->label('From')
                            ->native(false),
                        DateTimePicker::make('until')
                            ->label('Until')
                            ->native(false),
                    ])
                    ->query(function () {}),
            ])
            ->columns([
                TextColumn::make('job')
                    ->label('Job')
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
                    ->label('Queue')
                    ->badge()
                    ->sortable(),
                TextColumn::make('payload')
                    ->label('Payload')
                    ->wrap()
                    ->limit(100)
                    ->placeholder('—')
                    ->copyable(),
                TextColumn::make('exception')
                    ->label('Exception')
                    ->searchable()
                    ->wrap()
                    ->limit(100),
                TextColumn::make('failedAt')
                    ->label('Failed At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                $actionClass::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Retry Failed Job')
                    ->modalDescription('Are you sure you want to retry this failed job?')
                    ->action(function ($record) {
                        $this->getDriver()->retryFailedJob($record->id);

                        Notification::make()
                            ->title('Job Retried')
                            ->body("Failed job #{$record->id} has been retried.")
                            ->success()
                            ->send();
                    }),
                $actionClass::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete Failed Job')
                    ->modalDescription('Are you sure you want to delete this failed job? This action cannot be undone.')
                    ->modalSubmitActionLabel('Delete')
                    ->action(function ($record) {
                        $this->getDriver()->deleteFailedJob($record->id);

                        Notification::make()
                            ->title('Job Deleted')
                            ->body("Failed job #{$record->id} has been deleted.")
                            ->success()
                            ->send();
                    }),
            ])
            ->searchPlaceholder('Search failed jobs...')
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
