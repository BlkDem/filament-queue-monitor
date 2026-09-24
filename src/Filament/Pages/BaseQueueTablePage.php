<?php

namespace BlkDem\FilamentQueueMonitor\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Models\QueueJob;
use BlkDem\FilamentQueueMonitor\Support\Access;

abstract class BaseQueueTablePage extends Page implements HasTable
{
    use InteractsWithTable;

    public ?string $tableSortColumn = null;

    public ?string $tableSortDirection = null;

    public ?array $tableFilters = null;

    public static function getNavigationGroup(): ?string
    {
        $group = config('filament-queue-monitor.navigation.group', 'Queue Monitor');

        return is_string($group) && $group !== '' ? $group : 'Queue Monitor';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-queue-monitor.navigation.enabled', true);
    }

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

    public function getTableRecords(): Paginator
    {
        if ($this->cachedTableRecords) {
            return $this->cachedTableRecords;
        }

        $records = collect($this->resolveAllRecords());

        $records = $this->applyFiltersToTableRecords($records);

        if (filled($search = $this->getTableSearch())) {
            $records = $this->applySearchToTableRecords($records, $search);
        }

        $records = $this->applySortToTableRecords($records);

        $models = QueueJob::hydrate($records->all());

        $perPage = $this->getTableRecordsPerPage() ?: $this->getTable()->getDefaultPaginationPageOption() ?: 10;

        if ($this->tableRecordsPerPage === 'all') {
            $perPage = max(1, $models->count());
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

        return $this->applyFiltersToTableRecords(collect($this->resolveAllRecords()))->count();
    }

    public function getTableRecordKey(Model | array $record): string
    {
        if (is_array($record)) {
            return (string) ($record['id'] ?? $record['uuid'] ?? '');
        }

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

    protected function applyFiltersToTableRecords(Collection $records): Collection
    {
        foreach ($this->tableFilters ?? [] as $field => $filter) {
            if (! is_array($filter)) {
                continue;
            }

            $filtered = $this->applyFilterToTableRecords($records, (string) $field, $filter);

            if ($filtered !== null) {
                $records = $filtered;
            }
        }

        return $records;
    }

    protected function applyFilterToTableRecords(Collection $records, string $field, array $filter): ?Collection
    {
        $value = $filter['value'] ?? null;

        if (blank($value)) {
            return null;
        }

        return $records->filter(
            fn ($record): bool => (string) $this->getFieldValue($record, $field) === (string) $value,
        )->values();
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
        return Access::canAccess();
    }

    protected function getDriver()
    {
        return app(\BlkDem\FilamentQueueMonitor\QueueMonitor\QueueMonitorManager::class)->driver();
    }
}
