<?php

namespace Kilo\FilamentQueueMonitor\Filament\Pages\FailedJobs;

use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Kilo\FilamentQueueMonitor\Filament\Pages\BaseQueueTablePage;
use Kilo\FilamentQueueMonitor\QueueMonitor\DTO\FailedJobInfo;

class ListFailedJobs extends BaseQueueTablePage
{
    protected static string $view = 'filament-queue-monitor::pages.list-failed-jobs';

    protected static ?string $navigationLabel = 'Failed Jobs';

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'Queue Monitor';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'queue-monitor/failed-jobs';

    protected function getSearchableFields(): array
    {
        return ['job', 'queue', 'exception'];
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

    protected function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->poll($this->getTablePollingInterval())
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('uuid')
                    ->label('UUID')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('job')
                    ->label('Job')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('queue')
                    ->label('Queue')
                    ->badge()
                    ->sortable(),
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
                Action::make('retry')
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
                Action::make('delete')
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
