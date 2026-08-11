<?php

namespace App\Console\Commands;

use App\Models\Batch;
use App\Models\Issue;
use App\Models\IssueType;
use App\Models\MachineConnection;
use App\Models\MachineEvent;
use App\Models\MachineTag;
use App\Models\ProductType;
use App\Models\QualityCheck;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Production\QualityCheckService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * The paperwork around the simulated machines.
 *
 * `modbus:simulate` + `modbus:poll` produce the machine half of a shift: states
 * and counter pulses. Nothing produces the MES half — batches opening and
 * closing, quality checks being signed off, an escalation when one fails — so
 * on the shift monitor the batch strip and the event pins stayed frozen while
 * the timeline moved. This closes that gap:
 *
 *   php artisan db:seed --class=MachineSimulatorSeeder
 *   docker compose --profile simulator up -d      # machines, pollers, and this
 *
 * It only ever touches the simulated stations — those behind a machine
 * connection named `SIM-*`, which is what MachineSimulatorSeeder creates — and
 * it works on its own `SIM-…` work orders rather than consuming real ones. A
 * demo order planned for 25 pieces would be finished and closed within two
 * minutes at nameplate rate, which is not a thing to do to somebody's data.
 *
 * Counts are not invented here: produced and scrap are read back out of the
 * counter events the poller wrote, so a batch can only ever claim what the
 * machine actually reported. The work order's own produced_qty is already
 * driven by MachineProductionService on the ingest path — these orders are
 * machine-counted, so that keeps working untouched.
 */
class SimulateWorkflowCommand extends Command
{
    protected $signature = 'demo:simulate-workflow
                            {--interval=15 : seconds between ticks}
                            {--batch-minutes=25 : nominal minutes of production per batch}
                            {--qc-minutes=8 : minutes between quality checks on a running batch}
                            {--fail-in=6 : one quality check in N fails and raises an issue}
                            {--seed= : decorrelates a rerun from the last one}
                            {--once : run a single tick and exit (for testing)}';

    protected $description = 'Drive batches, quality checks and escalations on the simulated machines';

    /** Machine connections whose stations this command is allowed to drive. */
    private const CONNECTION_PREFIX = 'SIM-';

    /** Work orders this command opens for itself, and recognises again later. */
    private const ORDER_PREFIX = 'SIM-';

    /** An order is worth this many batches before it is closed and replaced. */
    private const BATCHES_PER_ORDER = 4;

    private ?User $actor = null;

    /** Stations already reported as blocked, so the log says it once. */
    private array $warned = [];

    public function handle(QualityCheckService $quality): int
    {
        if ($this->option('seed') !== null) {
            mt_srand((int) $this->option('seed'));
        }

        $stations = $this->stations();

        if ($stations->isEmpty()) {
            $this->error('No simulated stations found. Run: php artisan db:seed --class=MachineSimulatorSeeder');

            return self::FAILURE;
        }

        $this->info('Driving workflow for: '.$stations->pluck('code')->implode(', '));

        $interval = max(1, (int) $this->option('interval'));

        do {
            foreach ($stations as $station) {
                $this->tick($station, $quality);
            }

            if ($this->option('once')) {
                return self::SUCCESS;
            }

            sleep($interval);
        } while (true);
    }

    /**
     * The stations wired to a simulator, resolved through their tags.
     *
     * Deliberately narrow: this command writes production records, so it must
     * never find a real machine. The `SIM-` connection name is the contract
     * with MachineSimulatorSeeder.
     *
     * @return Collection<int, Workstation>
     */
    private function stations(): Collection
    {
        $connectionIds = MachineConnection::where('name', 'like', self::CONNECTION_PREFIX.'%')
            ->where('is_active', true)
            ->pluck('id');

        $workstationIds = MachineTag::whereIn('machine_connection_id', $connectionIds)
            ->whereNotNull('workstation_id')
            ->distinct()
            ->pluck('workstation_id');

        return Workstation::whereIn('id', $workstationIds)->orderBy('id')->get();
    }

