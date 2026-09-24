<?php

namespace App\Console\Commands;

use App\Models\SerialUnit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Traceability\SerialTraceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class ImportTestStationJsonl extends Command
{
    protected $signature = 'traceability:import-jsonl
        {paths* : Path to a .jsonl file or a directory of .jsonl files (e.g. the pass/fail folders)}
        {--work-order= : Work order to attach units to (order_no, or id if numeric)}
        {--workstation= : Workstation code for the test station (falls back to the station from the log)}
        {--operator= : Operator email or name (defaults to the first user)}';

    protected $description = 'Import test-station JSONL logs (cycle/step/verdict lines) into serial traceability';

    public function handle(SerialTraceService $serials): int
    {
        $files = $this->collectFiles();
        if ($files === []) {
            $this->error('No .jsonl files found for the given paths.');

            return self::FAILURE;
        }

        $workOrder = $this->resolveWorkOrder();
        $operator = $this->resolveOperator();
        if ($operator === null) {
            $this->error('No user found for the operator. Create a user first.');

            return self::FAILURE;
        }

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($files as $file) {
            try {
                $lines = array_values(array_filter(
                    array_map('trim', File::lines($file)->all()),
                    fn ($l) => $l !== ''
                ));

                $cycle = null;
                $steps = [];
                $verdict = null;
                foreach ($lines as $line) {
                    $row = json_decode($line, true);
                    if (!is_array($row)) {
                        continue;
                    }
                    $t = $row['t'] ?? null;
                    if ($t === 'cycle') {
                        $cycle = $row;
                    } elseif ($t === 's') {
                        $steps[] = $row;
                    } elseif ($t === 'v') {
                        $verdict = $row;
                    }
                }

                if ($cycle === null || empty($cycle['sn'])) {
                    $this->warn("[SKIP] {$file}: no cycle line with sn");
                    $skipped++;
                    continue;
                }

                $sn = trim((string) $cycle['sn']);
                $cycleId = (string) ($cycle['id'] ?? '');
                $psn = isset($cycle['psn']) && $cycle['psn'] !== '' ? trim((string) $cycle['psn']) : null;
                $stationCode = $this->option('workstation') ?: ($cycle['station'] ?? null);

                $unit = SerialUnit::where('serial_no', $sn)->first();
                if ($unit === null) {
                    $unit = $serials->registerUnit($sn, [
                        'psn' => $psn,
                        'work_order_id' => $workOrder?->id,
                        'status' => SerialUnit::STATUS_IN_PRODUCTION,
                    ]);
                } else {
                    $update = [];
                    if ($psn !== null && empty($unit->psn)) {
                        $update['psn'] = $psn;
                    }
                    if ($workOrder !== null && empty($unit->work_order_id)) {
                        $update['work_order_id'] = $workOrder->id;
                    }
                    if ($update !== []) {
                        $unit->update($update);
                    }
                }

                // Idempotency: skip when this exact test cycle was already imported.
                $alreadyImported = $cycleId !== '' && $unit->history()
                    ->get()
                    ->contains(fn ($h) => ($h->parameters['cycle_id'] ?? null) === $cycleId);

                if ($alreadyImported) {
                    $this->line("[SKIP] {$file}: cycle {$cycleId} already imported");
                    $skipped++;
                    continue;
                }

                $workstation = $stationCode
                    ? Workstation::where('code', $stationCode)->first()
                    : null;

                $failedSteps = array_values(array_map(
                    fn ($s) => $s['n'] ?? null,
                    array_filter($steps, fn ($s) => ($s['r'] ?? 'P') === 'F')
                ));

                $verdictR = $verdict['r'] ?? 'P';
                $result = $verdictR === 'F' ? 'fail' : ($verdictR === 'R' ? 'rework' : 'pass');

                $serials->recordStep($unit, $operator, null, [
                    'workstation_id' => $workstation?->id,
                    'parameters' => [
                        'cycle_id' => $cycleId,
                        'file' => basename($file),
                        'station' => $cycle['station'] ?? null,
                        'line' => $cycle['line'] ?? null,
                        'operator' => $cycle['op'] ?? null,
                        'software' => $cycle['sw'] ?? null,
                        'limits' => $cycle['lim'] ?? null,
                        'sfr' => $cycle['sfr'] ?? null,
                        'dut' => $cycle['dut'] ?? null,
                        'start' => $cycle['start'] ?? null,
                        'steps' => $steps,
                        'failed_steps' => $failedSteps,
                    ],
                    'result' => $result,
                    'notes' => $result === 'fail'
                        ? 'Test station verdict FAIL, steps: ' . implode(', ', $failedSteps)
                        : "Test station verdict {$verdictR}",
                ]);

                $this->line(sprintf(
                    '[OK] %s: unit %s (station %s) verdict %s%s',
                    $file,
                    $sn,
                    $stationCode ?? 'n/a',
                    strtoupper($result),
                    $failedSteps !== [] ? ', failed steps: ' . implode(', ', $failedSteps) : ''
                ));
                $imported++;
            } catch (Throwable $e) {
                $this->error("[ERROR] {$file}: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->info("Done: {$imported} imported, {$skipped} skipped, {$errors} errors.");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return string[]
     */
    private function collectFiles(): array
    {
        $files = [];
        foreach ((array) $this->argument('paths') as $path) {
            if (is_dir($path)) {
                $found = glob($path . '/*.jsonl') ?: [];
                sort($found);
                $files = array_merge($files, $found);
            } elseif (is_file($path)) {
                $files[] = $path;
            } else {
                $this->warn("Path not found: {$path}");
            }
        }

        return array_values(array_unique($files));
    }

    private function resolveWorkOrder(): ?WorkOrder
    {
        $value = $this->option('work-order');
        if ($value === null || $value === '') {
            return null;
        }

        $workOrder = ctype_digit((string) $value)
            ? WorkOrder::whereKey($value)->first()
            : WorkOrder::where('order_no', $value)->first();

        if ($workOrder === null) {
            $this->warn("Work order not found: {$value} (continuing without work order)");
        }

        return $workOrder;
    }

    private function resolveOperator(): ?User
    {
        $value = $this->option('operator');
        if ($value !== null && $value !== '') {
            $user = User::where('email', $value)->orWhere('name', $value)->first();
            if ($user !== null) {
                return $user;
            }
            $this->warn("Operator not found: {$value} (falling back to first user)");
        }

        return User::orderBy('id')->first();
    }
}
