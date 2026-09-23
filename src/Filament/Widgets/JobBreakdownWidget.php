<?php

namespace Kilo\FilamentQueueMonitor\Filament\Widgets;

use Filament\Tables\Columns\Summarizers\Average;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Kilo\FilamentQueueMonitor\QueueMonitor\Models\Metric;
use Kilo\FilamentQueueMonitor\QueueMonitor\Statistics\MetricsStorage;

class JobBreakdownWidget extends BaseTableWidget
{
    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    public string $selectedPeriod = 'today';

    public ?string $pollingInterval = null;

    protected $listeners = ['refreshDashboard' => 'refreshDashboard'];

    public function refreshDashboard(string $period): void
    {
        $this->selectedPeriod = in_array($period, ['hour', 'today', '24h', '7d'], true)
            ? $period
            : 'today';
    }

    public function render(): View
    {
        return view('filament-queue-monitor::widgets.job-breakdown', $this->getViewData());
    }

    public function table(Table $table): Table
    {
        $counts = [];

        $this->collapseGroupsByDefault($table);

        return $table
            ->heading('Job Breakdown')
            ->description('Processed and failed jobs grouped by queue')
            ->query(fn (): Builder => $this->query())
            ->defaultGroup(
                Group::make('queue')
                    ->label('Queue')
                    ->collapsible()
                    ->getTitleFromRecordUsing(function (Model $record) use (&$counts): string {
                        if ($counts === []) {
                            $counts = $this->query()
                                ->get(['queue'])
                                ->groupBy('queue')
                                ->map->count()
                                ->all();
                        }

                        return (string) $record->queue.' ('.($counts[$record->queue] ?? 0).')';
                    }),
            )
            ->groups([
                Group::make('queue')->label('Queue')->collapsible(),
                Group::make('connection')->label('Connection')->collapsible(),
            ])
            ->defaultSort('processed', 'desc')
            ->poll($this->getPollingInterval())
            ->columns([
                TextColumn::make('job')
                    ->label('Job')
                    ->formatStateUsing(fn (string $state) => Str::afterLast($state, '\\'))
                    ->tooltip(fn ($record): string => (string) $record->job)
                    ->wrap(),
                TextColumn::make('connection')
                    ->label('Connection')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('processed')
                    ->label('Processed')
                    ->numeric()
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->numeric()
                            ->label('Total processed'),
                    ),
                TextColumn::make('failed')
                    ->label('Failed')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn ($state): string => (int) $state > 0 ? 'danger' : 'success')
                    ->summarize(
                        Sum::make()
                            ->numeric()
                            ->label('Total failed'),
                    ),
                TextColumn::make('avg_runtime')
                    ->label('Avg time')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 2).'s' : '—')
                    ->summarize(
                        Average::make()
                            ->label('Avg time'),
                    ),
                TextColumn::make('max_runtime')
                    ->label('Max time')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 2).'s' : '—'),
            ])
            ->emptyStateHeading('No job activity')
            ->emptyStateDescription('Nothing was processed or failed for the selected period.');
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