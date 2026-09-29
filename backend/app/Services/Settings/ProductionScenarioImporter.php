<?php

namespace App\Services\Settings;

use App\Enums\PalletStatus;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\LotSequence;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\Pallet;
use App\Models\ProductType;
use App\Models\ScrapReason;
use App\Models\SerialUnit;
use App\Models\UnitCarton;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Lot\LotService;
use App\Services\Packaging\UnitPackingService;
use App\Services\Production\PalletBackflushService;
use App\Services\Schedule\SchedulePlannerService;
use App\Services\Traceability\SerialTraceService;
use App\Services\Traceability\TestLog\TestLogImporter;
use App\Services\Traceability\TestLog\TestRun;
use App\Services\WorkOrder\BatchService;
use App\Services\WorkOrder\WorkOrderService;
use App\Support\ProductionFlow;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Replays a production scenario from an import file (`scenario` section):
 * operator accounts, material lots and a timeline of shop-floor events - work
 * orders, batches, step starts and logs, units started on a PSN, labels,
 * components, test results, holds, packing, cartons and pallets.
 *
 * Every event goes through the same service the operator screens call, so the
 * result is what the line would have produced (statuses, quantities, history,
 * consumption), not rows written around the rules. The clock is set to each
 * event's time while it runs, so durations and waiting times read like a real
 * shift. Times are relative to the import: "-2d 07:30" (that day at 07:30),
 * "-3h" (three hours ago), "+4m" (after the previous event). Events happen
 * in the past; a schedule window and a due date may lie ahead ("+1d 06:00").
 *
 * Configuration (lines, stations, products, routings, sequences) is referenced
 * by code and must already exist. The caller runs this inside a transaction:
 * the first event that fails stops the import and names the event.
 */
class ProductionScenarioImporter
{
    private CarbonImmutable $base;

    private ?CarbonImmutable $clock = null;

    private User $actor;

    /** @var array<string, User> */
    private array $users = [];

    /** @var array<string, int> file reference => serial unit id */
    private array $units = [];

    /** @var array<string, int> file reference => pallet id */
    private array $pallets = [];

    private int $tests = 0;

    public function __construct(
        private readonly WorkOrderService $workOrders,
        private readonly BatchService $batches,
        private readonly SerialTraceService $serials,
        private readonly UnitPackingService $packing,
        private readonly TestLogImporter $testLogs,
        private readonly LotService $lots,
        private readonly PalletBackflushService $backflush,
        private readonly SchedulePlannerService $planner,
    ) {}

    /**
     * @param  array<string, mixed>  $scenario  the file's `scenario` section
     * @param  User  $actor  who imports - acts for events that name nobody
     * @return int events replayed
     *
     * @throws ScenarioException
     */
    public function import(array $scenario, User $actor): int
    {
        $this->actor = $actor;
        $frozen = [Carbon::getTestNow(), CarbonImmutable::getTestNow()];
        $this->base = CarbonImmutable::now();
        $this->clock = null;
        $this->users = $this->units = $this->pallets = [];
        $this->tests = 0;

        $mode = $scenario['requires']['production_flow_mode'] ?? null;
        if (is_string($mode) && ProductionFlow::mode() !== $mode) {
            throw new ScenarioException(__('This production scenario needs the ":mode" production flow mode. Change it under Settings → Production and import again.', ['mode' => $mode]));
        }

        $events = array_values(array_filter((array) ($scenario['events'] ?? []), 'is_array'));

        try {
            foreach (array_values((array) ($scenario['users'] ?? [])) as $i => $row) {
                $this->step(__('user :n', ['n' => $i + 1]), fn () => $this->createUser((array) $row));
            }
            foreach (array_values((array) ($scenario['material_lots'] ?? [])) as $i => $row) {
                $this->step(__('material lot :n', ['n' => $i + 1]), fn () => $this->createLot((array) $row));
            }
            foreach ($events as $i => $event) {
                $label = __('event :n (:type)', ['n' => $i + 1, 'type' => $event['do'] ?? '?']);
                $this->step($label, function () use ($event) {
                    $this->moveClock($event['at'] ?? null);
                    $this->apply($event);
                });
            }
        } finally {
            // Back to the real clock - or to whatever a caller had frozen it at.
            Carbon::setTestNow($frozen[0]);
            CarbonImmutable::setTestNow($frozen[1]);
        }

        return count($events);
    }

