# Production flow deployment and rollback

The quantity-ledger migration is
`2026_09_15_100000_add_flow_ledger_to_batch_steps.php`.
It changes step counters to decimals, adds step scrap and permits unclassified scrap reasons.
Whole-batch mode remains the default until transfer mode is enabled in production settings.

## Upgrade

1. Stop production writes, including operator requests, connectivity pollers and queue workers.
   HTTP maintenance mode alone does not stop machine or background writes.
2. Take and verify a database backup. Rehearse the migration with a production-sized copy to
   measure ALTER TABLE/backfill locking time; the test database does not establish downtime.
3. Deploy the backend and built frontend, then run `php artisan migrate --force`.
4. Restart long-running application/queue/connectivity workers with the new code.
5. Verify whole-batch operation, then enable transfer mode during a controlled production pause.
   Confirm existing batch quantities and machine counter semantics before resuming production.

## Schema rollback policy

A downgrade is permitted only while the old schema can represent the stored values. Before
any schema modification, the migration refuses rollback if any of these exist, including
soft-deleted rows:

- a scrap entry with no reason;
- a nonzero step scrap counter;
- fractional, negative or greater-than-2147483647 passed quantities.

Refusal leaves the schema and production facts intact and tells the operator how to proceed.
The policy deliberately avoids assigning a fabricated scrap reason, dropping scrap history
or rounding production quantities just to make rollback succeed. Compatible data can roll
back and migrate forward again; this is covered on SQLite and PostgreSQL.

Once incompatible production data exists, use a forward correction. If a return to the old
schema is unavoidable, stop all writers, preserve a backup/export of the current state,
restore the verified pre-upgrade database together with its matching application version,
and reconcile production recorded since that backup before resuming. Restoration alone does
not preserve post-backup production. Do not bypass the rollback guard or assume deploying an
older application against this schema has been validated.

## Backward-compatible upgrades and optional channel migration

The additional migration is `2026_09_15_120000_create_machine_counters.php`. It introduces
durable channel state and retained reading/reconciliation history. **Existing machine integrations
keep their legacy behaviour after an upgrade.** Opening a source in Machine counters does not
opt it in. No new timestamps, event IDs or assignments are required for legacy whole-batch
counting. Previously configured explicit channels remain explicit.

Enable **explicit counting** for one channel at a time when ready. MQTT, Modbus and OPC UA all
support the explicit pipeline. Its assignment, timestamp and event-ID requirements below apply
only after that channel opts in. Legacy counting keeps its previous reset/routing assumptions;
those reliability improvements are part of the explicit mode.

Production flow is currently **system-wide**, not per order or per channel. Migrate channels
while whole-batch flow remains enabled. The web and API settings endpoints refuse transfer
flow when open machine-counted work has incompatible legacy channels or its existing output
does not match the step ledger. Finish/reconcile that work first. Runtime guards also prevent
legacy sources from writing a transfer ledger if settings are changed outside those endpoints.
Manual-only orders do not require machine-channel migration.

Pause acquisition around a channel switch. To opt in, choose its configuration, enter a reason
and click **Enable explicit counting**. To opt out, use **Return to legacy counting** with a
reason while in whole-batch flow. Both transitions retain audit history. Returning a tag to
legacy mode clears its old cache baseline; its next reading establishes the legacy baseline
without replaying the interval spent in explicit mode.

1. In **Connectivity → Machine counters**, open each good/reject/cycle tag or production MQTT
   mapping. Select its mode and quality meaning explicitly.
2. Set its workstation and the exact batch step. Multiple batches can run at a workstation;
   a channel targets only its explicitly assigned step. Only one good-count channel may be
   assigned to a step. Unassign that channel before replacing it with another source.
3. In explicit mode, use an **exact MQTT topic**, without `+` or `#`. Legacy wildcard topics
   continue working until migrated. Existing `line_id`, `step_number`,
   `order_no`, `order_id` and `also_count_work_order` counting hints no longer choose the target.
   The final effective step owns finished output; intermediate counts never add order output.
4. Supply acquisition timestamps on **all** count readings. Gateways use `ts`; MQTT defaults
   to `$.timestamp` (or `action_params.timestamp_path`). Use ISO 8601 with a timezone, preferably
   UTC. Preserve the acquisition time across retries; synchronise device clocks. The Modbus
   poller stamps the time it acquires a register reading.
5. For increment/pulse channels, also supply a stable event ID: gateway `event_id`, MQTT
   `$.event_id` (or `action_params.event_id_path`). IDs must remain identical on retries and
   must not be reused for new events, including after device restart or batch changes.
   **A polled boolean is not a deduplicated pulse:** use a cumulative register or a publisher
   that provides a distinct event ID and acquisition time for each pulse. A pulse has value 1.
6. Send the first cumulative reading as a baseline. Verify the next known increment against the
   physical machine and the intended step. The baseline is independent of existing order
   output; 50,000 then 50,003 means 3 new pieces, even if order output was already nonzero.
7. Resume the migrated channel only after checking assignment, quality meaning and timestamp/event-ID
   delivery. Batch changes require explicit reassignment. Stop or establish a known boundary
   at the machine when changing batches: output between reassignment and the next cumulative
   baseline is intentionally not inferred. Reconcile that transition using physical evidence.

Counts use the ledger's two-decimal quantity precision. Negative, nonnumeric, over-range and
higher-precision values are retained as invalid; they do not advance the baseline. Accepted
raw values are limited to 999,999,999,999.99. Excess production beyond the step's available
quantity remains recorded as a partial reading for review.

Only **good** counts advance the step. Total counts with unknown quality and reject counts are
retained for quality review; they do not automatically create good output or step scrap.
Scrap reporting/classification continues through the existing production workflow. Legacy
MQTT direct step-completion/order-status mappings are rejected for transfer-ledger orders;
complete them through that workflow so quantity, blocking and completion rules run.

## Machine concurrency and recovery

In explicit mode, a channel row lock serializes its baseline, assignment and event-ID lookup.
That same lock serializes both modes with configuration changes, so an in-flight legacy
reading cannot race an opt-in and also be processed by the explicit path. Production then
locks the order and step in the same order used by the ledger. The baseline change, accepted
production and reading history commit together. Cache flushes and worker restarts do not
lose baselines or duplicate-detection history.

A decreasing cumulative reading freezes the channel until reviewed. Later larger values do
not silently resume counting. Reset acknowledgement clears the baseline; the next fresh
reading establishes it without adding output. Source/address/transform/mapping changes require
saving and validating configuration again, rather than bypassing that check with a reset.
Pause acquisition when changing source settings, restart/reload the gateway or poller, and then
verify a fresh baseline before resuming. The built-in Modbus path also rejects readings decoded
with a stale tag or transport snapshot; external gateways must honour their refreshed config.

Unassigned, blocked and partial readings stay in **Awaiting review**. Supervisors can apply an
entire remaining good delta to a compatible open step, or dismiss it with a reason. Original
readings retain their original attribution; reconciliation is appended separately. A failed
reconciliation rolls back any partial production write. No automatic replay occurs on resume.

The counter migration refuses rollback once reading/audit history exists, including initial
configuration. Use a forward migration or the verified backup-recovery process above. Deleting
history or baselines to make a rollback succeed can reintroduce duplicate production. Source
soft deletion remains available; hard purges are constrained while counter evidence references
those sources.

For an isolated browser rehearsal, follow [the browser test guide](machine-counter-browser-tests.md).
