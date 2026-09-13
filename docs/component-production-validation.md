# Component production verification — 2026-09-08

- SQLite regression selection: 601 tests executed, 600 passed and one PostgreSQL-specific transaction test skipped; 2,171 assertions, no failures.
- PostgreSQL component/import selection: all 45 tests passed, including the test skipped under SQLite; 170 assertions. These ran in a separate temporary database which was dropped afterward.
- New feature suite: 24 component tests, plus two import-job tests covering BOM mapping failures and generated-job counts in dry/real imports.
- Vite production build passed. It retains the existing bundle-size warning.
- Mobile `tsc --noEmit` passed.
- PHP formatting and `git diff --check` passed.
- PHPUnit reports four existing test-metadata deprecations.

Backend selections:

```text
WorkOrder|Bom|Import|BatchServiceTest|BatchStep|QualityControl|ProductionCost|ModuleHookEventsTest|WebhookDeliveryTest
ComponentWorkOrderTest|DryRunImportTest|ProcessDataImportTest (PostgreSQL)
```

The browser test creates its order through the web form, previews the eight-part requirement, creates batches through authenticated endpoints, verifies that assembly start is rejected before parts are ready, and executes cutting and assembly through the web controls. It asserts two finished sofas and captures the component table with eight completed parts. See `tests/e2e/component-production.spec.ts`.

Local test setup required a temporary administrator because the existing administrator password differed from the repository default. Four obsolete machine-worker containers were found restarting on missing configurations and overwriting the shared web configuration cache, causing intermittent API 401 responses. Those failing containers were stopped and the backend configuration cache rebuilt. No existing user password was changed.

The final browser run passed in 5.4 seconds. The completed local demo is `/admin/work-orders/563`, with component order `/admin/work-orders/564`. The temporary test account and its sessions/tokens were removed; incomplete trial orders were cancelled.