    /** Run one entry; any failure becomes a message naming the entry. */
    private function step(string $label, callable $run): void
    {
        try {
            $run();
        } catch (\Throwable $e) {
            throw new ScenarioException($label.': '.$e->getMessage(), 0, $e);
        }
    }

    private function apply(array $e): void
    {
        match ($e['do'] ?? null) {
            'work_order' => $this->createWorkOrder($e),
            'batch' => $this->createBatch($e),
            'schedule' => $this->schedule($e),
            'step_start' => $this->batches->startStep($this->batchStep($e), $this->user($e)),
            'step_log' => $this->batches->recordQuantity($this->batchStep($e), $this->user($e), (float) ($e['good'] ?? 0), (float) ($e['scrap'] ?? 0), $e['note'] ?? null),
            'step_complete' => $this->batches->completeStep($this->batchStep($e), $this->user($e)),
            'unit_start' => $this->startUnit($e),
            'subassembly' => $this->registerSubassembly($e),
            'unit_serial' => $this->bindSerial($e),
            'component' => $this->bindComponent($e),
            'test' => $this->recordTest($e),
            'block' => $this->serials->blockUnit($this->unit($e), $this->user($e), $this->scrapReason($e), $e['note'] ?? null, $this->workstation($e)?->id),
            'unblock' => $this->serials->unblockUnit($this->unit($e), $this->user($e), (string) ($e['note'] ?? __('Released')), $this->workstation($e)?->id),
            'scrap' => $this->serials->scrapUnit($this->unit($e), $this->user($e), (string) ($e['reason'] ?? __('Scrapped')), $this->workstation($e)?->id),
            'pack' => $this->pack($e),
            'carton_close' => $this->closeCarton($e),
            'pallet_open' => $this->openPallet($e),
            'pallet_close' => $this->closePallet($e),
            'pallet_ship' => $this->shipPallet($e),
            default => throw new ScenarioException(__('Unknown event type ":type".', ['type' => $e['do'] ?? ''])),
        };
    }

    // ── time ────────────────────────────────────────────────────────────────

    private function moveClock(mixed $spec): void
    {
        $time = $spec === null || $spec === ''
            ? ($this->clock ?? $this->base)->addSeconds(10)
            : $this->parseTime((string) $spec);

        if ($time->greaterThan($this->base)) {
            throw new ScenarioException(__('The time :time is after the import - scenario events must lie in the past.', ['time' => $time->format('Y-m-d H:i')]));
        }
        if ($this->clock && $time->lessThan($this->clock)) {
            throw new ScenarioException(__('The time :time is earlier than the previous event - list events in time order.', ['time' => $time->format('Y-m-d H:i:s')]));
        }

        $this->clock = $time;
        Carbon::setTestNow($time);
        CarbonImmutable::setTestNow($time);
    }

    /** "-2d 07:30" a day relative to the import at a clock time; "-3h20m" before the import; "+4m" after the previous event; or an absolute date. */
    private function parseTime(string $spec, bool $allowFuture = false): CarbonImmutable
    {
        $spec = trim($spec);
        if (preg_match('/^([+-]?\d+)d\s+(\d{1,2}):(\d{2})(?::(\d{2}))?$/i', $spec, $m)) {
            return $this->base->startOfDay()->addDays((int) $m[1])->setTime((int) $m[2], (int) $m[3], (int) ($m[4] ?? 0));
        }
        if (preg_match('/^([+-])\s*((?:\d+\s*[dhms]\s*)+)$/i', $spec, $m)) {
            $seconds = 0;
            preg_match_all('/(\d+)\s*([dhms])/i', $m[2], $parts, PREG_SET_ORDER);
            foreach ($parts as [, $n, $unit]) {
                $seconds += (int) $n * ['d' => 86400, 'h' => 3600, 'm' => 60, 's' => 1][strtolower($unit)];
            }

            return $m[1] === '+'
                ? ($allowFuture ? $this->base : ($this->clock ?? $this->base))->addSeconds($seconds)
                : $this->base->subSeconds($seconds);
        }
        try {
            return CarbonImmutable::parse($spec);
        } catch (\Throwable) {
            throw new ScenarioException(__('Unreadable time ":time".', ['time' => $spec]));
        }
    }

    // ── references ──────────────────────────────────────────────────────────

    private function user(array $e): User
    {
        $name = $e['by'] ?? null;
        if ($name === null || $name === '') {
            return $this->actor;
        }

        return $this->users[$name] ??= User::where('username', $name)->first()
            ?? throw new ScenarioException(__('Unknown user ":name" - list the account under scenario.users.', ['name' => $name]));
    }

