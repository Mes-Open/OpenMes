<?php

namespace App\Sync;

use App\Events\Machine\ShiftMonitorChanged;
use App\Models\Batch;
use App\Models\MachineEvent;
use App\Models\ProductionDowntime;
use App\Models\QualityCheck;
use App\Models\WorkstationState;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Write-path for the live shift monitor: the three tables it derives from, each
 * nudging its workstation's channel when a row lands.
 *
 * Hooked on the models rather than called from the services that write them —
 * the state machine, the signal ingestor, the classify endpoint and the demo
 * seeder all reach the same tables, and none of them should have to know a UI
 * channel exists. Mirrors CollectionBroadcaster, which does the same for synced
 * collections.
 *
 * Booted from AppServiceProvider::boot().
 */
class ShiftMonitorBroadcaster
{
    /**
     * model => [events to hook, reason tag, station resolver].
     *
     * Only `created` for the append-only event store; states and downtimes also
     * update in place (a slice closing, a stop being given its cause), and those
     * updates are exactly what the monitor needs to redraw.
     *
     * Batches and quality checks are on the screen too — the batch strip and the
     * event pins — and they move on their own schedule, not the machine's: a
     * check signed off while the line is stopped produces no counter event to
     * ride along with, and without a nudge of its own it would sit invisible
     * until the fallback poll came round.
     *
     * @return array<class-string, array{0: array<int, string>, 1: string, 2: (callable(mixed): ?int)|null}>
     */
    private static function map(): array
    {
        return [
            WorkstationState::class => [['created', 'updated'], 'state', null],
            ProductionDowntime::class => [['created', 'updated'], 'downtime', null],
            MachineEvent::class => [['created'], 'counter', null],
            Batch::class => [['created', 'updated'], 'batch', null],
            // A check carries no station of its own; the batch it was taken on
            // says where it happened. One extra read per check, and checks are
            // signed off a handful of times a shift.
            QualityCheck::class => [['created'], 'quality', fn (QualityCheck $c) => $c->batch?->workstation_id],
        ];
    }

    /**
     * Stations with a nudge already queued for the current commit.
     *
     * One state transition writes four rows — it closes the previous slice,
     * opens a new one, opens or closes a downtime, and records an event — all
     * inside `WorkstationStateMachine`'s transaction. Those are four identical
     * messages on one channel, and the client coalesces them into a single
     * re-fetch anyway, so only the first is worth sending.
     *
     * @var array<int, true>
     */
    private static array $queued = [];

    public static function boot(): void
    {
        foreach (self::map() as $model => [$events, $reason, $station]) {
            foreach ($events as $event) {
                $model::{$event}(function ($row) use ($reason, $station) {
                    self::nudge($row, $reason, $station);
                });
            }
        }

        // A rollback discards the after-commit callbacks, including the one that
        // would have cleared the flag. Without this the station stays marked as
        // "already queued" for the life of the process — which under Octane or a
        // queue worker means its channel goes permanently silent after a single
        // failed transaction. The rolled-back writes never happened, so dropping
        // the whole window is right: there is nothing left to announce.
        Event::listen(TransactionRolledBack::class, fn () => self::$queued = []);
    }

    /**
     * Queue the nudge for after the write commits.
     *
     * After-commit rather than inline for two reasons: the push is a
     * synchronous HTTP call to Reverb, which has no business happening while
     * the originating transaction still holds its row locks; and a client told
     * to re-fetch before the commit lands would read the old state. Outside a
     * transaction the callback runs immediately, so a lone write still pushes
     * at once — and the dedupe window clears with it, which is what keeps this
     * correct in queue workers and tests, where there is no request boundary.
     * A rollback clears it instead (see boot()).
     */
    private static function nudge($row, string $reason, ?callable $station = null): void
    {
        $workstationId = (int) ($station ? $station($row) : $row->getAttribute('workstation_id'));

        if (! $workstationId || ! self::isInteresting($row) || isset(self::$queued[$workstationId])) {
            return;
        }

        self::$queued[$workstationId] = true;

        DB::afterCommit(function () use ($workstationId, $reason) {
            unset(self::$queued[$workstationId]);

            // Best-effort: an unreachable Reverb must never fail the ingest that
            // triggered it, and the client's periodic re-fetch covers the gap.
            try {
                event(new ShiftMonitorChanged($workstationId, $reason));
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    /** Whether this write changed anything the monitor actually draws. */
    private static function isInteresting($row): bool
    {
        // Telemetry is the highest-frequency signal on the ingest path and
        // reports temperatures and pressures, not state, output or stops.
        if ($row instanceof MachineEvent) {
            return $row->event_type !== MachineEvent::TYPE_TELEMETRY;
        }

        // A telemetry sample also lands as a metadata refresh on the open state
        // slice (MachineSignalIngestor::handleTelemetry), which would otherwise
        // walk straight past the check above. Only the timeline columns matter.
        if ($row instanceof WorkstationState && $row->exists && ! $row->wasRecentlyCreated) {
            return $row->wasChanged(['state', 'started_at', 'ended_at', 'duration_seconds']);
        }

        return true;
    }
}
