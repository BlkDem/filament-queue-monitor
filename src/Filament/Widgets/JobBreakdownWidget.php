<?php

namespace BlkDem\FilamentQueueMonitor\Filament\Widgets;

use Filament\Support\Facades\FilamentView;
use Filament\Tables\Columns\Summarizers\Average;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Tables\View\TablesRenderHook;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Models\Metric;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Statistics\MetricsStorage;
use BlkDem\FilamentQueueMonitor\Support\Trans;

class JobBreakdownWidget extends BaseTableWidget
{
    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    public string $selectedPeriod = 'today';

    public array $periods = [];

    public ?string $pollingInterval = null;

    protected $listeners = ['refreshDashboard' => 'refreshDashboard'];

    public function boot(): void
    {
        $this->periods = $this->resolvePeriods();

        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_GROUPING_SELECTOR_AFTER,
            fn (): string => view('filament-queue-monitor::widgets.partials.toolbar-controls', [
                'periods' => $this->periods,
                'selectedPeriod' => $this->selectedPeriod,
                'defaultInterval' => (int) config('filament-queue-monitor.metrics.refresh_interval', 30),
            ])->render(),
            scopes: static::class,
        );
    }

    public function refreshDashboard(string $period): void
    {
        $this->selectedPeriod = in_array($period, ['hour', 'today', '24h', '7d'], true)
            ? $period
            : 'today';
    }

    protected function resolvePeriods(): array
    {
        return [
            'hour' => Trans::get('periods.hour'),
            'today' => Trans::get('periods.today'),
            '24h' => Trans::get('periods.24h'),
            '7d' => Trans::get('periods.7d'),
        ];
    }

    public function render(): View
    {
        return view('filament-queue-monitor::widgets.job-breakdown', $this->getViewData());
    }

    public function table(Table $table): Table
    {
        $counts = [];

        $groupCounts = function (string $column) use (&$counts): array {
            if (! array_key_exists($column, $counts)) {
                $counts[$column] = $this->query()
                    ->get([$column])
                    ->groupBy($column)
                    ->map->count()
                    ->all();
            }

            return $counts[$column];
        };

        $queueGroup = Group::make('queue')
            ->label(Trans::get('common.queue'))
            ->collapsible()
            ->getTitleFromRecordUsing(function (Model $record) use ($groupCounts): string {
                $value = (string) $record->queue;

                return $value.' ('.($groupCounts('queue')[$value] ?? 0).')';
            });

        $connectionGroup = Group::make('connection')
            ->label(Trans::get('common.connection'))
            ->collapsible()
            ->getTitleFromRecordUsing(function (Model $record) use ($groupCounts): string {
                $value = (string) $record->connection;

                return $value.' ('.($groupCounts('connection')[$value] ?? 0).')';
            });

        $this->collapseGroupsByDefault($table);

        return $table
            ->heading(Trans::get('breakdown.heading'))
            ->description(Trans::get('breakdown.description'))
            ->query(fn (): Builder => $this->query())
            ->filters([
                SelectFilter::make('queue')
                    ->label(Trans::get('common.queue'))
                    ->options(fn (): array => $this->metricQueueOptions()),
            ])
            ->defaultGroup($queueGroup)
            ->groups([$queueGroup, $connectionGroup])
            ->defaultSort('processed', 'desc')
            ->columns([
                TextColumn::make('job')
                    ->label(Trans::get('common.job'))
                    ->formatStateUsing(fn (string $state) => Str::afterLast($state, '\\'))
                    ->tooltip(fn ($record): string => (string) $record->job)
                    ->wrap(),
                TextColumn::make('connection')
                    ->label(Trans::get('common.connection'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('processed')
                    ->label(Trans::get('breakdown.processed'))
                    ->numeric()
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->numeric()
                            ->label(Trans::get('breakdown.total_processed')),
                    ),
                TextColumn::make('failed')
                    ->label(Trans::get('breakdown.failed'))
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn ($state): string => (int) $state > 0 ? 'danger' : 'success')
                    ->summarize(
                        Sum::make()
                            ->numeric()
                            ->label(Trans::get('breakdown.total_failed')),
                    ),
                TextColumn::make('avg_runtime')
                    ->label(Trans::get('breakdown.avg_time'))
                    ->formatStateUsing(fn ($state) => $this->formatRuntime($state))
                    ->summarize(
                        Average::make()
                            ->label(Trans::get('breakdown.avg_time')),
                    ),
                TextColumn::make('max_runtime')
                    ->label(Trans::get('breakdown.max_time'))
                    ->formatStateUsing(fn ($state) => $this->formatRuntime($state)),
            ])
            ->emptyStateHeading(Trans::get('breakdown.empty_heading'))
            ->emptyStateDescription(Trans::get('breakdown.empty_description'));
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

    protected function getPollingInterval(): ?string
    {
        if ($this->pollingInterval === 'off') {
            return null;
        }

        if (filled($this->pollingInterval)) {
            return $this->pollingInterval;
        }

        $interval = (int) config('filament-queue-monitor.metrics.refresh_interval', 30);

        if ($interval <= 0) {
            return null;
        }

        return "{$interval}s";
    }

    protected function collapseGroupsByDefault(Table $table): void
    {
        if (method_exists($table, 'collapsedGroupsByDefault')) {
            $table->collapsedGroupsByDefault();
        }
    }

    protected function metricQueueOptions(): array
    {
        $storage = app(MetricsStorage::class);

        if (! $storage->isEnabled() || ! Schema::hasTable($storage->getTable())) {
            return [];
        }

        return Metric::query()
            ->where('period', '>=', $this->periodStart())
            ->distinct()
            ->orderBy('queue')
            ->pluck('queue', 'queue')
            ->all();
    }

    protected function query(): Builder
    {
        $storage = app(MetricsStorage::class);
        $table = $storage->getTable();

        if (! $storage->isEnabled() || ! Schema::hasTable($table)) {
            return Metric::query()->whereRaw('1 = 0');
        }

        $jobSelect = $storage->jobColumnExists()
            ? 'COALESCE(NULLIF(job, \'\'), \'unknown\') as job'
            : '\'unknown\' as job';

        $builder = Metric::query()
            ->selectRaw('MAX(id) as id')
            ->selectRaw('connection, queue, '.$jobSelect)
            ->selectRaw('SUM(processed) as processed')
            ->selectRaw('SUM(failed) as failed')
            ->selectRaw('AVG(avg_runtime) as avg_runtime')
            ->selectRaw('MAX(max_runtime) as max_runtime')
            ->where('period', '>=', $this->periodStart())
            ->groupBy(['connection', 'queue', 'job']);

        return $builder;
    }

    protected function periodStart(): string
    {
        $period = in_array($this->selectedPeriod, ['hour', 'today', '24h', '7d'], true)
            ? $this->selectedPeriod
            : 'today';

        return match ($period) {
            'hour' => now()->subHour()->format('Y-m-d H:i:s'),
            '24h' => now()->subHours(24)->format('Y-m-d H:i:s'),
            '7d' => now()->subDays(7)->format('Y-m-d H:i:s'),
            default => today()->toDateString(),
        };
    }
}