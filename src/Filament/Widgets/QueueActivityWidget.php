<?php

namespace Kilo\FilamentQueueMonitor\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Kilo\FilamentQueueMonitor\Filament\Pages\Queues\ViewQueue;
use Kilo\FilamentQueueMonitor\QueueMonitor\Models\QueueJob;

class QueueActivityWidget extends BaseTableWidget
{
    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    public function render(): View
    {
        return view('filament-queue-monitor::widgets.queue-activity', $this->getViewData());
    }

    public function table(Table $table): Table
    {
        $counts = [];

        $this->collapseGroupsByDefault($table);

        return $table
            ->heading('Queue Activity')
            ->description('Current tasks in each queue — the same counts as the stats above')
            ->query(fn (): Builder => $this->activeJobsQuery())
            ->defaultGroup(
                Group::make('queue')
                    ->label('Queue')
                    ->collapsible()
                    ->getTitleFromRecordUsing(function (Model $record) use (&$counts): string {
                        if ($counts === []) {
                            $counts = $this->activeJobsQuery()
                                ->select('queue')
                                ->selectRaw('COUNT(*) as total')
                                ->groupBy('queue')
                                ->pluck('total', 'queue')
                                ->all();
                        }

                        return (string) $record->queue.' ('.($counts[$record->queue] ?? 0).')';
                    }),
            )
            ->groups([
                Group::make('queue')->label('Queue')->collapsible(),
                Group::make('status')->label('Status')->collapsible(),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll($this->getPollingInterval())
            ->columns([
                TextColumn::make('name')
                    ->label('Job')
                    ->formatStateUsing(fn (string $state) => Str::afterLast($state, '\\'))
                    ->tooltip(fn ($record): string => (string) $record->name)
                    ->wrap()
                    ->description(fn ($record): string => (string) $record->name),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'processing' ? 'info' : 'gray')
                    ->tooltip(fn (string $state): string => $state === 'processing'
                        ? 'Reserved by a worker'
                        : 'Queued, ready to run'),
                TextColumn::make('attempts')
                    ->label('Attempts')
                    ->badge()
                    ->color(fn ($state): string => (int) $state > 0 ? 'warning' : 'gray'),
                TextColumn::make('reserved_at')
                    ->label('Running since')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Queued')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordUrl(fn ($record): ?string => ViewQueue::getUrl(['queue' => $record->queue]))
            ->defaultPaginationPageOption(5)
            ->paginated([5, 10, 25, 'all'])
            ->emptyStateHeading('No active tasks')
            ->emptyStateDescription('No pending or processing jobs right now.');
    }

    protected function activeJobsQuery(): Builder
    {
        return QueueJob::query()->where(function (Builder $query): void {
            $query
                ->whereNull('reserved_at')
                ->where('available_at', '<=', now()->timestamp)
                ->orWhereNotNull('reserved_at');
        });
    }

    protected function collapseGroupsByDefault(Table $table): void
    {
        if (method_exists($table, 'collapsedGroupsByDefault')) {
            $table->collapsedGroupsByDefault();
        }
    }

    protected function getPollingInterval(): ?string
    {
        $interval = (int) config('filament-queue-monitor.refresh_interval', 10);

        if ($interval <= 0) {
            return null;
        }

        return "{$interval}s";
    }
}