    private function tick(Workstation $station, QualityCheckService $quality): void
    {
        if ($this->foreignBatchIsOpen($station)) {
            return;
        }

        $batch = $this->openBatch($station) ?? $this->startBatch($station);

        if (! $batch) {
            return;
        }

        $this->applyCounters($batch);
        $this->maybeCheckQuality($batch, $station, $quality);

        if ((float) $batch->produced_qty >= (float) $batch->target_qty) {
            $this->completeBatch($batch, $station);
        }
    }

    /** This command's own open batch on the station — never somebody else's. */
    private function openBatch(Workstation $station): ?Batch
    {
        return Batch::with('workOrder')
            ->forWorkstation($station->id)
            ->where('status', Batch::STATUS_IN_PROGRESS)
            ->whereIn('work_order_id', $this->ownOrders($station)->select('id'))
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Whether something else is already running a batch on this station.
     *
     * If so the station is left entirely alone. Adopting the batch would mean
     * writing this simulation's counts over somebody else's record — a batch
     * left open by a demo seeder, or a real one an operator started — and
     * opening a second alongside it would double-count the station. Clearing
     * the way is the seeder's job (MachineSimulatorSeeder), where it happens
     * once, on purpose, in front of whoever ran it.
     */
    private function foreignBatchIsOpen(Workstation $station): bool
    {
        $foreign = Batch::forWorkstation($station->id)
            ->where('status', Batch::STATUS_IN_PROGRESS)
            ->whereNotIn('work_order_id', $this->ownOrders($station)->select('id'))
            ->exists();

        if ($foreign && ! isset($this->warned[$station->id])) {
            $this->warned[$station->id] = true;
            $this->warn(sprintf(
                '[%s] a batch that is not this simulation\'s is open here — skipping the station. '
                .'Close it, or re-run db:seed --class=MachineSimulatorSeeder.',
                $station->code,
            ));
        }

        return $foreign;
    }

    /**
     * The work orders this command opened for this station.
     *
     * @return \Illuminate\Database\Eloquent\Builder<WorkOrder>
     */
    private function ownOrders(Workstation $station)
    {
        return WorkOrder::where('order_no', 'like', $this->orderPrefix($station).'%');
    }

    private function orderPrefix(Workstation $station): string
    {
        return self::ORDER_PREFIX.$station->code.'-';
    }

    /**
     * Open the next batch on this station: same order until it has had its run
     * of batches, then a fresh one for a different product — which is what puts
     * a changeover on the timeline.
     */
    private function startBatch(Workstation $station): ?Batch
    {
        $workOrder = $this->workOrderFor($station);

        if (! $workOrder) {
            return null;
        }

        $number = (int) (Batch::withTrashed()->where('work_order_id', $workOrder->id)->max('batch_number') ?? 0) + 1;
        $rate = max(1, (int) $station->ideal_rate_per_hour);
        $target = max(1, (int) round($rate * (int) $this->option('batch-minutes') / 60));

        $batch = Batch::create([
            'work_order_id' => $workOrder->id,
            'workstation_id' => $station->id,
            'batch_number' => $number,
            'lot_number' => sprintf('%s%s-%s-%02d', self::ORDER_PREFIX, $station->code, now()->format('ymd'), $number),
            'target_qty' => $target,
            'produced_qty' => 0,
            'scrap_qty' => 0,
            'status' => Batch::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        $this->line(sprintf('[%s] batch %s opened · target %d', $station->code, $batch->lot_number, $target));

        return $batch->setRelation('workOrder', $workOrder);
    }

    private function completeBatch(Batch $batch, Workstation $station): void
    {
        $batch->update([
            'status' => Batch::STATUS_DONE,
            'completed_at' => now(),
        ]);

        $this->line(sprintf(
            '[%s] batch %s closed · %d made, %d scrap',
            $station->code, $batch->lot_number, (int) $batch->produced_qty, (int) $batch->scrap_qty,
        ));

        // Straight into the next one: a station that has work left does not sit
        // idle between batches, and an empty batch strip on the monitor would
        // read as a problem rather than as a gap in the simulation.
        $this->startBatch($station);
    }

    /**
     * The order this station should be working.
     *
     * Its own open order if it still has batches left in it, otherwise a new
     * one. Real orders on the line are never picked up — see the class comment.
     */
    private function workOrderFor(Workstation $station): ?WorkOrder
    {
        $prefix = $this->orderPrefix($station);

        $open = $this->ownOrders($station)
            ->whereNotIn('status', WorkOrder::TERMINAL_STATUSES)
            ->orderByDesc('id')
            ->first();

        if ($open && Batch::where('work_order_id', $open->id)->count() < self::BATCHES_PER_ORDER) {
            return $open;
        }

        if ($open) {
            // Finished as far as this simulation is concerned. Closing it is
            // what makes room for the next product to appear on the strip.
            $open->update(['status' => WorkOrder::STATUS_DONE, 'completed_at' => now()]);
        }

        return $this->createWorkOrder($station, $prefix);
    }

    private function createWorkOrder(Workstation $station, string $prefix): ?WorkOrder
    {
        $product = $this->nextProductType($station);

        if (! $product) {
            $this->warn("No product types configured — cannot open a work order for {$station->code}.");

            return null;
        }

        $rate = max(1, (int) $station->ideal_rate_per_hour);
        $perBatch = max(1, (int) round($rate * (int) $this->option('batch-minutes') / 60));
        $sequence = $this->ownOrders($station)->count() + 1;

        $order = WorkOrder::create([
            'order_no' => sprintf('%s%03d', $prefix, $sequence),
            'line_id' => $station->line_id,
            'product_type_id' => $product->id,
            'planned_qty' => $perBatch * self::BATCHES_PER_ORDER,
            'produced_qty' => 0,
            // The ingest path only applies machine counts to machine-counted
            // orders, which is exactly what these are.
            'counting_source' => WorkOrder::COUNTING_MACHINE,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'due_date' => now()->endOfDay(),
            'description' => 'Simulated production (demo:simulate-workflow)',
        ]);

        $this->line(sprintf('[%s] work order %s opened · %s', $station->code, $order->order_no, $product->name));

        return $order;
    }

    /** Rotate through the product catalogue so consecutive orders differ. */
    private function nextProductType(Workstation $station): ?ProductType
    {
        $products = ProductType::orderBy('id')->get();

        if ($products->isEmpty()) {
            return null;
        }

        $seen = $this->ownOrders($station)->count();

        return $products[$seen % $products->count()];
    }

    /**
     * Bring the batch's counts up to what the machine has reported since it
     * opened.
     *
     * Recomputed from the event store rather than accumulated in memory, so a
     * restart mid-batch does not lose a shift's counting and two ticks can
     * never double-count the same pulse. A batch's worth of counter events is
     * a few hundred rows.
     */
    private function applyCounters(Batch $batch): void
    {
        $good = 0.0;
        $reject = 0.0;

        // Chunked because the payload is JSON and has to be summed in PHP: a
        // batch's worth is a few hundred rows, but nothing in the query itself
        // stops a station with a long-open batch from returning far more.
        MachineEvent::where('workstation_id', $batch->workstation_id)
            ->where('event_type', MachineEvent::TYPE_COUNTER)
            ->where('event_timestamp', '>=', $batch->started_at)
            ->toBase()
            ->select(['id', 'payload'])
            ->chunkById(1000, function ($events) use (&$good, &$reject) {
                foreach ($events as $event) {
                    $payload = json_decode((string) $event->payload, true) ?: [];
                    $delta = (float) ($payload['delta'] ?? 0);

                    if (($payload['kind'] ?? 'good') === 'reject') {
                        $reject += $delta;
                    } else {
                        $good += $delta;
                    }
                }
            });

        if ((float) $batch->produced_qty === $good && (float) $batch->scrap_qty === $reject) {
            return;
        }

        $batch->update(['produced_qty' => $good, 'scrap_qty' => $reject]);
    }

    /**
     * Sign off an in-process check every so often, and raise an issue when one
     * fails — the two things a supervisor watching this screen expects to see
     * arrive on their own.
     */
    private function maybeCheckQuality(Batch $batch, Workstation $station, QualityCheckService $quality): void
    {
        if ((float) $batch->produced_qty <= 0) {
            return;
        }

        $actor = $this->actor();

        if (! $actor) {
            return;
        }

        $last = QualityCheck::where('batch_id', $batch->id)->max('checked_at');
        $since = $last ? Carbon::parse($last) : $batch->started_at;

        if ($since->diffInMinutes(now(), true) < (int) $this->option('qc-minutes')) {
            return;
        }

        $failIn = max(1, (int) $this->option('fail-in'));
        $failed = mt_rand(1, $failIn) === 1;

        // No note: `notes` is whatever the person doing the check typed, so a
        // simulated one has nothing honest to put there — and a canned English
        // sentence would sit untranslated on the screen wherever it is shown.
        // The result and its samples say everything this check knows.
        $check = $quality->performCheck(
            batch: $batch,
            user: $actor,
            samples: $this->samples($failed),
            productionQuantity: (float) $batch->produced_qty,
        );

        $this->line(sprintf(
            '[%s] QC #%d on %s · %s',
            $station->code, $check->id, $batch->lot_number, $failed ? 'FAILED' : 'passed',
        ));

        if ($failed) {
            $this->raiseIssue($batch, $station);
        }
    }

    /**
     * Three measurements against a print-shop tolerance. A failing check fails
     * exactly one of them, because a check that fails on everything reads as a
     * broken gauge rather than as a process drifting.
     *
     * @return array<int, array<string, mixed>>
     */
    private function samples(bool $failed): array
    {
        $failing = $failed ? mt_rand(0, 2) : -1;

        $parameters = [
            ['Colour deviation ΔE', 0.4, 2.6, 3.5],
            ['Print alignment (mm)', 0.1, 0.8, 1.5],
            ['Cure temperature (°C)', 158.0, 166.0, 172.0],
        ];

        $samples = [];

        foreach ($parameters as $i => [$name, $low, $high, $outOfSpec]) {
            $passes = $i !== $failing;
            $value = $passes
                ? $low + (mt_rand(0, 1000) / 1000) * ($high - $low)
                : $outOfSpec + (mt_rand(0, 500) / 1000);

            $samples[] = [
                'sample_number' => $i + 1,
                'parameter_name' => $name,
                'parameter_type' => 'measurement',
                'value_numeric' => round($value, 2),
                'is_passed' => $passes,
            ];
        }

        return $samples;
    }

    private function raiseIssue(Batch $batch, Workstation $station): void
    {
        $issueType = IssueType::where('is_active', true)->orderBy('id')->first();

        if (! $issueType || ! $batch->work_order_id) {
            return;
        }

        $issue = Issue::create([
            'work_order_id' => $batch->work_order_id,
            'issue_type_id' => $issueType->id,
            'source' => Issue::SOURCE_IN_PROCESS,
            'title' => "Quality check failed at {$station->code}",
            'description' => "In-process check on {$batch->lot_number} fell out of tolerance.",
            'status' => Issue::STATUS_OPEN,
            // A share of what has been made since the last check is suspect,
            // not the whole batch — the earlier check passed.
            'non_conforming_qty' => max(1, (int) round((float) $batch->produced_qty * 0.02)),
            'reported_by_id' => $this->actor()?->id,
            'reported_at' => now(),
        ]);

        $this->warn(sprintf('[%s] issue #%d raised on %s', $station->code, $issue->id, $batch->lot_number));
    }

    /**
     * Who the simulated checks and escalations are recorded as.
     *
     * A supervisor if there is one, because that is who signs these off; any
     * user otherwise. Nothing is created — the records point at a real account
     * or the command does without them.
     */
    private function actor(): ?User
    {
        // Matched on the role's name rather than through Spatie's role() scope,
        // which throws when the role does not exist at all.
        return $this->actor ??= User::whereHas('roles', fn ($q) => $q->where('name', 'Supervisor'))
            ->orderBy('id')
            ->first()
            ?? User::orderBy('id')->first();
    }
}
