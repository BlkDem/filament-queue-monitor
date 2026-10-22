# Changelog

All notable changes are documented here. Versions follow Semantic Versioning;
see [RELEASING.md](RELEASING.md). No release has been tagged yet.

## [Unreleased]

### Fixed

- Installation failed on Laravel 10: the metrics migration used
  `float(precision: ...)`, a Laravel 11 signature. Runtime columns are now
  `double`, which also stops Laravel 10 on MySQL rounding run times to 0.01 s.
- On Filament 3 the activity and job breakdown widgets used a Filament 4 only
  class, so the panel dashboard and the monitor dashboard returned a 500.
- The minimum Laravel 10 version is now 10.43, the first with
  `Schema::hasIndex()`, which the migrations use.

- Metric writes can no longer affect the job being monitored. A failed write
  from `JobProcessed` raised `JobExceptionOccurred` for a job that had
  succeeded, skipped later listeners and could stop the worker on a lost
  connection; a failing schema check on `JobProcessing` released or failed the
  job before it ran. Writes are now reported and dropped.
- Runtime averages on MySQL/MariaDB were weighted by the already-incremented
  counts (two runs of 10 s and 20 s averaged 13.3 s).
- Metric writes failed on MySQL connections with `use_upsert_alias` enabled
  (ambiguous column names).
- The metrics migrations failed on MySQL with utf8mb4: the four-column unique
  index needed 4080 bytes against a 3072 byte limit. Key columns are now sized,
  and existing MySQL tables are narrowed before the index is created.
- Widgets were shown on Filament's default dashboard to every panel user,
  ignoring `authorize`.
- List pages, failed jobs and the activity widget read whole queues and the
  whole `failed_jobs` table into memory on every poll. Reads are now bounded
  by `list_limit` and memoised per request.
- `QUEUE_MONITOR_DRIVER` set to a queue connection name was honoured by access
  checks but not by the drivers, pages and widgets.
- The Redis driver defaulted to the `default` Redis connection instead of the
  queue's own.
- Redis reservation times were shown `retry_after` seconds in the future, and
  stuck-job counts were off by the same amount.
- Retrying a failed job unserialized every class in its command; only the
  command class is now built.
- A metrics table migrated after workers started was never written to until
  the workers restarted.
- Jobs on the `sync` connection are no longer recorded.

- Parse database queue timestamps as Unix timestamps, including string values.
- Handle an empty configured database queue list safely.
- Scope database failure counts to the monitored queue connection.
- Accept array records from failed-job providers.
- Invalidate failed-job and queue caches after retrying or deleting failures.
- Avoid retry errors for malformed serialized command payloads.
- Honor string false values in the monitoring master switch.
- Preserve metrics when retention is zero, consistently with completed runs.
- Recognize legacy truncated metrics indexes during the job-column migration.
- Correct installation package name and table configuration documentation.
- Make test route registration and icon rendering compatible with Filament 4.

### Added

- Widget rendering tests through Livewire with the Filament providers loaded.

- `authorize_actions` / `QUEUE_MONITOR_AUTHORIZE_ACTIONS` for retry and delete.
- `list_limit` / `QUEUE_MONITOR_LIST_LIMIT`, with a notice when a list is cut.
- MySQL and Redis services in CI; MySQL-only tests run when
  `QUEUE_MONITOR_TEST_MYSQL_HOST` is set.

- Regression coverage for the audit findings.
- CI compatibility matrix for Laravel 10–12 and Filament 3–4, plus a separate
  security audit against freshly resolved current dependencies.
- Testbench 10 support to test Laravel 12.
- Release policy, changelog and distribution export exclusions.

### Changed

- `store_payload` now defaults to `false`. Set `QUEUE_MONITOR_STORE_PAYLOAD=true`
  to keep payloads on completed runs.
- `QueueMonitorDriver` list methods take an optional `?int $limit`;
  `failedJobs()` takes one too. Custom implementations must add the parameter.
- The test suite uses Redis database 15 (`QUEUE_MONITOR_TEST_REDIS_DB`) and
  flushes it; it no longer flushes database 0.

### Upgrade notes

- Planned initial release: `0.1.0`; no tag or publication is created automatically.
- Set table names before migrating. Editing configuration does not rename tables.
- Migration index fixes apply when migrations execute; installations that already
  ran the job-column migration should inspect legacy indexes (see `AUDIT.md`).
- Updating this library does not update the application's framework lock file.
  Resolve security updates in the consuming application as well.
