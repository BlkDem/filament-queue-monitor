<?php

namespace Kilo\FilamentQueueMonitor\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Kilo\FilamentQueueMonitor\QueueMonitor\Models\QueueJob;

abstract class BaseQueueTablePage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationGroup = 'Queue Monitor';

    protected static bool $isNavigationGroupEnabled = true;

    public ?string $tableSearch = '';

    public ?string $tableSortColumn = null;

    public ?string $tableSortDirection = null;

    public ?array $tableFilters = null;

    public ?int $tableRecordsPerPage = null;

    public $tableColumnSearches = [];

    public $toggledTableColumns = [];

    public $tablePaginationPage = 1;

    protected function getTableQuery(): Builder | Relation | null
    {
        return QueueJob::query();
    }

    public function getFilteredTableQuery(): Builder
    {
        return $this->getTableQuery();
    }

    public function getFilteredSortedTableQuery(): Builder
    {
        return $this->getFilteredTableQuery();
    }

    public function getTableRecords(): Collection | LengthAwarePaginator
    {
        if ($this->cachedTableRecords) {
            return $this->cachedTableRecords;
        }

        $records = collect($this->resolveAllRecords());

        if (filled($search = $this->getTableSearch())) {
            $records = $this->applySearchToTableRecords($records, $search);
        }

        $records = $this->applySortToTableRecords($records);

        $models = QueueJob::hydrate($records->all());

        $perPage = $this->getTableRecordsPerPage() ?: $this->getTable()->getDefaultPaginationPageOption() ?: 10;

        if ($this->tableRecordsPerPage === 'all') {
            $perPage = $models->count();
        }

        $page = $this->getTablePage();

        $paginated = new LengthAwarePaginator(
            $models->forPage($page, (int) $perPage),
            $models->count(),
            (int) $perPage,
            $page,
            [
                'page' => $this->getTablePaginationPageName(),
            ]
        );

        $paginated->withQueryString();

        return $this->cachedTableRecords = $paginated->onEachSide(0);
    }

    public function getAllTableRecordsCount(): int
    {
        if ($this->cachedTableRecords instanceof LengthAwarePaginator) {
            return $this->cachedTableRecords->total();
        }

        return collect($this->resolveAllRecords())->count();
    }

    public function getTableRecordKey(Model $record): string
    {
        return (string) $record->getKey();
    }

    protected function resolveTableRecord(?string $key): ?Model
    {
        if (! $key) {
            return null;
        }

        if ($this->cachedTableRecords instanceof LengthAwarePaginator) {
            foreach ($this->cachedTableRecords->getCollection() as $record) {
                if ((string) $record->getKey() === (string) $key) {
                    return $record;
                }
            }
        }

        foreach (QueueJob::hydrate($this->resolveAllRecords()) as $model) {
            if ((string) $model->getKey() === (string) $key) {
                return $model;
            }
        }

        return null;
    }

    public function resolveTableRecordModel(?string $key): ?Model
    {
        return $this->resolveTableRecord($key);
    }

    abstract protected function resolveAllRecords(): array;

    abstract protected function getSearchableFields(): array;

    protected function applySearchToTableRecords(Collection $records, string $search): Collection
    {
        $searchWords = array_filter(
            str_getcsv(preg_replace('/\s+/', ' ', $search), separator: ' ', escape: '\\'),
            fn ($word): bool => filled($word),
        );

        if (empty($searchWords)) {
            return $records;
        }

        return $records->filter(function ($record) use ($searchWords) {
            foreach ($searchWords as $word) {
                foreach ($this->getSearchableFields() as $field) {
                    $value = $this->getFieldValue($record, $field);
                    if (stripos((string) $value, $word) !== false) {
                        return true;
                    }
                }
            }

            return false;
        })->values();
    }

    protected function applySortToTableRecords(Collection $records): Collection
    {
        $column = $this->tableSortColumn;
        $direction = $this->tableSortDirection ?? 'asc';

        if (! $column) {
            return $records;
        }

        $field = $this->getSortField($column);

        return $records->sortBy(function ($record) use ($field) {
            return $this->getFieldValue($record, $field);
        }, SORT_REGULAR, $direction === 'desc');
    }

    protected function getSortField(string $column): string
    {
        return $column;
    }

    protected function getFieldValue($record, string $field)
    {
        if (is_array($record)) {
            return $record[$field] ?? null;
        }

        return $record->{$field} ?? null;
    }

    protected function getRefreshInterval(): int
    {
        return (int) config('filament-queue-monitor.refresh_interval', 10);
    }

    protected function getTablePollingInterval(): ?string
    {
        $interval = $this->getRefreshInterval();

        if ($interval <= 0) {
            return null;
        }

        return "{$interval}s";
    }

    public static function canAccess(): bool
    {
        if (! config('filament-queue-monitor.enabled', true)) {
            return false;
        }

        $authorize = config('filament-queue-monitor.authorize');

        if ($authorize instanceof \Closure) {
            return (bool) $authorize(app('auth')->user());
        }

        return true;
    }

    protected function getDriver()
    {
        return app(\Kilo\FilamentQueueMonitor\QueueMonitor\QueueMonitorManager::class)->driver();
    }
}
