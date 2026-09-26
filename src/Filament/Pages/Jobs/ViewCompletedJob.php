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
use BlkDem\FilamentQueueMonitor\QueueMonitor\Models\Metric;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Statistics\MetricsStorage;
use BlkDem\FilamentQueueMonitor\Support\Access;
use BlkDem\FilamentQueueMonitor\Support\Trans;
use BlkDem\FilamentQueueMonitor\Support\Version;

/**
 * Minute-by-minute activity of one job class.
 *
 * Redis removes a job once it has run, so individual runs are not recoverable.
 * The metrics table keeps one row per minute, queue and class, which is what
 * this page shows.
 */
class ViewCompletedJob extends Page implements HasTable
{
    use InteractsWithTable;

    /**
     * Namespace separator for the URL. A backslash is not escaped by Laravel's
     * route parameter encoder, and a raw one in a path can be normalised away
     * by the browser. "~" is unreserved in RFC 3986, so it travels safely.
     */
    public const NAMESPACE_SEPARATOR = '~';

    public string $job = '';

    public function mount(string $job): void
    {
        $this->job = self::decodeJob($job);
    }

    public static function encodeJob(string $job): string
    {
        return str_replace('\\', self::NAMESPACE_SEPARATOR, $job);
    }

    public static function decodeJob(string $job): string
    {
        return str_replace(self::NAMESPACE_SEPARATOR, '\\', rawurldecode($job));
    }

    public function getView(): string
    {
        return 'filament-queue-monitor::pages.view-completed-job';
    }

    protected static ?string $slug = 'queue-monitor/completed-jobs/{job}';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getHeading(): string
    {
        return $this->job;
    }

    public function getSubheading(): ?string
    {
        return Trans::get('completed_jobs.detail_subtitle');
    }

    public function table(Table $table): Table
    {
        $table = $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('period')
                    ->label(Trans::get('completed_jobs.minute'))
                    ->dateTime()
                    ->sortable(),
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
            ])
            ->filters([
                SelectFilter::make('period_filter')
                    ->label(Trans::get('completed_jobs.period'))
                    ->options([
                        'hour' => Trans::get('periods.hour'),
                        'today' => Trans::get('periods.today'),
                        '24h' => Trans::get('periods.24h'),
                        '7d' => Trans::get('periods.7d'),
                    ])
                    ->default('7d')
                    ->query(fn (Builder $query): Builder => $query),
                SelectFilter::make('queue')
                    ->label(Trans::get('common.queue'))
                    ->options(fn (): array => $this->queueOptions())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where('queue', $data['value'])
                        : $query),
            ])
            ->searchPlaceholder(Trans::get('completed_jobs.detail_search_placeholder'))
            ->defaultSort('period', 'desc')
            ->emptyStateHeading($this->job)
            ->emptyStateDescription(Trans::get('completed_jobs.detail_empty'))
            ->paginated([10, 25, 50, 100]);

        if (Version::isFilament4()) {
            $table->defaultKeySort(false);
        }

        return $table;
    }

    public function getTableQuery(): Builder
    {
        $query = $this->baseQuery();

        $query->where('period', '>=', app(MetricsStorage::class)->periodStart($this->selectedPeriod()));

        return $query;
    }

    protected function baseQuery(): Builder
    {
        $storage = app(MetricsStorage::class);

        // Without the `job` column there is nothing to drill into.
        if (! $storage->isEnabled() || ! $storage->tableExists() || ! $storage->jobColumnExists()) {
            return Metric::query()->whereRaw('1 = 0');
        }

        return Metric::query()
            ->select(['id', 'period', 'queue', 'processed', 'failed', 'avg_runtime', 'max_runtime'])
            ->where('connection', $this->currentConnection())
            ->where('job', $this->job);
    }

    /**
     * @return array<string, string>
     */
    protected function queueOptions(): array
    {
        $storage = app(MetricsStorage::class);

        if (! $storage->isEnabled() || ! $storage->tableExists() || ! $storage->jobColumnExists()) {
            return [];
        }

        return Metric::query()
            ->where('connection', $this->currentConnection())
            ->where('job', $this->job)
            ->where('period', '>=', $storage->periodStart($this->selectedPeriod()))
            ->distinct()
            ->orderBy('queue')
            ->pluck('queue', 'queue')
            ->all();
    }

    protected function selectedPeriod(): string
    {
        $value = $this->getTableFilterState('period_filter')['value'] ?? null;

        return is_string($value) && $value !== '' ? $value : '7d';
    }

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