    private function workOrder(array $e): WorkOrder
    {
        $no = (string) ($e['order'] ?? '');

        return WorkOrder::where('order_no', $no)->first()
            ?? throw new ScenarioException(__('Unknown work order ":no".', ['no' => $no]));
    }

    private function workstation(array $e): ?Workstation
    {
        if (empty($e['workstation'])) {
            return null;
        }

        return Workstation::where('code', $e['workstation'])->first()
            ?? throw new ScenarioException(__('Unknown workstation ":code".', ['code' => $e['workstation']]));
    }

    private function batchStep(array $e): BatchStep
    {
        $order = $this->workOrder($e);
        $batch = $order->batches()->where('batch_number', (int) ($e['batch'] ?? 1))->first()
            ?? throw new ScenarioException(__('Work order :no has no batch :n.', ['no' => $order->order_no, 'n' => $e['batch'] ?? 1]));

        return $batch->steps()->where('step_number', (int) ($e['step'] ?? 0))->first()
            ?? throw new ScenarioException(__('Batch :n of :no has no step :step.', ['n' => $batch->batch_number, 'no' => $order->order_no, 'step' => $e['step'] ?? '?']));
    }

    private function unit(array $e): SerialUnit
    {
        $ref = (string) ($e['unit'] ?? '');
        $unit = isset($this->units[$ref]) ? SerialUnit::find($this->units[$ref]) : SerialUnit::findByIdentifier($ref);

        return $unit ?? throw new ScenarioException(__('Unknown unit ":ref".', ['ref' => $ref]));
    }

    private function pallet(string $ref): Pallet
    {
        $pallet = isset($this->pallets[$ref]) ? Pallet::find($this->pallets[$ref]) : Pallet::where('pallet_no', $ref)->first();

        return $pallet ?? throw new ScenarioException(__('Unknown pallet ":ref".', ['ref' => $ref]));
    }

    private function scrapReason(array $e): ScrapReason
    {
        return ScrapReason::where('code', $e['reason'] ?? '')->first()
            ?? throw new ScenarioException(__('Unknown error code ":code".', ['code' => $e['reason'] ?? '']));
    }

    // ── set-up ──────────────────────────────────────────────────────────────

    /** A new account; an existing one is used as it is - never changed. */
    private function createUser(array $row): void
    {
        $username = trim((string) ($row['username'] ?? ''));
        if ($username === '') {
            throw new ScenarioException(__('An account needs a username.'));
        }
        if ($existing = User::where('username', $username)->first()) {
            $this->users[$username] = $existing;

            return;
        }

        // A workstation account is a bench's own terminal login: locked to that bench, an operator.
        $type = ($row['account_type'] ?? 'user') === 'workstation' ? 'workstation' : 'user';
        $role = $type === 'workstation' ? 'Operator' : (string) ($row['role'] ?? 'Operator');
        if (! Role::where('name', $role)->exists()) {
            throw new ScenarioException(__('Unknown role ":role".', ['role' => $role]));
        }
        // A file shared around must not leave an administrator with a known password behind.
        if ($role === 'Admin') {
            throw new ScenarioException(__('A scenario file cannot create administrator accounts.'));
        }
        $workstation = $this->workstation($row);
        if ($type === 'workstation' && ! $workstation) {
            throw new ScenarioException(__('A workstation account needs its workstation.'));
        }

        $user = User::create([
            'username' => $username,
            'name' => (string) ($row['name'] ?? $username),
            'email' => (string) ($row['email'] ?? $username.'@example.invalid'),
            'password' => Hash::make((string) ($row['password'] ?? Str::random(40))),
            'account_type' => $type,
            'workstation_id' => $workstation?->id,
        ]);
        $user->assignRole($role);
        $lineIds = Line::whereIn('code', (array) ($row['lines'] ?? []))->pluck('id');
        if ($lineIds->isNotEmpty()) {
            $user->lines()->syncWithoutDetaching($lineIds->all());
        }
        $this->users[$username] = $user;
    }

