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
use Illuminate\Support\Carbon;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Models\CompletedJob;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Models\Metric;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Statistics\CompletedJobsStorage;
use BlkDem\FilamentQueueMonitor\Support\Access;
use BlkDem\FilamentQueueMonitor\Support\Trans;
use BlkDem\FilamentQueueMonitor\Support\Version;

/**
 * Individual runs of one job class inside one minute.
 *
 * Reads the per-run table rather than the metrics aggregates, so unlike the
 * completed jobs and breakdown views this is an actual list of executions.
 */
class ListCompletedJobRuns extends Page implements HasTable
{
    use InteractsWithTable;

    public string $job = '';

    public string $minute = '';

    public function mount(string $job, string $minute): void
    {
        $this->job = ViewCompletedJob::decodeJob($job);
        $this->minute = self::decodeMinute($minute);
    }

    /**
     * The minute is carried as a 12 digit stamp: the usual "Y-m-d H:i" form
     * carries spaces and colons that are awkward in a path segment.
     */
    public static function encodeMinute(Carbon|string $minute): string
    {
        $minute = $minute instanceof Carbon ? $minute : Carbon::parse($minute);

        return $minute->format('YmdHi');
    }

    public static function decodeMinute(string $minute): string
    {
        $parsed = Carbon::createFromFormat('YmdHi', $minute);

        return $parsed !== false ? $parsed->format('Y-m-d H:i:s') : $minute;
    }

    public function getView(): string
    {
        return 'filament-queue-monitor::pages.list-completed-job-runs';
    }

    protected static ?string $slug = 'queue-monitor/completed-jobs/{job}/runs/{minute}';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getHeading(): string
    {
        return Trans::get('completed_jobs.runs_heading', [
            'job' => $this->job,
        ]);
    }

    public function getSubheading(): ?string
    {
        return Trans::get('completed_jobs.runs_subtitle', [
            'minute' => Carbon::parse($this->minute)->format('d.m.Y H:i'),
        ]);
    }

    public function table(Table $table): Table
    {
        $table = $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('queue')
                    ->label(Trans::get('common.queue'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('uuid')
                    ->label(Trans::get('completed_jobs.uuid'))
                    ->searchable()
                    ->copyable()
                    ->placeholder(Trans::get('common.empty_value'))
                    ->wrap(),
                TextColumn::make('runtime')
                    ->label(Trans::get('completed_jobs.runtime'))
                    ->formatStateUsing(fn ($state): string => $this->formatRuntime($state))
                    ->sortable(),
                TextColumn::make('finished_at')
                    ->label(Trans::get('completed_jobs.finished_at'))
                    ->dateTime('H:i:s')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('queue')
                    ->label(Trans::get('common.queue'))
                    ->options(fn (): array => $this->queueOptions())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where('queue', $data['value'])
                        : $query),
            ])
            ->searchPlaceholder(Trans::get('completed_jobs.runs_search_placeholder'))
            ->defaultSort('finished_at', 'desc')
            ->emptyStateHeading($this->job)
            ->emptyStateDescription($this->tableMissing()
                ? Trans::get('completed_jobs.runs_table_missing')
                : Trans::get('completed_jobs.runs_empty'))
            ->paginated([10, 25, 50, 100]);

        if (Version::isFilament4()) {
            $table->defaultKeySort(false);
        }

        return $table;
    }

    public function getTableQuery(): Builder
    {
        if (! $this->tableMissing()) {
            $minute = Carbon::parse($this->minute);

            return CompletedJob::query()
                ->where('connection', $this->currentConnection())
                ->where('job', $this->job)
                ->whereBetween('finished_at', [
                    $minute->copy()->startOfMinute(),
                    $minute->copy()->endOfMinute(),
                ]);
        }

        // The table has not been migrated yet. Query a table that does exist so
        // the page renders an explanation instead of a driver error.
        return Metric::query()->whereRaw('1 = 0');
    }

    protected function tableMissing(): bool
    {
        return ! app(CompletedJobsStorage::class)->tableExists();
    }

    /**
     * @return array<string, string>
     */
    protected function queueOptions(): array
    {
        if ($this->tableMissing()) {
            return [];
        }

        $minute = Carbon::parse($this->minute);

        return CompletedJob::query()
            ->where('connection', $this->currentConnection())
            ->where('job', $this->job)
            ->whereBetween('finished_at', [$minute->copy()->startOfMinute(), $minute->copy()->endOfMinute()])
            ->distinct()
            ->orderBy('queue')
            ->pluck('queue', 'queue')
            ->all();
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
