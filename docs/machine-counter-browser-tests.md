# Machine counter browser checks

## Ready-to-use local demo

Log in as an admin or supervisor and open:

**http://localhost:8080/admin/connectivity/counters?counter=2**

This session left that fresh demo at zero. Counter **1** contains the completed automated
walkthrough and its audit history. These IDs belong to this development database only.
The demo has its own line, product, order and two batches of **10 pieces each**, sharing one
station. It does not connect to a PLC or broker. The page is also linked from
**Connectivity → Overview → Machine counters** (Polish: **Połączenia → Przegląd → Liczniki maszyn**).

For another fresh demo, run from the repository root on the local development installation:

```sh
docker exec openmmes-backend php artisan machine-counters:demo 1 --force
```

`1` is this installation's admin user ID. The command prints a new URL and never resets existing
data. `--force` is needed because this local Docker instance uses `APP_ENV=production`; do not
use this command against your production database. Simulation controls are restricted to these
CLI-created test sources. Normal machine channels cannot enable simulation through the web UI.

## Walkthrough

Use **Raw count / Wartość surowa → Send test reading / Wyślij odczyt testowy**.
Leave the event ID and timestamp empty until instructed otherwise. The simulator stamps the
current acquisition time; real gateways must supply their acquisition timestamp.

| Step | Browser action | Expected result |
|---|---|---|
| 1 | Send **1000**, then **1003**. | First reading establishes the baseline: **0** good. Second reading: batch 1 has **3** good. |
| 2 | Send **1003** again. | Still **3** good; history says **Unchanged / Bez zmian**. |
| 3 | Send **2**, then **1010**. | Both leave output at **3**. **Reset review required** remains active even after the raw count exceeds the old value. |
| 4 | Enter a reset reason and click **Establish new baseline / Ustal nową wartość bazową**. Send **10**, then **12**. | 10 becomes the new baseline with no output. 12 adds 2: batch 1 now has **5** good. |
| 5 | Select **Demo batch 2** under **Assigned batch step**, enter a reason, save configuration. Send **20**, then **22**. | Batch 2 starts at **0**, then reaches **2**. Batch 1 stays at **5**. |
| 6 | Select **Unassigned / Nieprzypisane**, enter a reason, save. Send **30**, then **33**. | 30 is a baseline; the delta of **3** is retained as unassigned, with **0 applied**. Neither batch changes. |
| 7 | On that unassigned row, click **Review / Przejrzyj**. Choose **Apply remaining good quantity**, select batch 2, enter a reason, save. | A separate **Reconciled / Uzgodniono** audit row applies **3** to batch 2. The original row retains its original attribution and cannot be reviewed twice. |
| 8 | Assign the channel back to batch 2 and save with a reason. | Batch 2 shows **5** good. Assigning a channel does not replay old readings. |
| 9 | Change mode to **Pulse / Impuls**, keep quality **Good**, save with a reason. Send **1** with an empty event ID. | Nothing counted; history says **Event ID required**. |
| 10 | Send **1** with ID `manual-pulse-1`; repeat it; send **1** with ID `manual-pulse-2`. | Batch 2 progresses **6 → 6 → 7**. Reusing an event ID returns the existing reading; it creates no second production entry. |
| 11 | Send 1 with a new ID and timestamp `2020-01-01T12:00:00Z`; then another new ID with `2099-01-01T12:00:00Z`. Clear the timestamp afterward. | Both are retained as out-of-order/future readings. Output stays at **7**. |
| 12 | Change mode to **Increment / Przyrost**, save, send **5** with ID `manual-overflow`. | Batch 2 reaches its **10-piece** limit. History records delta **5**, applied **3**, status **Partially applied**. The remaining 2 require explicit review. |
| 13 | Change to **Cumulative** and quality **Total (quality unknown)**, save, send **100**, then **103**. | No additional good output; the three unclassified pieces are retained for quality review. Repeat with quality **Reject**, values **0 → 2**: still no good output. |
| 14 | Click **Awaiting review / Do weryfikacji**. Review a quality row and choose **Dismiss with reason**. | The row leaves the pending queue, and its reason remains in **All readings**. Total/reject readings cannot be silently converted into good output. |

The current step's good count appears above its configuration. To inspect the other batch,
select that batch in configuration and save with a reason. This establishes a new counting
boundary, so subsequent cumulative readings need a fresh baseline.

Try an unrelated workstation in configuration: saving must show a form error and preserve the
existing assignment. At a mobile viewport, the forms must fit; the history table scrolls
horizontally inside its own container.

## Production-flow checks from the earlier changes

On an isolated routed order with **Transfer** enabled under **Settings → System**:

1. Start the first step of a 10-piece batch; log **4 good + 1 scrap**.
2. The next station becomes ready with **4 incoming** while the first step is still running.
3. At the next step, attempting to log more than those 4 must fail. Log 2 good and check that
   only 2 arrive downstream. Final order output comes only from the final effective step.
4. Attempt to finish the first step with 5 pieces still waiting: it must fail. Record the
   remaining quantity before finishing. Downstream completion also waits for upstream closure.
5. Pause the order or open a blocking issue/QC task: output logging must stop.
6. Classify a generated scrap entry: the reason can change, but its quantity, step/order
   association and deletion must remain protected.
7. Open two operator sessions on the same line. A valid quantity change must refresh the other
   view; a session on another line must not receive that private line update.

Do not change the global production-flow setting while real work is running.

## Recording and verification

The visible native `playwright-iso` walkthrough was recorded with Playwright's `recordVideo`
context option, saved as `docs/review-evidence/counter-e2e-2026-09-15/counter-walkthrough.webm`.
Screenshots in the same directory cover the baseline/reset, assignment/reconciliation,
quantity caps, quality handling and a 390-pixel mobile viewport. These local evidence files
are intentionally excluded from the application commit.

See [deployment requirements](production-flow-deployment.md) before enabling machine writes.
