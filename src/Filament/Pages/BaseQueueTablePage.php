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
use BlkDem\FilamentQueueMonitor\Support\Trans;

abstract class BaseQueueTablePage extends Page implements HasTable
{
    use InteractsWithTable;

    public ?string $tableSortColumn = null;

    public ?string $tableSortDirection = null;

    public ?array $tableFilters = null;

    public static function getNavigationGroup(): ?string
    {
        return Trans::navigationGroup();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-queue-monitor.navigation.enabled', true);
    }

    /**
     * Filament's HasTable contract wants a builder, and the table declares one,
     * but the records do not come from it.
     *
     * Every page here resolves its rows from the driver, which is the source of
     * truth, and filtering, searching, sorting and pagination all happen in
     * getTableRecords(). Verified: rendering a driver-backed page issues no
     * query against the jobs table at all.
     *
     * That is worth stating, because this builder is the SQL jobs table and a
     * driver may be watching redis. Anything that is added later and reaches
     * for the query would silently read a table that is not the data, which is
     * how the status accessor and the status filter went wrong before. Resolve
     * through the driver instead.
     */
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

        $records = collect($this->allRecords());

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

        return $this->applyFiltersToTableRecords(collect($this->allRecords()))->count();
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

        foreach (QueueJob::hydrate($this->allRecords()) as $model) {
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

    /**
     * Records for this request. Filament asks for them several times per
     * render (records, count, filter options, action lookups), and every ask
     * used to go back to the queue backend.
     */
    protected ?array $resolvedRecords = null;

    protected bool $recordsTruncated = false;

    protected function allRecords(): array
    {
        return $this->resolvedRecords ??= $this->bounded($this->resolveAllRecords());
    }

    /**
     * Replaces the table trait's own flush, which only clears the paginator,
     * so a reset or a finished action also re-reads the backend.
     */
    public function flushCachedTableRecords(): void
    {
        $this->cachedTableRecords = null;

        $this->resolvedRecords = null;
        $this->recordsTruncated = false;
    }

    /**
     * The most rows a list page loads. Search, filters, sorting and paging all
     * run in PHP over what was loaded, so without a ceiling a backlog of a
     * million jobs was read into memory on every poll.
     */
    public static function listLimit(): int
    {
        $limit = config('filament-queue-monitor.list_limit', 1000);

        return is_numeric($limit) && (int) $limit > 0 ? (int) $limit : 1000;
    }

    /**
     * What to ask a driver for: one more than is shown, so a cut list can be
     * told apart from one that happens to be exactly the limit long.
     */
    protected function fetchLimit(): int
    {
        return static::listLimit() + 1;
    }

    protected function bounded(iterable $items): array
    {
        $items = is_array($items) ? array_values($items) : iterator_to_array($items, false);

        if (count($items) > static::listLimit()) {
            $this->recordsTruncated = true;

            return array_slice($items, 0, static::listLimit());
        }

        return $items;
    }

    public function truncationNotice(): ?string
    {
        // The table header renders before its rows, so load them first.
        $this->allRecords();

        return $this->recordsTruncated
            ? Trans::get('common.list_truncated', ['limit' => static::listLimit()])
            : null;
    }

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

    /**
     * Maps a column to the field it sorts by. Only the delayed page needs it:
     * it sorts on the numeric minutes rather than on the translated string,
     * which would order "2 ч" against "10 мин" alphabetically.
     */
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
