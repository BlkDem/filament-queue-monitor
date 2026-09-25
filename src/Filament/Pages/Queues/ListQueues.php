<?php

namespace BlkDem\FilamentQueueMonitor\Filament\Pages\Queues;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use BlkDem\FilamentQueueMonitor\Filament\Pages\BaseQueueTablePage;
use BlkDem\FilamentQueueMonitor\QueueMonitor\DTO\QueueInfo;
use BlkDem\FilamentQueueMonitor\Support\Trans;
use BlkDem\FilamentQueueMonitor\Support\Version;

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
        return Trans::get('navigation.queues');
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    protected static ?string $slug = 'queue-monitor/queues';

    public ?string $timeFilter = 'all';

    protected function getSearchableFields(): array
    {
        return ['queue'];
    }

    protected function resolveAllRecords(): array
    {
        $queues = $this->getDriver()->getQueues();

        return array_map(function (QueueInfo $info): array {
            return [
                'id' => $info->name,
                'queue' => $info->name,
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
                TextColumn::make('queue')
                    ->label(Trans::get('queues.columns.queue'))
                    ->searchable()
                    ->sortable()
                    ->weight('font-medium'),
                TextColumn::make('pending')
                    ->label(Trans::get('queues.columns.pending'))
                    ->sortable(),
                TextColumn::make('processing')
                    ->label(Trans::get('queues.columns.processing'))
                    ->sortable()
                    ->color('info'),
                TextColumn::make('delayed')
                    ->label(Trans::get('queues.columns.delayed'))
                    ->sortable()
                    ->color('warning'),
                TextColumn::make('failed')
                    ->label(Trans::get('queues.columns.failed'))
                    ->sortable()
                    ->color('danger'),
                TextColumn::make('total')
                    ->label(Trans::get('queues.columns.total'))
                    ->sortable(),
                TextColumn::make('lastActivityAt')
                    ->label(Trans::get('queues.columns.last_activity'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordUrl(fn ($record) => is_array($record) ? null : ViewQueue::getUrl(['queue' => $record->queue]))
            ->searchPlaceholder(Trans::get('queues.search_placeholder'))
            ->defaultSort('queue', 'asc')
            ->paginated([10, 25, 50]);

        if (Version::isFilament4()) {
            $table->defaultKeySort(false);
        }

        return $table;
    }
}