    private function createLot(array $row): void
    {
        $material = Material::where('code', $row['material'] ?? '')->first()
            ?? throw new ScenarioException(__('Unknown material ":code".', ['code' => $row['material'] ?? '']));
        $quantity = (float) ($row['quantity'] ?? 0);

        MaterialLot::firstOrCreate(['lot_number' => (string) ($row['lot_number'] ?? '')], [
            'material_id' => $material->id,
            'quantity_received' => $quantity,
            'quantity_available' => $quantity,
            'unit_of_measure' => $row['unit_of_measure'] ?? $material->unit_of_measure,
            'received_at' => isset($row['received']) ? $this->parseTime((string) $row['received']) : $this->base,
            'status' => MaterialLot::STATUS_RELEASED,
            'supplier_lot_no' => $row['supplier_lot_no'] ?? null,
        ]);
    }

    // ── orders and steps ────────────────────────────────────────────────────

    private function createWorkOrder(array $e): void
    {
        $no = (string) ($e['order_no'] ?? '');
        if ($no === '' || WorkOrder::where('order_no', $no)->exists()) {
            throw new ScenarioException(__('Work order ":no" is missing a number or already exists.', ['no' => $no]));
        }
        $product = ProductType::where('code', $e['product'] ?? '')->first()
            ?? throw new ScenarioException(__('Unknown product ":code".', ['code' => $e['product'] ?? '']));
        $line = isset($e['line']) ? (Line::where('code', $e['line'])->first()
            ?? throw new ScenarioException(__('Unknown line ":code".', ['code' => $e['line']]))) : null;

        $this->workOrders->createWorkOrder([
            'order_no' => $no,
            'product_type_id' => $product->id,
            'line_id' => $line?->id,
            'planned_qty' => (float) ($e['quantity'] ?? 0),
            'customer_order_no' => $e['customer_order_no'] ?? null,
            'priority' => (int) ($e['priority'] ?? 0),
            'due_date' => isset($e['due']) ? $this->parseTime((string) $e['due'], true)->toDateString() : null,
            'description' => $e['description'] ?? null,
        ]);
    }

    /**
     * Place an order on the schedule, as dragging it on the planner does. Its
     * window may lie ahead ("+1d 06:00"): planning is about the future. An
     * overlap with another order on the line is refused unless `force`.
     */
    private function schedule(array $e): void
    {
        $order = $this->workOrder($e);
        $input = [];
        if (isset($e['line'])) {
            $input['line_id'] = Line::where('code', $e['line'])->value('id')
                ?? throw new ScenarioException(__('Unknown line ":code".', ['code' => $e['line']]));
        }
        $start = isset($e['start']) ? $this->parseTime((string) $e['start'], true) : null;
        $end = isset($e['end']) ? $this->parseTime((string) $e['end'], true) : null;
        if ($start) {
            $input['planned_start_at'] = $start->toDateTimeString();
        }
        if ($end) {
            $input['planned_end_at'] = $end->toDateTimeString();
        }
        if (isset($e['due']) || $start || $end) {
            $input['due_date'] = (isset($e['due']) ? $this->parseTime((string) $e['due'], true) : ($end ?? $start))->toDateString();
        }
        if (isset($e['shift'])) {
            $input['shift_number'] = (int) $e['shift'];
        }

        $result = $this->planner->updateOrder($order, $input, (bool) ($e['force'] ?? false));
        if ($result['conflict']) {
            throw new ScenarioException($result['message']);
        }
    }

    private function createBatch(array $e): void
    {
        $order = $this->workOrder($e);
        $lot = $e['lot'] ?? null;
        if ($lot === 'auto') {
            $lot = $this->lots->generateLot($order->productType);
        }
        $this->workOrders->createBatch($order, (float) ($e['quantity'] ?? $order->planned_qty), $this->workstation($e)?->id, $lot);
    }

    // ── units ───────────────────────────────────────────────────────────────

    private function startUnit(array $e): void
    {
        $order = $this->workOrder($e);
        $psn = $e['psn'] ?? 'auto';
        if ($psn === 'auto') {
            $psn = $this->lots->generate($order->productType, LotSequence::PURPOSE_PROCESS_SERIAL);
        }
        $unit = $this->serials->startUnit((string) $psn, $this->user($e), [
            'work_order_id' => $order->id,
            'workstation_id' => $this->workstation($e)?->id,
        ]);
        $this->remember($e, $unit);
    }

    /** A sub-assembly registered by its own serial where it is made. */
    private function registerSubassembly(array $e): void
    {
        $material = Material::where('code', $e['material'] ?? '')->first()
            ?? throw new ScenarioException(__('Unknown material ":code".', ['code' => $e['material'] ?? '']));
        $unit = $this->serials->registerSubassembly((string) ($e['sn'] ?? ''), $material, $this->workOrder($e), $this->user($e), $this->workstation($e)?->id);
        $this->remember($e, $unit);
    }

