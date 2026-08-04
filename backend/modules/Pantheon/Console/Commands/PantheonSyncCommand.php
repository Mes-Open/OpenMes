<?php

namespace Modules\Pantheon\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Pantheon\Services\PantheonSettings;
use Modules\Pantheon\Sync\PushStockDocuments;
use Modules\Pantheon\Sync\SyncProducts;

/**
 * One entry point for every Pantheon sync, so the scheduler, an operator at a
 * terminal and the status page all trigger the same code path.
 *
 *   php artisan pantheon:sync                     — everything, in dependency order
 *   php artisan pantheon:sync --only=products     — one entity
 *   php artisan pantheon:sync --dry-run           — read Pantheon, write nothing
 */
class PantheonSyncCommand extends Command
{
    protected $signature = 'pantheon:sync
        {--only= : Run a single sync by name (products, stock-documents)}
        {--dry-run : Read from Pantheon and report, without writing to OpenMES}';

    protected $description = 'Synchronise master data, stock and warehouse documents with Datalab Pantheon';

    /**
     * Dependency order: items must exist before anything can reference them, and
     * documents are pushed last so they reflect a synced state.
     *
     * @var array<string, class-string>
     */
    private const SYNCS = [
        'products' => SyncProducts::class,
        'stock-documents' => PushStockDocuments::class,
    ];

    public function handle(PantheonSettings $settings): int
    {
        if (! $settings->isActive()) {
            $this->warn('The Pantheon integration is inactive (Admin → Integrations). Nothing to do.');

            return self::SUCCESS;
        }

        if (! $settings->isConfigured()) {
            $this->error('Pantheon is not configured: PAWS URL, username and company database are required.');

            return self::FAILURE;
        }

        $only = $this->option('only');
        $selected = $only ? array_intersect_key(self::SYNCS, [$only => true]) : self::SYNCS;

        if ($selected === []) {
            $this->error("Unknown sync '{$only}'. Available: ".implode(', ', array_keys(self::SYNCS)));

            return self::FAILURE;
        }

        $failed = false;

        foreach ($selected as $name => $class) {
            $this->line("→ {$name}");

            if ($this->option('dry-run')) {
                $this->warn('  dry run — skipped (reading only is not implemented for this sync yet)');

                continue;
            }

            $runId = $this->startRun($name);

            try {
                $report = app($class)->run();

                $this->finishRun($runId, $report);

                $this->info(sprintf(
                    '  imported %d, updated %d, skipped %d, errors %d',
                    $report['imported'], $report['updated'], $report['skipped'], count($report['errors']),
                ));

                // Row errors are the point of a nightly sync report, so print them
                // rather than leaving them in a log nobody opens.
                foreach (array_slice($report['errors'], 0, 20) as $error) {
                    $this->warn('  · '.json_encode($error, JSON_UNESCAPED_UNICODE));
                }

                if (count($report['errors']) > 20) {
                    $this->warn('  · … and '.(count($report['errors']) - 20).' more (see the log)');
                }

                $failed = $failed || $report['errors'] !== [];
            } catch (\Throwable $e) {
                // One entity failing should not stop the others: work orders are
                // still worth importing when the recipe view is unavailable.
                $this->failRun($runId, $e->getMessage());
                $this->error('  failed: '.$e->getMessage());
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** Insert the run row up front, so a crashed run is visible as unfinished. */
    private function startRun(string $sync): int
    {
        return (int) DB::table('pantheon_sync_runs')->insertGetId([
            'sync' => $sync,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param  array{imported: int, updated: int, skipped: int, errors: array<int, mixed>}  $report */
    private function finishRun(int $id, array $report): void
    {
        DB::table('pantheon_sync_runs')->where('id', $id)->update([
            'finished_at' => now(),
            'imported' => $report['imported'] ?? 0,
            'updated' => $report['updated'] ?? 0,
            'skipped' => $report['skipped'] ?? 0,
            'error_count' => count($report['errors'] ?? []),
            // Cap what is stored: a broken mapping can produce thousands of
            // identical row errors, and the first hundred say the same thing.
            'errors' => json_encode(array_slice($report['errors'] ?? [], 0, 100), JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    private function failRun(int $id, string $message): void
    {
        DB::table('pantheon_sync_runs')->where('id', $id)->update([
            'finished_at' => now(),
            'failure' => $message,
            'updated_at' => now(),
        ]);
    }
}
