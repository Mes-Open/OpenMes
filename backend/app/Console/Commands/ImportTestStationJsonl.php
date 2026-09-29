<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Traceability\TestLog\TestLogImporter;
use App\Services\Traceability\TestLog\TestLogParser;
use App\Services\Traceability\TestLog\TestRun;
use Illuminate\Console\Command;
use Throwable;

/**
 * Loads test-station JSONL logs onto serial-unit history. Which file layouts
 * are understood is configuration (config/traceability.php); runs are applied
 * in the order they happened, so a retest after a failure lands after it.
 */
class ImportTestStationJsonl extends Command
{
    protected $signature = 'traceability:import-jsonl
        {paths* : .jsonl files or directories of them (e.g. the pass/ and fail/ folders)}
        {--work-order= : Work order to attach new units to (order_no, or id if numeric)}
        {--workstation= : Workstation code to attribute every run to (default: the station named in the log)}
        {--operator= : User name, e-mail or username to attribute runs to (default: the operator named in the log)}';

    protected $description = 'Import test-station JSONL logs into serial traceability';

    public function handle(TestLogParser $parser, TestLogImporter $importer): int
    {
        $files = $this->collectFiles();
        if ($files === []) {
            $this->error('No .jsonl files found for the given paths.');

            return self::FAILURE;
        }

        $options = array_filter([
            'work_order' => $this->resolveWorkOrder(),
            'workstation' => $this->resolveWorkstation(),
            'operator' => $this->resolveOperator(),
        ]);

        // Parse everything first so runs can be applied in time order.
        $runs = [];
        $errors = 0;
        foreach ($files as $file) {
            try {
                $run = $parser->parseFile($file);
                if ($run === null) {
                    $this->warn("[SKIP] {$file}: no complete run in file");

                    continue;
                }
                $runs[] = [$file, $run];
            } catch (Throwable $e) {
                $this->error("[ERROR] {$file}: {$e->getMessage()}");
                $errors++;
            }
        }
        usort($runs, fn ($a, $b) => ($a[1]->processedAt()?->getTimestamp() ?? 0) <=> ($b[1]->processedAt()?->getTimestamp() ?? 0));

        $imported = 0;
        $skipped = 0;
        foreach ($runs as [$file, $run]) {
            try {
                $result = $importer->import($run, [...$options, 'source' => basename($file)]);
                if ($result['status'] === TestLogImporter::DUPLICATE) {
                    $this->line("[SKIP] {$file}: run {$run->runId} already imported");
                    $skipped++;

                    continue;
                }
                $this->line(sprintf('[OK] %s: unit %s (%s) verdict %s%s -> %s',
                    $file, $result['unit']->serial_no, $run->station ?? 'n/a', strtoupper($run->verdict),
                    $run->verdict === TestRun::FAIL && $run->failedSteps ? ', failed steps: '.implode(', ', $run->failedSteps) : '',
                    $result['unit']->status,
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

    /** @return string[] */
    private function collectFiles(): array
    {
        $files = [];
        foreach ((array) $this->argument('paths') as $path) {
            if (is_dir($path)) {
                $found = glob(rtrim($path, '/').'/*.jsonl') ?: [];
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
        $workOrder = ctype_digit((string) $value) ? WorkOrder::find($value) : WorkOrder::where('order_no', $value)->first();
        if ($workOrder === null) {
            $this->warn("Work order not found: {$value} (continuing without work order)");
        }

        return $workOrder;
    }

    private function resolveWorkstation(): ?Workstation
    {
        $value = $this->option('workstation');
        if ($value === null || $value === '') {
            return null;
        }
        $workstation = Workstation::where('code', $value)->first();
        if ($workstation === null) {
            $this->warn("Workstation not found: {$value} (using the station named in each log)");
        }

        return $workstation;
    }

    private function resolveOperator(): ?User
    {
        $value = $this->option('operator');
        if ($value === null || $value === '') {
            return null;
        }
        $user = User::where('email', $value)->orWhere('name', $value)->orWhere('username', $value)->first();
        if ($user === null) {
            $this->warn("Operator not found: {$value} (using the operator named in each log)");
        }

        return $user;
    }
}
