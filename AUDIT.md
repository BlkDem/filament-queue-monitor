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
- Tests use SQLite and mocked Redis; real Redis, MySQL and PostgreSQL have not
  been exercised in this audit.

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
