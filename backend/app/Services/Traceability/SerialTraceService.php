<?php

namespace App\Services\Traceability;

use App\Models\BatchStep;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\Pallet;
use App\Models\ScrapReason;
use App\Models\SerialUnit;
use App\Models\SerialUnitComponent;
use App\Models\UnitStepHistory;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\UnitSerialisation;
use Illuminate\Support\Facades\DB;

/**
 * Per-unit (serial) genealogy. Registers serialised units and records a
 * "birth certificate" entry each time a unit is processed at a workstation,
 * with a parameter (sensor/measurement) snapshot.
 */
class SerialTraceService
{
    /**
     * Register a new serialised unit (or return the existing one for the serial).
     */
    public function registerUnit(string $serialNo, array $attributes = []): SerialUnit
    {
        // Tenant comes from the global scope on the lookup and from HasTenant on
        // the insert - naming it here would search "tenant X AND tenant NULL".
        return SerialUnit::firstOrCreate(
            ['serial_no' => $serialNo],
            [
                'psn' => $attributes['psn'] ?? null,
                'work_order_id' => $attributes['work_order_id'] ?? null,
                'batch_id' => $attributes['batch_id'] ?? null,
                'material_id' => $attributes['material_id'] ?? null,
                'status' => $attributes['status'] ?? SerialUnit::STATUS_IN_PRODUCTION,
                'produced_at' => $attributes['produced_at'] ?? null,
                'extra_data' => $attributes['extra_data'] ?? null,
            ]
        );
    }