    private function bindSerial(array $e): void
    {
        $unit = $this->unit($e);
        $sn = $e['sn'] ?? 'auto';
        if ($sn === 'auto') {
            $sn = $this->lots->generate($unit->workOrder?->productType, LotSequence::PURPOSE_UNIT_SERIAL);
        }
        $bound = $this->serials->bindProcessSerial((string) $sn, $unit->psn, $this->user($e), [
            'work_order_id' => $unit->work_order_id,
            'workstation_id' => $this->workstation($e)?->id,
        ]);
        $this->remember(['ref' => $e['unit']], $bound['unit']);
    }

    private function bindComponent(array $e): void
    {
        $unit = $this->unit($e);
        $material = isset($e['material']) ? (Material::where('code', $e['material'])->first()
            ?? throw new ScenarioException(__('Unknown material ":code".', ['code' => $e['material']]))) : null;
        $step = isset($e['step']) && $unit->work_order_id
            ? $this->batchStep(['order' => $unit->workOrder->order_no, 'batch' => $e['batch'] ?? 1, 'step' => $e['step']])
            : null;

        $this->serials->bindComponent($unit, (string) ($e['identifier'] ?? ''), $this->user($e), [
            'material_id' => $material?->id,
            'quantity' => $e['quantity'] ?? 1,
            'batch_step_id' => $step?->id,
            'workstation_id' => $this->workstation($e)?->id,
        ]);
    }

    /** A tester's run, imported the way the test-run API imports it. */
    private function recordTest(array $e): void
    {
        $unit = $this->unit($e);
        if (empty($unit->serial_no)) {
            throw new ScenarioException(__('Unit :ref has no serial number yet - a test result is filed under the SN.', ['ref' => $unit->display_id]));
        }
        $workstation = $this->workstation($e);
        $end = CarbonImmutable::now();
        $steps = [];
        foreach (array_values((array) ($e['steps'] ?? [])) as $i => $s) {
            $s = (array) $s;
            $verdict = strtolower((string) ($s['verdict'] ?? 'pass'));
            $steps[] = [
                'id' => (string) ($s['id'] ?? $i + 1),
                'name' => $s['name'] ?? null,
                'verdict' => $verdict === 'fail' ? TestRun::FAIL : TestRun::PASS,
                'duration_ms' => null,
                'measurements' => array_key_exists('value', $s) ? [array_filter([
                    'name' => $s['name'] ?? null, 'value' => $s['value'], 'unit' => $s['unit'] ?? null,
                    'low' => $s['low'] ?? null, 'high' => $s['high'] ?? null,
                ], fn ($v) => $v !== null)] : [],
            ];
        }
        $failed = array_values(array_map(fn ($s) => $s['id'], array_filter($steps, fn ($s) => $s['verdict'] === TestRun::FAIL)));
        $verdict = strtolower((string) ($e['result'] ?? 'pass')) === 'fail' || $failed !== [] ? TestRun::FAIL : TestRun::PASS;

        $this->testLogs->import(new TestRun(
            serialNo: $unit->serial_no,
            psn: $unit->psn,
            runId: (string) ($e['run_id'] ?? sprintf('scenario-%s-%d', $unit->serial_no, ++$this->tests)),
            verdict: $verdict,
            startedAt: $end->subSeconds((int) ($e['duration_s'] ?? 60)),
            endedAt: $end,
            station: $workstation?->code,
            line: $unit->workOrder?->line?->code,
            operator: null,
            steps: $steps,
            failedSteps: $failed,
        ), ['work_order' => $unit->workOrder, 'workstation' => $workstation, 'operator' => $this->user($e), 'source' => 'scenario']);
    }

    private function remember(array $e, SerialUnit $unit): void
    {
        $ref = $e['ref'] ?? null;
        if (is_string($ref) && $ref !== '') {
            $this->units[$ref] = $unit->id;
        }
    }

    // ── packing ─────────────────────────────────────────────────────────────

