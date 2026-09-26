<?php

namespace BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs;

use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Models\Metric;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Statistics\MetricsStorage;
use BlkDem\FilamentQueueMonitor\Support\Access;
use BlkDem\FilamentQueueMonitor\Support\Trans;
use BlkDem\FilamentQueueMonitor\Support\Version;

/**
 * Completed jobs, aggregated per job class.
 *
 * Redis removes a job from the queue as soon as it has run, so there is no
 * per-run record to list. The metrics table keeps one row per minute, queue
 * and job class, which is what this page rolls up.
 */
class ListCompletedJobs extends Page implements HasTable
{
    use InteractsWithTable;

    public function getView(): string
    {
        return 'filament-queue-monitor::pages.list-completed-jobs';
    }

    protected static ?string $slug = 'queue-monitor/completed-jobs';

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-check-badge';
    }

    public static function getNavigationLabel(): string
    {
        return Trans::get('navigation.completed_jobs');
    }

    public static function getNavigationSort(): ?int
    {
        return 28;
    }

    public static function getNavigationGroup(): ?string
    {
        return Trans::navigationGroup();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-queue-monitor.navigation.enabled', true)
            && (bool) config('filament-queue-monitor.metrics.enabled', true);
    }

    public function table(Table $table): Table
    {
        $table = $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('job')
                    ->label(Trans::get('completed_jobs.job'))
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->weight('font-medium'),
                TextColumn::make('queue')
                    ->label(Trans::get('common.queue'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('processed')
                    ->label(Trans::get('completed_jobs.processed'))
                    ->numeric()
                    ->sortable()
                    ->color('success'),
                TextColumn::make('failed')
                    ->label(Trans::get('completed_jobs.failed'))
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn ($state): string => (int) $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('avg_runtime')
                    ->label(Trans::get('completed_jobs.avg_time'))
                    ->formatStateUsing(fn ($state): string => $this->formatRuntime($state))
                    ->sortable(),
                TextColumn::make('max_runtime')
                    ->label(Trans::get('completed_jobs.max_time'))
                    ->formatStateUsing(fn ($state): string => $this->formatRuntime($state))
                    ->sortable(),
                TextColumn::make('last_activity')
                    ->label(Trans::get('completed_jobs.last_activity'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('period')
                    ->label(Trans::get('completed_jobs.period'))
                    ->options([
                        'hour' => Trans::get('periods.hour'),
                        'today' => Trans::get('periods.today'),
                        '24h' => Trans::get('periods.24h'),
                        '7d' => Trans::get('periods.7d'),
                    ])
                    ->default('today')
                    // The filter is a reporting window, not a column value.
                    // Without this, Filament adds `where period = 'today'`,
                    // which matches nothing and empties the table.
                    ->query(fn (Builder $query): Builder => $query),
                SelectFilter::make('queue')
                    ->label(Trans::get('common.queue'))
                    ->options(fn (): array => $this->queueOptions())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where('queue', $data['value'])
                        : $query),
            ])
            ->searchPlaceholder(Trans::get('completed_jobs.search_placeholder'))
            ->defaultSort('processed', 'desc')
            ->emptyStateHeading(Trans::get('completed_jobs.title'))
            ->emptyStateDescription(Trans::get('completed_jobs.empty'))
            ->paginated([10, 25, 50, 100]);

        if (Version::isFilament4()) {
            $table->defaultKeySort(false);
        }

        return $table;
    }

    public function getTableQuery(): Builder
    {
        $query = $this->aggregateQuery();

        // The queue filter applies its own where clause through Filament; only
        // the reporting window has to be added here.
        $query->where('period', '>=', app(MetricsStorage::class)->periodStart($this->selectedPeriod()));

        return $query;
    }

    protected function aggregateQuery(): Builder
    {
        $storage = app(MetricsStorage::class);

        if (! $storage->isEnabled() || ! $storage->tableExists()) {
            return Metric::query()->whereRaw('1 = 0');
        }

        $jobSelect = $storage->jobColumnExists()
            ? "COALESCE(NULLIF(job, ''), 'unknown') as job"
            : "'unknown' as job";

        return Metric::query()
            ->selectRaw('MAX(id) as id')
            ->selectRaw("queue, {$jobSelect}")
            ->selectRaw('SUM(processed) as processed')
            ->selectRaw('SUM(failed) as failed')
            ->selectRaw('AVG(avg_runtime) as avg_runtime')
            ->selectRaw('MAX(max_runtime) as max_runtime')
            ->selectRaw('MAX(period) as last_activity')
            ->where('connection', $this->currentConnection())
            ->groupBy($storage->jobColumnExists() ? ['job', 'queue'] : ['queue']);
    }

    /**
     * @return array<string, string>
     */
    protected function queueOptions(): array
    {
        $storage = app(MetricsStorage::class);

        if (! $storage->isEnabled() || ! $storage->tableExists()) {
            return [];
        }

        return Metric::query()
            ->where('connection', $this->currentConnection())
            ->where('period', '>=', $storage->periodStart($this->selectedPeriod()))
            ->distinct()
            ->orderBy('queue')
            ->pluck('queue', 'queue')
            ->all();
    }

    protected function selectedPeriod(): string
    {
        return $this->filterValue('period') ?? 'today';
    }

    protected function filterValue(string $name): ?string
    {
        $value = $this->getTableFilterState($name)['value'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Metrics are recorded per queue connection, so the page is scoped to the
     * connection in use instead of folding another connection's history in.
     */
    protected function currentConnection(): string
    {
        $connection = config('queue.default', 'database');

        return is_string($connection) && $connection !== '' ? $connection : 'database';
    }

    protected function formatRuntime(mixed $state): string
    {
        if ($state === null) {
            return Trans::get('common.empty_value');
        }

        return Trans::get('breakdown.seconds', [
            'seconds' => number_format((float) $state, 2),
        ]);
    }

    public static function canAccess(): bool
    {
        return Access::canAccess();
    }
}
