<?php

namespace Kilo\FilamentQueueMonitor\Filament\Pages\Queues;

use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Pages\Concerns\InteractsWithForms as InteractsWithFormsTrait;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Kilo\FilamentQueueMonitor\Filament\Pages\BaseQueueTablePage;
use Kilo\FilamentQueueMonitor\QueueMonitor\DTO\QueueInfo;

class ListQueues extends BaseQueueTablePage
{
    use InteractsWithFormsTrait;

    protected static string $view = 'filament-queue-monitor::pages.list-queues';

    protected static ?string $navigationLabel = 'Queues';

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'Queue Monitor';

    protected static ?int $navigationSort = 10;

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

    protected function table(Table $table): Table
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
            ->recordUrl(fn ($record) => ViewQueue::getUrl(['queue' => $record->name]))
            ->searchPlaceholder('Search queues...')
            ->defaultSort('name', 'asc')
            ->paginated([10, 25, 50]);
    }

    public function getViewData(): array
    {
        return array_merge(parent::getViewData(), [
            'queues' => $this->getDriver()->getQueues(),
        ]);
    }
}
