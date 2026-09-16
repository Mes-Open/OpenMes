# PostgreSQL test failures — 2026-09-16

The nine failures previously reproduced on baseline `cca82cab` had four causes.

| Area | Failures | Cause and fix |
| --- | ---: | --- |
| Invalid timezone fixture | 1 | The test inserted a bare string into a JSON column. Encode the deliberately unknown timezone as JSON; application timezone logic is unchanged. |
| Preset installer | 4 | Tests forced the SQLite preset while the suite supplied PostgreSQL connection settings. Use the suite's actual driver so both supported presets are exercised in their respective runs. |
| Sample-data replacement | 3 | Tests mocked schema replacement/seeding but still purged the real connection, rolling back their uncommitted fixtures. Mock both connection resets alongside the mocked Artisan work. |
| Expired demo tenant pruning | 1 | Real PostgreSQL cascade-order issue: clearing a checklist's user reference could run after its batch disappeared. Delete the expired tenant's users first, then the tenant, within the same transaction. |

## Additional regression coverage

- Pruning removes the expired tenant, its users, batches and checklists while keeping unrelated users.
- A retained machine-counter reference blocks deletion and rolls back user deletion; another
  eligible tenant can still be pruned. Audit retention is not bypassed.
- A separate integration test performs **real** schema replacement and seeding without a
  surrounding test transaction. It checks removal of the old admin, the replacement admin's
  password, Admin role and authenticated session, bakery product data, and installed modules.
- The replacement test discards its disposable database during cleanup rather than running
  historical downgrade migrations. Production downgrade support is not established by this test.

## Scope

The only application behavior change is the transaction ordering in `tenants:prune`.
Production counting, flow settings, timezone behavior and sample-data replacement code are unchanged.
No schema migration is required for this fix.

Tests run against disposable databases and source snapshots containing only tracked project
files plus this change's new integration test. Unrelated untracked workspace changes are excluded.
The test runtime has GD WebP enabled, as in the preceding verification. This does not add WebP
support to the repository's production image.

A production-sized migration rehearsal was not performed: no production database copy is
available. Functional migration tests cannot establish real-data locking duration or downtime.

## Final verification

- SQLite: **2,718 tests / 11,268 assertions**, zero errors/failures. Five existing PHPUnit
  deprecations, one skipped test and the existing assertion-free ModuleSelectionTest remain.
- PHP formatting and staged whitespace checks passed.
- PostgreSQL 17: **2,718 tests / 11,269 assertions**, zero errors/failures. The same five
  PHPUnit deprecations and assertion-free test remain. All nine previously failing tests pass.