    /**
     * Register a sub-assembly by its own serial where it is made (a board, a
     * module), so the product it goes into later is linked to a unit with a
     * history - not to a bare number. It has no process serial and does not
     * follow the product's serial format; it is complete once registered.
     *
     * @throws BindingException when the serial is already taken
     */
    public function registerSubassembly(string $serialNo, Material $material, WorkOrder $workOrder, User $operator, ?int $workstationId = null): SerialUnit
    {
        $serialNo = app(UnitSerialisation::class)->normalize($serialNo) ?? '';

        return DB::transaction(function () use ($serialNo, $material, $workOrder, $operator, $workstationId) {
            if ($serialNo === '') {
                throw new BindingException(__('Scan the serial number of the sub-assembly.'));
            }
            $owner = SerialUnit::where('serial_no', $serialNo)->lockForUpdate()->first()
                ?? SerialUnit::where('psn', $serialNo)->lockForUpdate()->first();
            if ($owner) {
                throw new BindingException(__('Serial :sn is already registered (:material, order :order).', [
                    'sn' => $serialNo,
                    'material' => $owner->material?->name ?? '—',
                    'order' => $owner->workOrder?->order_no ?? '—',
                ]));
            }

            try {
                $unit = SerialUnit::create([
                    'serial_no' => $serialNo,
                    'material_id' => $material->id,
                    'work_order_id' => $workOrder->id,
                    'status' => SerialUnit::STATUS_COMPLETED,
                    'produced_at' => now(),
                ]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                // The same label scanned at two benches at once: one wins, the other is told.
                throw new BindingException(__('Serial :sn is already registered (:material, order :order).', ['sn' => $serialNo, 'material' => '—', 'order' => '—']));
            }
            $this->recordStep($unit, $operator, null, [
                'workstation_id' => $workstationId,
                'parameters' => ['event' => 'subassembly_registered', 'material' => $material->code],
            ]);

            return $unit->fresh();
        });
    }

    /**
     * Record a processing event for a unit at a workstation. The high-precision
     * timestamp guarantees ordering even for sub-second consecutive steps.
     */
    public function recordStep(SerialUnit $unit, User $operator, ?BatchStep $step, array $data = []): UnitStepHistory
    {
        return DB::transaction(function () use ($unit, $operator, $step, $data) {
            $entry = $unit->history()->create([
                'batch_step_id' => $step?->id,
                // `exact_workstation`: the caller knows the bench, or knows it is unknown
                // (a tester log from an unregistered station) - no fallback to the operator's.
                'workstation_id' => ! empty($data['exact_workstation'])
                    ? ($data['workstation_id'] ?? null)
                    : ($data['workstation_id'] ?? $step?->workstation_id ?? $operator->workstation_id),
                'operator_id' => $operator->id,
                'parameters' => $data['parameters'] ?? null,
                'result' => $data['result'] ?? null,
                'notes' => $data['notes'] ?? null,
                // An import passes the tester's own time so history reads in the
                // order things happened, not the order files were loaded.
                // Stored in the app's timezone whatever zone the caller's stamp
                // carries (a tester's "…Z"), so history sorts with station rows.
                'processed_at' => ($data['processed_at'] ?? now())->copy()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s.u'),
            ]);

            if (($data['result'] ?? null) === 'fail' && ($data['parameters']['event'] ?? null) !== 'scrapped') {
                $this->applyFailPolicy($unit, $entry, $operator);
            }
            // A hold from failed tests waits for a retest: a pass lifts it (policy
            // `block`) and the unit goes back to where it was - packed units stay
            // packed. A hold someone put on by hand (a non-conformity) is not a
            // test's to lift.
            $unit->refresh();
            // Only a test's pass - not an inspection or step marked "pass" elsewhere.
            if (($data['result'] ?? null) === 'pass' && ($data['parameters']['event'] ?? null) === 'test'
                && $unit->status === SerialUnit::STATUS_BLOCKED
                && ($unit->extra_data['hold']['source'] ?? 'test') !== 'manual'
                && $this->passLiftsHold($unit->extra_data['hold'] ?? [], $entry)) {
                $unit->update([
                    'status' => $unit->packed_at ? SerialUnit::STATUS_COMPLETED : SerialUnit::STATUS_IN_PRODUCTION,
                    'extra_data' => array_diff_key($unit->extra_data ?? [], ['hold' => true]) ?: null,
                ]);
                // The release shows in the history next to the pass that made it.
                $this->recordStep($unit, $operator, null, [
                    'workstation_id' => $entry->workstation_id,
                    'exact_workstation' => true,
                    'processed_at' => $entry->processed_at,
                    'parameters' => array_filter(['event' => 'unblocked', 'hold_source' => 'test', 'released_by' => 'test', 'station' => $entry->parameters['station'] ?? null]),
                ]);
            }
            // A verdict on a unit already on a pallet changes what that pallet may
            // do. Read the pallet from the database: the caller's instance may
            // predate the packing.
            if (($data['result'] ?? null) !== null) {
                $palletId = SerialUnit::whereKey($unit->id)->value('pallet_id');
                if ($palletId) {
                    Pallet::find($palletId)?->recomputeQualityStatus();
                }
            }

            return $entry;
        });
    }

    /**
     * What a failed verdict does to the unit is the plant's call (settings):
     * scrap at once, count attempts and block once the limit is reached so a
     * retest can still clear it, or just keep the verdict. Scrapped is final.
     */
    private function applyFailPolicy(SerialUnit $unit, UnitStepHistory $entry, User $operator): void
    {
        $settings = app(UnitSerialisation::class);
        $policy = $settings->get('unit_test_fail_policy');

        if ($policy === UnitSerialisation::FAIL_SCRAP) {
            // A late or re-imported log does not scrap a unit that already left (shipped) or is scrapped.
            $unit->refresh();
            if (! in_array($unit->status, SerialUnit::TERMINAL_STATUSES, true)) {
                $unit->update(['status' => SerialUnit::STATUS_SCRAPPED]);
            }

            return;
        }
        if ($policy !== UnitSerialisation::FAIL_BLOCK) {
            return; // record only
        }

        $limit = max(1, (int) $settings->get('unit_test_max_attempts'));

        $fails = $settings->get('unit_test_attempts_scope') === UnitSerialisation::ATTEMPTS_TEST
            ? $this->failsSinceLastPassAt($unit, $entry)
            : $unit->history()->where('result', 'fail')->count();
        if ($fails >= $limit && ! in_array($unit->status, SerialUnit::TERMINAL_STATUSES, true)) {
            $unit->refresh();
            $wasBlocked = $unit->status === SerialUnit::STATUS_BLOCKED;
            $unit->update([
                'status' => SerialUnit::STATUS_BLOCKED,
                // A hand-made hold stays what it is; otherwise the tests put it on -
                // remembering which test and when, so only that test's later pass lifts it.
                'extra_data' => array_merge($unit->extra_data ?? [], ['hold' => $unit->extra_data['hold'] ?? array_filter([
                    'source' => 'test',
                    'at' => $entry->processed_at?->toIso8601String() ?? now()->toIso8601String(),
                    'workstation_id' => $entry->workstation_id,
                    'station' => $entry->parameters['station'] ?? null,
                ], fn ($v) => $v !== null)]),
            ]);
            // The hold is a moment in the unit's history, not only its status:
            // it reads right after the fail that put it on, with the count.
            if (! $wasBlocked) {
                $this->recordStep($unit, $operator, null, [
                    'workstation_id' => $entry->workstation_id,
                    'exact_workstation' => true,
                    'processed_at' => $entry->processed_at,
                    'parameters' => array_filter([
                        'event' => 'blocked', 'hold_source' => 'test',
                        'station' => $entry->parameters['station'] ?? null,
                        'failed_attempts' => $fails, 'max_attempts' => $limit,
                    ], fn ($v) => $v !== null),
                ]);
            }
        }
    }

    /**
     * Failed verdicts of this test: at the same station as the verdict just
     * recorded, after that station's last pass (by the tester's own time - logs
     * can arrive out of order). A scrap is not a test and does not count.
     */
    private function failsSinceLastPassAt(SerialUnit $unit, UnitStepHistory $entry): int
    {
        $atStation = fn () => $this->sameTest($unit->history()->reorder(), $entry->workstation_id, $entry->parameters['station'] ?? null);

        $lastPass = $atStation()->where('result', 'pass')->max('processed_at');

        return $atStation()->where('result', 'fail')
            ->where(fn ($q) => $q->whereNull('parameters->event')->orWhere('parameters->event', '!=', 'scrapped'))
            ->when($lastPass, fn ($q) => $q->where('processed_at', '>', $lastPass))
            ->count();
    }

    /**
     * Rows of the same test: the same workstation, or - for a tester the plant
     * has no workstation for - the same station code in its log.
     */
    private function sameTest($query, ?int $workstationId, ?string $station)
    {
        if ($workstationId) {
            return $query->where('workstation_id', $workstationId);
        }

        return $query->whereNull('workstation_id')
            ->when($station !== null, fn ($q) => $q->where('parameters->station', $station), fn ($q) => $q->whereNull('parameters->station'));
    }

    /**
     * Whether a pass lifts a test hold. Counting per test, only a pass of the
     * test that put the hold on, and no older than the failure that did; every
     * failure of the unit counting, any pass does (a hold from before the test
     * was remembered also lifts on any pass).
     */
    private function passLiftsHold(array $hold, UnitStepHistory $pass): bool
    {
        // By the tester's own time in either scope: an older pass arriving late
        // (logs come out of order) does not undo a newer hold.
        $newer = ! isset($hold['at']) || ! $pass->processed_at || $pass->processed_at->greaterThanOrEqualTo(\Carbon\Carbon::parse($hold['at']));
        if (app(UnitSerialisation::class)->get('unit_test_attempts_scope') !== UnitSerialisation::ATTEMPTS_TEST
            || (! isset($hold['workstation_id']) && ! isset($hold['station']))) {
            return $newer;
        }
        $sameTest = isset($hold['workstation_id'])
            ? (int) $pass->workstation_id === (int) $hold['workstation_id']
            : $pass->workstation_id === null && ($pass->parameters['station'] ?? null) === $hold['station'];

        return $sameTest && $newer;
    }

    /**
     * Start a unit on its process serial alone - the line numbers the unit at
     * its first station, before the product label (and its SN) exists. The PSN
     * is the unit's identity until then, so it must be free.
     *
     * @throws BindingException
     */
    public function startUnit(string $psn, User $operator, array $context = []): SerialUnit
    {
        $psn = app(UnitSerialisation::class)->normalize($psn) ?? '';

        return DB::transaction(function () use ($psn, $operator, $context) {
            if ($psn === '') {
                throw new BindingException(__('Scan or issue the process serial first.'));
            }
            $owner = SerialUnit::where('psn', $psn)->lockForUpdate()->first();
            if ($owner) {
                throw new BindingException(__('Process serial :psn already belongs to unit :sn.', ['psn' => $psn, 'sn' => $owner->display_id]));
            }

            $unit = SerialUnit::create([
                'serial_no' => null,
                'psn' => $psn,
                'work_order_id' => $context['work_order_id'] ?? null,
                'status' => SerialUnit::STATUS_IN_PRODUCTION,
            ]);
            $this->recordStep($unit, $operator, null, [
                'workstation_id' => $context['workstation_id'] ?? null,
                'parameters' => ['event' => 'started', 'psn' => $psn],
            ]);

            return $unit->fresh();
        });
    }

    /**
     * Bind a unit's serial number to its process serial number at a station:
     * the moment the pre-printed label goes onto the product. Registers the
     * unit on first sight, records the event in its history, and applies the
     * plant's rules - a process serial owned by another unit is refused when
     * process serials are unique, and a unit already carrying a different
     * process serial is only re-bound with `force`, which callers reserve for
     * supervisors and record with a reason.
     *
     * @return array{unit: SerialUnit, created: bool, rebound: bool, serial_assigned: bool}
     *
     * @throws BindingException
     */
    public function bindProcessSerial(string $serialNo, ?string $psn, User $operator, array $context = []): array
    {
        $settings = app(UnitSerialisation::class);
        $serialNo = $settings->normalize($serialNo);
        $psn = $settings->normalize($psn);

        return DB::transaction(function () use ($serialNo, $psn, $operator, $context, $settings) {
            $unit = SerialUnit::where('serial_no', $serialNo)->lockForUpdate()->first();
            // A unit started on its PSN earlier gets its SN now: the product label.
            $waiting = $psn !== null
                ? SerialUnit::where('psn', $psn)->whereNull('serial_no')->when($unit, fn ($q) => $q->whereKeyNot($unit->id))->lockForUpdate()->first()
                : null;
            if ($unit && $waiting) {
                // Two units would claim one PSN: the one waiting for its label and
                // the one that already carries this SN. A person has to sort that out.
                throw new BindingException(__('Process serial :psn belongs to a unit still waiting for its serial number, and :sn is already another unit.', ['psn' => $psn, 'sn' => $serialNo]));
            }
            $gotSerial = false;
            if (! $unit && $waiting) {
                $unit = $waiting;
                $unit->serial_no = $serialNo;
                $gotSerial = true;
            }

            if ($unit && in_array($unit->status, SerialUnit::TERMINAL_STATUSES, true)) {
                throw new BindingException(__('Unit :sn is :status and can no longer be bound.', ['sn' => $unit->display_id, 'status' => $unit->status_label]));
            }
            // A held unit does not move on to its label: that would read as the next
            // step done while it waits for a retest or a release. A supervisor's
            // re-binding (a mixed-up PSN) is a correction, not a step, and still goes.
            if ($unit && $unit->status === SerialUnit::STATUS_BLOCKED && empty($context['force'])) {
                // With a different PSN on the scan the station still offers the
                // supervisor's re-bind, the one way this refusal can be overridden.
                throw new BindingException(__('Unit :sn is blocked - it cannot move on until it passes a retest or a supervisor releases it.', ['sn' => $unit->display_id]),
                    rebindable: $psn !== null && $unit->psn !== null && $unit->psn !== $psn);
            }

            if ($psn !== null && $settings->get('unit_psn_unique')) {
                $owner = SerialUnit::where('psn', $psn)->when($unit, fn ($q) => $q->whereKeyNot($unit->id))->first();
                if ($owner) {
                    throw new BindingException(__('Process serial :psn already belongs to unit :sn.', ['psn' => $psn, 'sn' => $owner->display_id]));
                }
            }

            $rebound = false;
            $previousPsn = $unit?->psn;
            if ($unit) {
                if ($psn !== null && $unit->psn !== null && $unit->psn !== $psn) {
                    if (empty($context['force'])) {
                        throw new BindingException(__('Unit :sn is already bound to process serial :psn.', ['sn' => $serialNo, 'psn' => $unit->psn]), rebindable: true);
                    }
                    $rebound = true;
                }
                $unit->fill(array_filter([
                    'psn' => $psn ?? $unit->psn,
                    'work_order_id' => $unit->work_order_id ?: ($context['work_order_id'] ?? null),
                ], fn ($v) => $v !== null));
                $created = false;
            } else {
                $unit = $this->registerUnit($serialNo, [
                    'psn' => $psn,
                    'work_order_id' => $context['work_order_id'] ?? null,
                    'status' => SerialUnit::STATUS_IN_PRODUCTION,
                ]);
                $created = true;
            }

            $unit->extra_data = array_merge($unit->extra_data ?? [], ['label_applied_at' => now()->toIso8601String()]);
            $unit->save();

            $this->recordStep($unit, $operator, null, [
                'workstation_id' => $context['workstation_id'] ?? null,
                'parameters' => array_filter([
                    'event' => $rebound ? 'process_serial_rebound' : 'label_applied',
                    'psn' => $psn,
                    'previous_psn' => $rebound ? $previousPsn : null,
                ]),
                'notes' => $rebound ? ($context['reason'] ?? null) : null,
            ]);

            return ['unit' => $unit->fresh(), 'created' => $created, 'rebound' => $rebound, 'serial_assigned' => $gotSerial];
        });
    }

    /**
     * Bind a component onto a unit by the identifier on the component's label.
     * The identifier is resolved to what the system already knows - another
     * serialised unit, or a material lot - and kept as scanned either way, so a
     * bought-in part with only a vendor serial still traces. A component that is
     * itself a serialised unit can only be inside one parent at a time.
     *
     * @throws BindingException
     */
    public function bindComponent(SerialUnit $unit, string $identifier, User $operator, array $context = []): SerialUnitComponent
    {
        $identifier = app(UnitSerialisation::class)->normalize($identifier) ?? '';

        return DB::transaction(function () use ($unit, $identifier, $operator, $context) {
            if (in_array($unit->status, SerialUnit::TERMINAL_STATUSES, true)) {
                throw new BindingException(__('Unit :sn is :status and can no longer be bound.', ['sn' => $unit->display_id, 'status' => $unit->status_label]));
            }
            if ($identifier === '' || $identifier === $unit->serial_no || $identifier === $unit->psn) {
                throw new BindingException(__('A unit cannot be a component of itself.'));
            }

            $existing = $unit->components()->installed()->where('identifier', $identifier)->first();
            if ($existing) {
                return $existing; // the same scan twice is a confirmation, not a second part
            }

            // A sub-assembly is known by its SN, or by its PSN while it has none.
            $componentUnit = SerialUnit::findByIdentifier($identifier);
            // Lot numbers are stored as typed; compare both sides folded (case,
            // spacing) so the match does not depend on the normalise setting.
            $lot = $componentUnit ? null : MaterialLot::whereRaw("UPPER(REPLACE(lot_number, ' ', '')) = ?", [strtoupper(str_replace(' ', '', $identifier))])->first();

            if ($componentUnit) {
                // Locked: two benches scanning the same board into two products at
                // once must not both find it free.
                $componentUnit = SerialUnit::whereKey($componentUnit->id)->lockForUpdate()->first();
                // A sub-assembly that is held, scrapped or gone does not go into a product.
                if (in_array($componentUnit->status, [...SerialUnit::TERMINAL_STATUSES, SerialUnit::STATUS_BLOCKED], true)) {
                    throw new BindingException(__('Component :id is :status and cannot be installed.', ['id' => $identifier, 'status' => $componentUnit->status_label]));
                }
                $elsewhere = SerialUnitComponent::installed()->where('component_serial_unit_id', $componentUnit->id)->with('unit:id,serial_no,psn')->first();
                if ($elsewhere) {
                    throw new BindingException(__('Component :id is already installed in unit :sn.', ['id' => $identifier, 'sn' => $elsewhere->unit->display_id]));
                }
                // A unit cannot end up inside something it contains (A ⊃ B ⊃ A).
                if ($this->contains($componentUnit, $unit)) {
                    throw new BindingException(__('Unit :sn is already inside :id - it cannot also contain it.', ['sn' => $unit->display_id, 'id' => $identifier]));
                }
            }

            $component = $unit->components()->create([
                'identifier' => $identifier,
                'component_serial_unit_id' => $componentUnit?->id,
                'material_lot_id' => $lot?->id,
                'material_id' => $context['material_id'] ?? $lot?->material_id ?? $componentUnit?->material_id,
                'quantity' => $context['quantity'] ?? 1,
                'batch_step_id' => $context['batch_step_id'] ?? null,
                'workstation_id' => $context['workstation_id'] ?? null,
                'bound_by_id' => $operator->id,
                'bound_at' => now(),
            ]);

            $this->recordStep($unit, $operator, null, [
                'workstation_id' => $context['workstation_id'] ?? null,
                'parameters' => array_filter([
                    'event' => 'component_bound',
                    'identifier' => $identifier,
                    'material' => $component->material?->code,
                    'kind' => $componentUnit ? 'serial_unit' : ($lot ? 'material_lot' : 'identifier'),
                ]),
            ]);

            return $component;
        });
    }

    /**
     * Scrap a unit - final. The reason goes into its history next to who did
     * it and where; the row itself is never deleted, the label exists.
     *
     * @throws BindingException when the unit already left the line
     */
    public function scrapUnit(SerialUnit $unit, User $operator, string $reason, ?int $workstationId = null): SerialUnit
    {
        return DB::transaction(function () use ($unit, $operator, $reason, $workstationId) {
            if ($unit->status === SerialUnit::STATUS_SHIPPED) {
                throw new BindingException(__('Unit :sn is :status and can no longer be bound.', ['sn' => $unit->display_id, 'status' => $unit->status_label]));
            }
            if ($unit->status !== SerialUnit::STATUS_SCRAPPED) {
                $unit->update(['status' => SerialUnit::STATUS_SCRAPPED]);
                $this->recordStep($unit, $operator, null, [
                    'workstation_id' => $workstationId ?? $operator->workstation_id,
                    'parameters' => ['event' => 'scrapped'],
                    'result' => 'fail',
                    'notes' => $reason,
                ]);
            }

            return $unit->fresh();
        });
    }

    /**
     * Hold a non-conforming unit with an error code: nothing downstream takes
     * it (packing, the pallet's quality gate) until a supervisor releases it.
     * A passing retest does not lift this hold - it was not the test's.
     *
     * @throws BindingException
     */
    public function blockUnit(SerialUnit $unit, User $operator, ScrapReason $reason, ?string $note = null, ?int $workstationId = null): SerialUnit
    {
        return DB::transaction(function () use ($unit, $operator, $reason, $note, $workstationId) {
            $unit = SerialUnit::whereKey($unit->id)->lockForUpdate()->firstOrFail();
            if (in_array($unit->status, SerialUnit::TERMINAL_STATUSES, true)) {
                throw new BindingException(__('Unit :sn is :status and can no longer be bound.', ['sn' => $unit->display_id, 'status' => $unit->status_label]));
            }
            if ($unit->status === SerialUnit::STATUS_BLOCKED) {
                throw new BindingException(__('Unit :sn is already blocked.', ['sn' => $unit->display_id]));
            }
            $unit->update([
                'status' => SerialUnit::STATUS_BLOCKED,
                'extra_data' => array_merge($unit->extra_data ?? [], ['hold' => [
                    'source' => 'manual', 'reason_code' => $reason->code, 'reason' => $reason->name,
                    'by_id' => $operator->id, 'at' => now()->toIso8601String(),
                ]]),
            ]);
            $this->recordStep($unit, $operator, null, [
                'workstation_id' => $workstationId ?? $operator->workstation_id,
                'parameters' => ['event' => 'blocked', 'reason_code' => $reason->code, 'reason' => $reason->name, 'category' => $reason->category],
                'notes' => $note,
            ]);
            $this->recomputePallet($unit);

            return $unit->fresh();
        });
    }

    /**
     * Release a held unit (by hand or after failed tests) - a supervisor's call,
     * with the reason. It goes back to where it was: packed units stay packed.
     *
     * @throws BindingException
     */
    public function unblockUnit(SerialUnit $unit, User $operator, string $note, ?int $workstationId = null): SerialUnit
    {
        return DB::transaction(function () use ($unit, $operator, $note, $workstationId) {
            $unit = SerialUnit::whereKey($unit->id)->lockForUpdate()->firstOrFail();
            if ($unit->status !== SerialUnit::STATUS_BLOCKED) {
                throw new BindingException(__('Unit :sn is not blocked.', ['sn' => $unit->display_id]));
            }
            $hold = $unit->extra_data['hold'] ?? [];
            $unit->update([
                'status' => $unit->packed_at ? SerialUnit::STATUS_COMPLETED : SerialUnit::STATUS_IN_PRODUCTION,
                'extra_data' => array_diff_key($unit->extra_data ?? [], ['hold' => true]) ?: null,
            ]);
            $this->recordStep($unit, $operator, null, [
                'workstation_id' => $workstationId ?? $operator->workstation_id,
                'parameters' => array_filter(['event' => 'unblocked', 'reason_code' => $hold['reason_code'] ?? null, 'hold_source' => $hold['source'] ?? 'test']),
                'notes' => $note,
            ]);
            $this->recomputePallet($unit);

            return $unit->fresh();
        });
    }

    /** A unit's hold changes what the pallet it sits on may do. */
    private function recomputePallet(SerialUnit $unit): void
    {
        $palletId = SerialUnit::whereKey($unit->id)->value('pallet_id');
        if ($palletId) {
            Pallet::find($palletId)?->recomputeQualityStatus();
        }
    }

    /** Take a component out of a unit; the row stays, closed, with the reason. */
    /** Whether $needle sits somewhere inside $haystack's installed sub-assemblies (any depth). */
    private function contains(SerialUnit $haystack, SerialUnit $needle, int $depth = 0): bool
    {
        if ($depth > 50) {
            return false;
        }
        $childIds = SerialUnitComponent::installed()->where('serial_unit_id', $haystack->id)->whereNotNull('component_serial_unit_id')->pluck('component_serial_unit_id');
        if ($childIds->contains($needle->id)) {
            return true;
        }
        foreach (SerialUnit::whereIn('id', $childIds)->get() as $child) {
            if ($this->contains($child, $needle, $depth + 1)) {
                return true;
            }
        }

        return false;
    }

    /** @throws BindingException when the product has shipped or been scrapped - its genealogy is final */
    public function unbindComponent(SerialUnitComponent $component, User $operator, ?string $reason = null, ?int $workstationId = null): SerialUnitComponent
    {
        return DB::transaction(function () use ($component, $operator, $reason, $workstationId) {
            $component = SerialUnitComponent::whereKey($component->id)->lockForUpdate()->firstOrFail();
            if ($component->unbound_at !== null) {
                return $component;
            }
            // What left the plant (or was scrapped) keeps the parts it had: removing
            // one would rewrite the record and free the part for another product.
            if (in_array($component->unit->status, SerialUnit::TERMINAL_STATUSES, true)) {
                throw new BindingException(__('Unit :sn is :status and can no longer be bound.', ['sn' => $component->unit->display_id, 'status' => $component->unit->status_label]));
            }
            $component->update(['unbound_at' => now(), 'unbound_by_id' => $operator->id, 'unbind_reason' => $reason]);
            $this->recordStep($component->unit, $operator, null, [
                'workstation_id' => $workstationId ?? $operator->workstation_id,
                'parameters' => ['event' => 'component_unbound', 'identifier' => $component->identifier],
                'notes' => $reason,
            ]);

            return $component;
        });
    }

    /**
     * Full chronological process history for a unit (the birth certificate).
     */
    public function getHistory(SerialUnit $unit): SerialUnit
    {
        return $unit->load([
            'workOrder:id,order_no,product_type_id',
            'workOrder.productType:id,name,code',
            'batch:id,batch_number,lot_number',
            'material:id,name,code',
            'carton:id,carton_no',
            'pallet:id,pallet_no',
            'history.workstation:id,name,code,line_id',
            'history.workstation.line:id,name,code',
            'history.operator:id,name',
            'history.batchStep:id,name,step_number',
        ]);
    }
}