    /** Scan at the packing bench: weigh when the step asks, open a carton when none is open, pack. */
    private function pack(array $e): void
    {
        $unit = $this->unit($e);
        $user = $this->user($e);
        $workstation = $this->workstation($e);
        $config = $this->packing->packingConfigFor($unit->work_order_id);

        $measured = [];
        if (isset($config['weight_expected_g'])) {
            if (! isset($e['weight_g'])) {
                throw new ScenarioException(__('The packing step weighs every unit - give weight_g.'));
            }
            $weight = round((float) $e['weight_g'], 2);
            $expected = (float) $config['weight_expected_g'];
            $tolerance = (float) ($config['weight_tolerance_g'] ?? 0);
            if (abs($weight - $expected) > $tolerance + 1e-9) {
                // Refused at the scale, as at the bench: the reading stays on the unit's history.
                $this->serials->recordStep($unit, $user, null, [
                    'workstation_id' => $workstation?->id,
                    'parameters' => ['event' => 'weight_check', 'verdict' => 'fail', 'weight_g' => $weight, 'expected_g' => $expected, 'tolerance_g' => $tolerance],
                ]);

                return;
            }
            $measured = ['weight_g' => $weight];
        }

        $pallet = isset($e['pallet']) ? $this->pallet((string) $e['pallet']) : null;
        $carton = null;
        if ($config !== [] && ($config['unit'] ?? 'carton') === 'carton') {
            $carton = UnitCarton::where('active_by_id', $user->id)->where('status', UnitCarton::STATUS_OPEN)
                ->where('work_order_id', $unit->work_order_id)->first()
                ?? $this->packing->openCarton($user, $unit->workOrder, $pallet, $workstation?->id);
        }

        $unit = $this->packing->packUnit($unit, $user, $carton, $carton ? null : $pallet, $workstation?->id, $measured);
        $this->packing->closePalletIfFull($unit->pallet);
    }

    /** Close the operator's open carton for the order, optionally putting it on a pallet. */
    private function closeCarton(array $e): void
    {
        $order = $this->workOrder($e);
        $user = $this->user($e);
        $carton = UnitCarton::where('work_order_id', $order->id)->where('status', UnitCarton::STATUS_OPEN)
            ->orderByRaw('CASE WHEN active_by_id = ? THEN 0 ELSE 1 END', [$user->id])->latest('id')->first()
            ?? throw new ScenarioException(__('Work order :no has no open carton.', ['no' => $order->order_no]));

        $carton = $this->packing->closeCarton($carton, $user);
        if (isset($e['pallet'])) {
            $pallet = $this->pallet((string) $e['pallet']);
            $this->packing->assignCartonToPallet($carton, $pallet, $user, $this->workstation($e)?->id);
            $this->packing->closePalletIfFull($pallet->fresh());
        }
    }

    /** A new pallet for the order, as the packing bench creates it. */
    private function openPallet(array $e): void
    {
        $order = $this->workOrder($e);
        $user = $this->user($e);
        $batchIds = $order->batches()->pluck('id');

        Pallet::where('active_by_id', $user->id)->update(['active_by_id' => null, 'active_workstation_id' => null, 'activated_at' => null]);
        $pallet = Pallet::create([
            'work_order_id' => $order->id,
            'batch_id' => $batchIds->count() === 1 ? $batchIds->first() : null,
            'status' => PalletStatus::Open->value,
            'location' => $e['location'] ?? null,
            'qty' => 0,
            'active_by_id' => $user->id,
            'active_workstation_id' => $this->workstation($e)?->id ?? $user->workstation_id,
            'activated_at' => now(),
        ]);
        if ($this->backflush->isEnabled()) {
            $this->backflush->backflushForPallet($pallet, null, $user);
        }
        if (is_string($e['ref'] ?? null)) {
            $this->pallets[$e['ref']] = $pallet->id;
        }
    }

    private function closePallet(array $e): void
    {
        $pallet = $this->pallet((string) ($e['pallet'] ?? ''));
        if (! $pallet->isOpen()) {
            return;
        }
        $pallet->update(['status' => PalletStatus::Closed->value, 'active_by_id' => null, 'active_workstation_id' => null, 'activated_at' => null]);
        $this->packing->palletClosed($pallet);
    }

    /**
     * Ship the pallet; its units leave with it (the pallet's own status hook),
     * attributed to the event's user. A pallet still open is closed first, as
     * the bench would, so its packaging is booked.
     */
    private function shipPallet(array $e): void
    {
        $pallet = $this->pallet((string) ($e['pallet'] ?? ''));
        if ($pallet->isOpen()) {
            $this->closePallet($e);
            $pallet->refresh();
        }
        $previous = Auth::user();
        Auth::setUser($this->user($e));
        try {
            $pallet->update(['status' => PalletStatus::Shipped->value]);
        } finally {
            $previous ? Auth::setUser($previous) : Auth::forgetUser();
        }
    }
}
