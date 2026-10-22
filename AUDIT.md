# Project audit — 2026-10-18

## Scope and verification

Reviewed queue drivers, failed-job handling, metrics storage and listeners,
access control, migrations, package metadata, documentation and release setup.
This is a code and automated-test audit, not a penetration test.

- Baseline: 159 tests / 626 assertions on Filament 3.3.55 and Laravel 11 dev.
- Updated local environment: Laravel 12.70.0, Filament 4.15.2, Testbench 10,
  Pest 3 / PHPUnit 11.
- Final local suite: 179 tests / 666 assertions, all passing.
- Freshly resolved local dependencies: `composer audit` reports no advisories.
- Compatibility CI added; the full matrix has not yet run on GitHub.
- Tests use SQLite and a real local Redis server (see the 2026-10-22 follow-up
  below; the original note said "mocked Redis", which was wrong). MySQL and
  PostgreSQL were not exercised in this audit.

## Confirmed findings addressed

| Area | Finding | Resolution |
| --- | --- | --- |
| Access / collection | String `false` kept the master switch enabled | Normalize false-like strings; regression tests |
| Retention | Metrics retention of zero deleted data despite completed runs preserving it | Zero disables pruning for both stores |
| Database jobs | Unix timestamps were parsed as dates | Use timestamp parsing |
| Failed jobs | Counts could use the default rather than monitored connection | Resolve the monitored database queue connection |
| Failed jobs | Array provider records and malformed command payloads caused errors | Normalize record access and validate command type |
| Mutations | Cached failures remained stale after retry/delete | Invalidate queue and failure caches |
| Discovery | Empty configured queue array produced an undefined index | Safe default fallback |
| Migrations | Legacy truncated unique-index name was not recognized | Consistent naming and legacy-name detection |
| Dependencies | Laravel 12 was claimed but excluded by dev Testbench constraints | Allow Testbench 10 |
| Test setup | Filament 4 route and icon APIs broke tests | Version-aware route registration and icon providers |
| Release setup | No release policy, tags, changelog or CI | Prepared SemVer workflow and compatibility/security jobs |
| Documentation | Wrong Composer package name and contradictory storage guidance | Corrected README |

## Remaining limitations and operational risks

1. **Migration rollback with multiple job classes:** rolling back the job-column
   migration reinstates uniqueness on connection/queue/minute. Existing rows for
   multiple job classes conflict. Do not roll this migration back against such
   data without an explicit backup and aggregation plan; silently merging or
   deleting statistics would be a destructive policy change.
2. **Already-applied legacy migration:** editing a migration does not rerun it.
   If a long custom table name was used and the old unique index remained after
   the job-column migration, inspect the table indexes. Remove the old
   connection/queue/period unique index only after confirming the replacement
   connection/queue/job/period unique index exists. No automated destructive
   repair is included.
3. **Legacy framework security:** the original local Laravel 11 dev installation
   had Laravel security advisories, including CRLF injection. Current local
   dependencies resolve cleanly after updating. This does not patch consumer
   applications. Laravel 10/11 remain legacy compatibility targets; prefer a
   currently patched framework. Compatibility CI permits resolving legacy
   dependencies with advisories; the separate current-dependency security job
   does not suppress advisories.
4. **Payload privacy and volume:** completed-run payload storage defaults to on.
   It can persist secrets and grow quickly. Consider
   `QUEUE_MONITOR_STORE_PAYLOAD=false`, restrict access, and run pruning.
5. **No published release:** `0.1.0` is a proposed starting point. Maintainer
   review and passing CI are required before tagging or publishing.


# Follow-up audit — 2026-10-22

## Verification

- Suite: 207 tests / 733 assertions passing with SQLite, local Redis and a
  MySQL 8.0 container (`QUEUE_MONITOR_TEST_MYSQL_HOST`); 204 + 3 skipped
  without MySQL.
- Worker-level regressions run a real `queue:work --once`. Each new
  regression test was checked to fail with its fix removed.
- PostgreSQL, SQL Server, MariaDB and Laravel 10 were not run locally; CI covers
  Laravel 10 on SQLite only.

## Findings fixed

| Severity | Finding | Fix |
| --- | --- | --- |
| High | Migrations failed on MySQL utf8mb4: the connection/queue/job/period unique index needed 4080 of 3072 bytes | Sized key columns; MySQL tables narrowed before the index is added |
| High | Metric writes failed on MySQL with `use_upsert_alias` (ambiguous columns) | Existing row qualified by table, incoming row by alias |
| High | Widgets visible to every panel user on Filament's default dashboard | `canView()` uses `Access::canAccess()` |
| High | Lists, failed jobs and the activity widget loaded every job and failure into memory on each poll | Bounded reads (`list_limit`), SQL limit for database failers, per-request memo, truncation notice |
| Medium | MySQL runtime average weighted by already-incremented counts | Average assigned before the counts |
| Medium | Listener exceptions surfaced as job exceptions; a schema check before the job could release or fail it unrun | Writes and schema check isolated and reported |
| Medium | `QUEUE_MONITOR_DRIVER=<connection>` ignored by drivers/pages; Redis driver defaulted to the `default` Redis connection | `MonitoredConnection` resolver used everywhere |
| Medium | Redis reservation time read from the expiry score (+`retry_after`) | Score minus `retry_after`; stuck count adjusted |
| Low | Retry unserialized every class in the command | `allowed_classes` limited to the command class |
| Low | Viewing and acting on failed jobs shared one permission | `authorize_actions` |
| Low | Payloads stored by default | `store_payload` defaults to `false` |
| Low | Missing table cached as missing for the worker's lifetime | Rechecked after 60 s |
| Low | Sync jobs recorded inside web requests | Skipped |
| Low | Test suite flushed Redis database 0 on the developer's machine | Tests use database 15 |

Correction to the first report of this follow-up: a failing `JobProcessed`
write does not mark the job failed or call `failed()`, because `Job::fail()`
returns early for a deleted job. The effects are the ones listed above.

## Remaining risks

1. **Laravel 10:** the job-column migration uses `Schema::hasIndex()`, which
   may not exist on Laravel 10. Unverified locally; the Laravel 10 CI job will
   show it.
2. **In-memory lists:** search, filters and sorting still run in PHP over at
   most `list_limit` rows, so entries beyond the limit are not searchable.
3. **Payloads already stored** before the default changed stay until pruned.
