# Filament 3 and 4 Compatibility Report

## Current Support Matrix

| Dependency | Supported versions |
| --- | --- |
| PHP | `^8.2` |
| Laravel | `^10.0 || ^11.0 || ^12.0` |
| Filament | `^3.3 || ^4.0` |
| Queue storage | Laravel database queues and Redis queues |

The package uses runtime capability detection rather than maintaining separate Filament 3 and 4 source trees.

## Compatibility Strategy

- `Support\Version::isFilament4()` detects Filament 4 through `Filament\Schemas\Schema`.
- Table actions are resolved dynamically: `Filament\Tables\Actions\Action` on Filament 3 and `Filament\Actions\Action` on Filament 4.
- Page classes override `getView()` instead of declaring version-specific static or instance `$view` properties.
- Conflicting table trait properties were removed from `BaseQueueTablePage`.
- `getTableRecordKey()` accepts `Model | array`, matching Filament 4 while remaining compatible with Filament 3.
- Table record pagination returns the `Paginator` contract and uses `LengthAwarePaginator` for the in-memory queue records.
- Widget polling is computed by the existing `getPollingInterval()` overrides; version-specific static/instance polling properties are not redeclared.
- `MetricsChartWidget::getHeading()` is public and returns the Filament-compatible `Htmlable` union.
- Filament 4 tables disable default key sorting because the displayed records are hydrated queue DTOs rather than ordinary Eloquent query rows.

## Validation

The same checks were run with both dependency sets:

| Filament version | Result |
| --- | --- |
| `3.3.55` | 46 tests passed, 96 assertions; monitored page/widget/plugin classes loaded |
| `4.13.4` | 46 tests passed, 96 assertions; monitored page/widget/plugin classes loaded |

The compatibility smoke test builds the queue, job, and failed-job tables. Unit coverage includes database and Redis drivers, failed-job handling, payload sanitization, metrics aggregation, and authorization defaults.

## Operational Notes

- `ext-redis` is optional at the Composer level and is only required when the Redis driver is selected.
- Redis queue discovery uses `SCAN`, honors the configured Redis prefix, and can use the `QUEUE_MONITOR_REDIS_QUEUES` allowlist.
- Metrics and completed runs use configurable table names; migrations read configuration when they execute. Set names before migrating.
- Queue Monitor access is denied by default through `authorize`. Set it to `true`, a Closure, or a Laravel Gate ability explicitly.
- Custom package Blade views use utility classes. Filament 4 applications using Tailwind 4 may need to add the vendor view path to their theme sources.
- The 2026-10-18 audit updated local dependencies to Laravel 12.70.0 / Filament 4.15.2: 179 tests and 666 assertions pass, with no Composer security advisories. The older results above are historical. See `AUDIT.md` for scope and limitations; the new CI matrix still requires execution.
