<?php

namespace Modules\Pantheon\Console\Commands;

use App\Models\IntegrationConfig;
use App\Scopes\TenantScope;
use App\Support\TenantContext;
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
 *   php artisan pantheon:sync --tenant=3          — one tenant
 *
 * TENANCY. This runs in the console, where there is no authenticated user and no
 * request to carry a tenant — and TenantScope only filters when it can resolve
 * one, so every tenant-aware query would otherwise run UNSCOPED. That would let
 * one customer's warehouse documents be booked into another customer's ERP. The
 * command therefore drives the loop itself: it reads the Pantheon rows across all
 * tenants once, then runs each tenant's syncs inside that tenant's TenantContext,
 * resolving the settings and the client fresh so nothing leaks between them.
 */
class PantheonSyncCommand extends Command
{
    protected $signature = 'pantheon:sync
        {--only= : Run a single sync by name (products, stock-documents)}
        {--tenant= : Restrict the run to one tenant id}';

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

    public function handle(TenantContext $tenants): int
    {
        $only = $this->option('only');
        $selected = $only ? array_intersect_key(self::SYNCS, [$only => true]) : self::SYNCS;

        if ($selected === []) {
            $this->error("Unknown sync '{$only}'. Available: ".implode(', ', array_keys(self::SYNCS)));

            return self::FAILURE;
        }

        // Only the tenant scope is dropped — this is the one query that must see
        // every tenant, because it is what tells us which tenants to iterate. The
        // soft-delete scope stays: a deleted integration row must not drive a sync.
        $configs = IntegrationConfig::withoutGlobalScope(TenantScope::class)
            ->where('system_type', PantheonSettings::SYSTEM_TYPE)
            ->when($this->option('tenant'), fn ($q, $id) => $q->where('tenant_id', (int) $id))
            ->get();

        if ($configs->isEmpty()) {
            $this->warn('No Pantheon integration is configured (Admin → Integrations). Nothing to do.');

            return self::SUCCESS;
        }

        $failed = false;

        foreach ($configs as $config) {
            $failed = $this->runForTenant($tenants, $config->tenant_id, $selected) || $failed;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Run the selected syncs for one tenant, with that tenant's context set for the
     * whole run so every core query and every row written is scoped to it.
     *
     * @param  array<string, class-string>  $selected
     * @return bool whether anything failed
     */
    private function runForTenant(TenantContext $tenants, ?int $tenantId, array $selected): bool
    {
        $label = $tenantId === null ? 'single-tenant install' : "tenant {$tenantId}";
        $tenants->set($tenantId);

        try {
            // Resolved inside the context on purpose: PantheonSettings reads the
            // integration row and is bound non-singleton, so this is THIS tenant's
            // configuration and credentials.
            $settings = app(PantheonSettings::class);

            if (! $settings->isActive()) {
                $this->warn("→ {$label}: integration inactive, skipped");

                return false;
            }

            if (! $settings->isConfigured()) {
                $this->error("→ {$label}: not configured (PAWS URL, username and company database are required)");

                return true;
            }

            if ($problem = $settings->transportProblem()) {
                // Refusing is the point: continuing would put the Pantheon password
                // on the wire in cleartext.
                $this->error("→ {$label}: {$problem}");

                return true;
            }

            $this->line("→ {$label}");

            return $this->runSyncs($selected, $tenantId);
        } finally {
            // Always clear: under Octane the context singleton outlives the command,
            // and a stale tenant id is exactly how data crosses between customers.
            $tenants->clear();
        }
    }

    /**
     * @param  array<string, class-string>  $selected
     * @return bool whether anything failed
     */
    private function runSyncs(array $selected, ?int $tenantId): bool
    {
        $failed = false;

        foreach ($selected as $name => $class) {
            $runId = $this->startRun($name, $tenantId);

            try {
                $report = app($class)->run();

                $this->finishRun($runId, $report);

                $this->info(sprintf(
                    '   %s: imported %d, updated %d, skipped %d, errors %d',
                    $name, $report['imported'], $report['updated'], $report['skipped'], count($report['errors']),
                ));

                // Row errors are the point of a nightly sync report, so print them
                // rather than leaving them in a log nobody opens.
                foreach (array_slice($report['errors'], 0, 20) as $error) {
                    $this->warn('   · '.json_encode($error, JSON_UNESCAPED_UNICODE));
                }

                if (count($report['errors']) > 20) {
                    $this->warn('   · … and '.(count($report['errors']) - 20).' more (see the run history)');
                }

                $failed = $failed || $report['errors'] !== [];
            } catch (\Throwable $e) {
                // One entity failing should not stop the others: work orders are
                // still worth importing when the recipe view is unavailable.
                $this->failRun($runId, $e->getMessage());
                $this->error("   {$name} failed: ".$e->getMessage());
                $failed = true;
            }
        }

        return $failed;
    }

    /** Insert the run row up front, so a crashed run is visible as unfinished. */
    private function startRun(string $sync, ?int $tenantId): int
    {
        return (int) DB::table('pantheon_sync_runs')->insertGetId([
            'sync' => $sync,
            'tenant_id' => $tenantId,
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
