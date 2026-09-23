<?php

namespace Kilo\FilamentQueueMonitor\Filament\Pages\Queues;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Kilo\FilamentQueueMonitor\Filament\Pages\BaseQueueTablePage;
use Kilo\FilamentQueueMonitor\QueueMonitor\DTO\QueueInfo;
use Kilo\FilamentQueueMonitor\Support\Version;

class ListQueues extends BaseQueueTablePage
{
    public function getView(): string
    {
        return 'filament-queue-monitor::pages.list-queues';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-queue-list';
    }

    public static function getNavigationLabel(): string
    {
        return 'Queues';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    protected static ?string $slug = 'queue-monitor/queues';

    public ?string $timeFilter = 'all';

    protected function getSearchableFields(): array
    {
        return ['name'];
    }

    protected function resolveAllRecords(): array
    {
        $queues = $this->getDriver()->getQueues();

        return array_map(function (QueueInfo $info): array {
            return [
                'id' => $info->name,
                'name' => $info->name,
                'pending' => $info->pending,
                'processing' => $info->processing,
                'delayed' => $info->delayed,
                'failed' => $info->failed,
                'total' => $info->pending + $info->processing + $info->delayed,
                'lastActivityAt' => $info->lastActivityAt?->toDateTimeString(),
            ];
        }, $queues);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->poll($this->getTablePollingInterval())
            ->columns([
                TextColumn::make('name')
                    ->label('Queue')
                    ->searchable()
                    ->sortable()
                    ->weight('font-medium'),
                TextColumn::make('pending')
                    ->label('Pending')
                    ->sortable(),
                TextColumn::make('processing')
                    ->label('Processing')
                    ->sortable()
                    ->color('info'),
                TextColumn::make('delayed')
                    ->label('Delayed')
                    ->sortable()
                    ->color('warning'),
                TextColumn::make('failed')
                    ->label('Failed')
                    ->sortable()
                    ->color('danger'),
                TextColumn::make('total')
                    ->label('Total')
                    ->sortable(),
                TextColumn::make('lastActivityAt')
                    ->label('Last Activity')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordUrl(fn ($record) => is_array($record) ? null : ViewQueue::getUrl(['queue' => $record->name]))
            ->searchPlaceholder('Search queues...')
            ->defaultSort('name', 'asc')
            ->paginated([10, 25, 50]);

        if (Version::isFilament4()) {
            $table->defaultKeySort(false);
        }

        return $table;
    }
}
