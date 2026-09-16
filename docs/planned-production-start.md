# Planned production start

Work orders have an optional `planned_start_at` date and time, expressed in the
configured plant timezone. The field is available in the shared creation form,
Admin/Supervisor edit forms, the planner editor and the work-order API.

## Availability

- A future start keeps the order out of the operator queue, workstation table,
  their update checks and the operator work-order API list.
- At the start instant, the order becomes available under the normal line,
  station and status rules. This does not automatically start production.
- An empty start retains immediate availability, including existing orders.
- Visible operator pages refresh at most every 30 seconds and also react to live
  changes. A tab refresh or returning to the tab rechecks availability.
- Direct starts and output writes are guarded on the server, including batch
  steps, whole-order quantities, shift entries, API transitions and machine
  counting. Rejected shift writes leave no quantity entry behind.
- Once production has started, moving its start into the future is rejected.
  Planner undo follows the same rule. Scheduler writes and production entry
  paths lock the order to serialize concurrent changes.

## Scheduling

`due_date` remains the delivery deadline. A primary planner move changes
`planned_start_at` and preserves that deadline. A day/shift move uses the selected
shift's start time, or midnight when no shift definitions exist; an hourly move
sets an exact interval. Extra line segments retain their independent placement
fields. Unscheduling preserves the deadline and release time while removing the
line placement.

For legacy orders without `planned_start_at`, the planner retains its existing
placement fallback to the deadline/week. That fallback does not postpone operator
availability. Capacity calculations and their drill-down use the planned start
when present.

Start-only orders show their actual start in the hourly view with an unspecified
end. Their placeholder width does not represent a booked duration or conflict.
No migration is required: the existing minute-planning columns and index are used.

## Manual browser check (Polish UI)

1. In **Zlecenia → Nowe zlecenie**, select a line, set quantity to 10 and choose
   **Operator (ręcznie)**. Set **Planowany start** to tomorrow at 08:15 and
   **Termin** to a later date. The timezone is shown beside the start input.
2. Save and reopen the edit form. Confirm that date, time and deadline are retained.
3. In **Harmonogramowanie**, find the order on its start day. Move it to another
   day/shift and reopen its editor: the start changes and the deadline remains.
4. Log in as an operator on that line. Confirm that this future order is absent
   from **Kolejka** and **Stanowisko**, while an existing undated order is present.
5. As Admin, change the start to the next minute. Leave the operator workstation
   visible. The order appears after the start, within the normal 30-second refresh.
6. Record one good piece using the appropriate whole-order or step action.
7. Try moving that started order to tomorrow. A validation message appears and
   its saved start/output remain unchanged.
8. Test an empty start on a separate pending order: it is immediately available.

Local E2E fixture: `TEST-PLANNED-START-PR300`, order 70. It was created through the
browser and left in progress with one manually recorded piece. Other existing
operator fixtures were not changed. The local plant was configured to UTC; the
implementation uses the configured timezone rather than hard-coding UTC.

## Verification — 2026-09-16

| Check | Result |
| --- | --- |
| Full SQLite suite | 2,730 tests; 11,390 assertions; no failures/errors |
| Full PostgreSQL 17 suite | 2,730 tests; 11,391 assertions; no failures/errors |
| Frontend Vitest | 139 tests passed |
| Vite production build | Passed |
| Mobile TypeScript (`tsc --noEmit`) | Passed |
| English/Polish key parity and duplicate checks | Passed |
| PHP Pint and `git diff --check` | Passed |

The full database suites ran in isolated snapshots/databases, with final changed
PHP files verified byte-for-byte against the workspace. Existing suite warnings
remain: five PHPUnit deprecations and one test without assertions; SQLite also
skips one database-specific test. Browser E2E used the visible `playwright-iso`
browser as Admin and Operator, including creation, day/shift and hourly dragging,
unchanged deadlines, hidden future orders, direct early-write rejection, automatic
release at 10:33 UTC, successful output after release, rejection of postponement
after output, and a 390-pixel-wide form check.
