<?php

namespace BlkDem\FilamentQueueMonitor\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use BlkDem\FilamentQueueMonitor\Filament\Pages\Queues\ViewQueue;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Models\QueueJob;
use BlkDem\FilamentQueueMonitor\Support\Trans;
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
        $groupCounts = function (string $column): array {
            return $this->groupCounts($column);
        };

        $queueGroup = Group::make('queue')
            ->label(Trans::get('common.queue'))
            ->collapsible()
            ->getTitleFromRecordUsing(function (Model $record) use ($groupCounts): string {
                $value = (string) $record->queue;

                return $value.' ('.($groupCounts('queue')[$value] ?? 0).')';
            });

        $statusGroup = Group::make('status')
            ->label(Trans::get('common.status'))
            ->collapsible()
            ->orderQueryUsing(function (Builder $query, string $direction): Builder {
                $query->orderByRaw("(CASE WHEN reserved_at IS NOT NULL THEN 'processing' ELSE 'pending' END) {$direction}");

                return $query;
            })
            ->getTitleFromRecordUsing(function (Model $record) use ($groupCounts): string {
                $value = (string) $record->status;

                return Trans::status($value).' ('.($groupCounts('status')[$value] ?? 0).')';
            });

        $this->collapseGroupsByDefault($table);

        return $table
            ->heading(Trans::get('activity.heading'))
            ->description(Trans::get('activity.description'))
            ->query(fn (): Builder => $this->activeRecordsQuery())
            ->filters([
                SelectFilter::make('queue')
                    ->label(Trans::get('common.queue'))
                    ->options(fn (): array => $this->activeJobsQuery()
                        ->distinct()
                        ->orderBy('queue')
                        ->pluck('queue', 'queue')
                        ->all()),
                SelectFilter::make('status')
                    ->label(Trans::get('common.status'))
                    ->options([
                        'pending' => Trans::get('status.pending'),
                        'processing' => Trans::get('status.processing'),
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
                    ->label(Trans::get('common.job'))
                    ->formatStateUsing(fn (string $state) => Str::afterLast($state, '\\'))
                    ->tooltip(fn ($record): string => (string) $record->name)
                    ->wrap()
                    ->description(fn ($record): string => (string) $record->name),
                TextColumn::make('status')
                    ->label(Trans::get('common.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Trans::status($state))
                    ->color(fn (string $state): string => $state === 'processing' ? 'info' : 'gray')
                    ->tooltip(fn (string $state): string => $state === 'processing'
                        ? Trans::get('activity.reserved_tooltip')
                        : Trans::get('activity.queued_tooltip')),
                TextColumn::make('attempts')
                    ->label(Trans::get('common.attempts'))
                    ->badge()
                    ->color(fn ($state): string => (int) $state > 0 ? 'warning' : 'gray'),
                TextColumn::make('reserved_at')
                    ->label(Trans::get('activity.running_since'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder(Trans::get('common.empty_value')),
                TextColumn::make('created_at')
                    ->label(Trans::get('activity.queued'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordUrl(fn ($record): ?string => ViewQueue::getUrl(['queue' => $record->queue]))
            ->paginated(false)
            ->emptyStateHeading(Trans::get('activity.empty_heading'))
            ->emptyStateDescription(Trans::get('activity.empty_description'));
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

    /**
     * The database driver reads the `jobs` table, so the activity table can be
     * served straight from a query. The redis driver keeps pending and reserved
     * jobs in a list and a sorted set instead, so records are assembled from the
     * driver and the table falls back to an in-memory source.
     */
    protected function usesDriverRecords(): bool
    {
        return app(\BlkDem\FilamentQueueMonitor\QueueMonitor\QueueMonitorManager::class)->driver() instanceof \BlkDem\FilamentQueueMonitor\QueueMonitor\Drivers\RedisQueueMonitorDriver;
    }

    protected function activeRecordsQuery(): Builder
    {
        return $this->usesDriverRecords()
            ? $this->activeJobsQuery()
            : $this->boundedActiveJobsQuery();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function driverRecords(int $perQueue = 5): array
    {
        $driver = app(\BlkDem\FilamentQueueMonitor\QueueMonitor\QueueMonitorManager::class)->driver();
        $records = [];

        foreach ($driver->getQueues() as $queueInfo) {
            $queue = $queueInfo->name;

            $pending = collect($driver->pendingJobs($queue));
            $processing = collect($driver->processingJobs($queue));

            $keep = $perQueue > 0
                ? $pending->take($perQueue)->concat($processing->take($perQueue))
                : $pending->concat($processing);

            foreach ($keep as $job) {
                $records[] = [
                    'id' => (string) ($job->uuid ?? $job->id ?? ''),
                    'uuid' => $job->uuid,
                    'queue' => $job->queue ?: $queue,
                    'job' => $job->job,
                    'attempts' => $job->attempts,
                    'reserved_at' => $job->reservedAt?->getTimestamp(),
                    'created_at' => $job->createdAt?->getTimestamp(),
                    'available_at' => $job->availableAt?->getTimestamp(),
                    'payload' => $job->payload,
                ];
            }
        }

        return $records;
    }

    public function getTableRecords(): EloquentCollection|Paginator|CursorPaginator
    {
        if (! $this->usesDriverRecords()) {
            return parent::getTableRecords();
        }

        if ($this->cachedTableRecords instanceof EloquentCollection) {
            return $this->cachedTableRecords;
        }

        $records = collect($this->driverRecords());
        $records = $this->applyDriverFilters($records);
        $records = $this->applyDriverSearch($records);
        $records = $this->applyDriverSort($records);

        return $this->cachedTableRecords = QueueJob::hydrate($records->all())->values();
    }

    public function getAllTableRecordsCount(): int
    {
        if ($this->usesDriverRecords()) {
            return $this->applyDriverFilters(collect($this->driverRecords()))->count();
        }

        return parent::getAllTableRecordsCount();
    }

    protected function applyDriverFilters(Collection $records): Collection
    {
        $filters = $this->getTableFilters();

        $queue = $filters['queue']['value'] ?? null;
        $status = $filters['status']['value'] ?? null;

        if (filled($queue)) {
            $records = $records->filter(fn (array $record): bool => (string) $record['queue'] === (string) $queue);
        }

        if (filled($status)) {
            $records = $records->filter(function (array $record) use ($status): bool {
                $isProcessing = ($record['reserved_at'] ?? null) !== null;

                return $status === 'processing' ? $isProcessing : ! $isProcessing;
            });
        }

        return $records->values();
    }

    protected function applyDriverSearch(Collection $records): Collection
    {
        $search = trim((string) $this->getTableSearch());

        if ($search === '') {
            return $records;
        }

        $words = array_filter(
            str_getcsv(preg_replace('/\s+/', ' ', $search), separator: ' ', escape: '\\'),
            fn ($word): bool => filled($word),
        );

        if ($words === []) {
            return $records;
        }

        return $records->filter(function (array $record) use ($words): bool {
            foreach ($words as $word) {
                foreach (['queue', 'job'] as $field) {
                    if (stripos((string) ($record[$field] ?? ''), $word) !== false) {
                        return true;
                    }
                }
            }

            return false;
        })->values();
    }

    protected function applyDriverSort(Collection $records): Collection
    {
        $column = $this->getTableSortColumn();

        if (! $column) {
            return $records;
        }

        $direction = $this->getTableSortDirection() === 'desc';

        return $records->sortBy(function (array $record) use ($column) {
            if ($column === 'status') {
                return ($record['reserved_at'] ?? null) !== null ? 1 : 0;
            }

            return match ($column) {
                'name' => (string) ($record['job'] ?? ''),
                'attempts' => (int) ($record['attempts'] ?? 0),
                default => $record[$column] ?? null,
            };
        }, SORT_REGULAR, $direction)->values();
    }

    protected function groupCounts(string $column): array
    {
        if (! $this->usesDriverRecords()) {
            if ($column === 'status') {
                return $this->applyActiveFilters($this->activeJobsQuery())
                    ->selectRaw("(CASE WHEN reserved_at IS NOT NULL THEN 'processing' ELSE 'pending' END) AS status")
                    ->selectRaw('COUNT(*) AS total')
                    ->groupByRaw("(CASE WHEN reserved_at IS NOT NULL THEN 'processing' ELSE 'pending' END)")
                    ->pluck('total', 'status')
                    ->all();
            }

            return $this->applyActiveFilters($this->activeJobsQuery())
                ->select($column)
                ->selectRaw('COUNT(*) AS total')
                ->groupBy($column)
                ->pluck('total', $column)
                ->all();
        }

        $counts = [];

        foreach ($this->applyDriverFilters(collect($this->driverRecords())) as $record) {
            $key = $column === 'status'
                ? (($record['reserved_at'] ?? null) !== null ? 'processing' : 'pending')
                : (string) ($record[$column] ?? '');

            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
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