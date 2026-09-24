<?php

namespace BlkDem\FilamentQueueMonitor\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use BlkDem\FilamentQueueMonitor\Filament\Pages\Queues\ViewQueue;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Models\QueueJob;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;

class QueueActivityWidget extends BaseTableWidget
{
    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    public ?string $pollingInterval = null;

    public function boot(): void
    {
        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_GROUPING_SELECTOR_AFTER,
            fn (): string => view('filament-queue-monitor::widgets.partials.polling-controls', [
                'defaultInterval' => (int) config('filament-queue-monitor.refresh_interval', 10),
            ])->render(),
            scopes: static::class,
        );
    }

    public function updatedPollingInterval(): void
    {
        $this->dispatch('queueActivityPollingIntervalChanged', interval: $this->pollingInterval);
    }

    public function render(): View
    {
        return view('filament-queue-monitor::widgets.queue-activity', $this->getViewData());
    }

    public function table(Table $table): Table
    {
        $counts = [];

        $groupCounts = function (string $column) use (&$counts): array {
            if (! array_key_exists($column, $counts)) {
                if ($column === 'status') {
                    $counts[$column] = $this->applyActiveFilters($this->activeJobsQuery())
                        ->selectRaw("(CASE WHEN reserved_at IS NOT NULL THEN 'processing' ELSE 'pending' END) AS status")
                        ->selectRaw('COUNT(*) AS total')
                        ->groupByRaw("(CASE WHEN reserved_at IS NOT NULL THEN 'processing' ELSE 'pending' END)")
                        ->pluck('total', 'status')
                        ->all();
                } else {
                    $counts[$column] = $this->applyActiveFilters($this->activeJobsQuery())
                        ->select($column)
                        ->selectRaw('COUNT(*) AS total')
                        ->groupBy($column)
                        ->pluck('total', $column)
                        ->all();
                }
            }

            return $counts[$column];
        };

        $queueGroup = Group::make('queue')
            ->label('Queue')
            ->collapsible()
            ->getTitleFromRecordUsing(function (Model $record) use ($groupCounts): string {
                $value = (string) $record->queue;

                return $value.' ('.($groupCounts('queue')[$value] ?? 0).')';
            });

        $statusGroup = Group::make('status')
            ->label('Status')
            ->collapsible()
            ->orderQueryUsing(function (Builder $query, string $direction): Builder {
                $query->orderByRaw("(CASE WHEN reserved_at IS NOT NULL THEN 'processing' ELSE 'pending' END) {$direction}");

                return $query;
            })
            ->getTitleFromRecordUsing(function (Model $record) use ($groupCounts): string {
                $value = (string) $record->status;

                return $value.' ('.($groupCounts('status')[$value] ?? 0).')';
            });

        $this->collapseGroupsByDefault($table);

        return $table
            ->heading('Queue Activity')
            ->description('Current tasks in each queue — the same counts as the stats above')
            ->query(fn (): Builder => $this->boundedActiveJobsQuery())
            ->filters([
                SelectFilter::make('queue')
                    ->label('Queue')
                    ->options(fn (): array => $this->activeJobsQuery()
                        ->distinct()
                        ->orderBy('queue')
                        ->pluck('queue', 'queue')
                        ->all()),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'pending',
                        'processing' => 'processing',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        return match ($value) {
                            'processing' => $query->whereNotNull('reserved_at'),
                            'pending' => $query->whereNull('reserved_at'),
                            default => $query,
                        };
                    }),
            ])
            ->defaultGroup($queueGroup)
            ->groups([$queueGroup, $statusGroup])
            ->defaultSort('created_at', 'desc')
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
            ->paginated(false)
            ->emptyStateHeading('No active tasks')
            ->emptyStateDescription('No pending or processing jobs right now.');
    }

    protected function boundedActiveJobsQuery(int $perQueue = 5): Builder
    {
        $model = new QueueJob();
        $keyName = $model->getKeyName();

        $ids = $this->applyActiveFilters($this->activeJobsQuery())
            ->select('queue')
            ->distinct()
            ->orderBy('queue')
            ->pluck('queue')
            ->flatMap(function (string $queue) use ($keyName, $perQueue): array {
                return $this->applyActiveFilters($this->activeJobsQuery())
                    ->where('queue', $queue)
                    ->orderByDesc('created_at')
                    ->orderByDesc($keyName)
                    ->limit($perQueue)
                    ->pluck($keyName)
                    ->all();
            });

        return $this->activeJobsQuery()
            ->whereKey($ids)
            ->orderBy('queue')
            ->orderByDesc('created_at');
    }

    protected function applyActiveFilters(Builder $query): Builder
    {
        $filterQueue = $this->tableFilters['queue']['value'] ?? null;
        $filterStatus = $this->tableFilters['status']['value'] ?? null;

        if (filled($filterQueue)) {
            $query->where('queue', $filterQueue);
        }

        if (filled($filterStatus)) {
            $query->when(
                $filterStatus === 'processing',
                fn (Builder $q): Builder => $q->whereNotNull('reserved_at'),
                fn (Builder $q): Builder => $q->whereNull('reserved_at'),
            );
        }

        return $query;
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
        if ($this->pollingInterval === 'off') {
            return null;
        }

        if (filled($this->pollingInterval)) {
            return $this->pollingInterval;
        }

        $interval = (int) config('filament-queue-monitor.refresh_interval', 10);

        if ($interval <= 0) {
            return null;
        }

        return "{$interval}s";
    }
}