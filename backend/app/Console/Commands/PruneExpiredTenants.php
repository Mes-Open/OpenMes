<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneExpiredTenants extends Command
{
    protected $signature = 'tenants:prune';

    protected $description = 'Delete demo tenants whose expires_at has passed';

    public function handle(): int
    {
        $expired = Tenant::whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired tenants found.');

            return self::SUCCESS;
        }

        foreach ($expired as $tenant) {
            // cascadeOnDelete on tenant_id FK removes users, lines, work_orders,
            // process_templates, product_types, issue_types automatically.
            // Isolate each tenant: a single failing delete (e.g. an un-relaxed
            // FK on a user reference) must not abort the whole scheduled run.
            try {
                DB::transaction(function () use ($tenant) {
                    // Resolve SET NULL user references while their parent batches
                    // still exist. PostgreSQL can otherwise interleave tenant cascades
                    // and reject the checklist update after its batch was deleted.
                    DB::table('users')->where('tenant_id', $tenant->id)->delete();
                    $tenant->delete();
                });
                $this->info("Deleted tenant #{$tenant->id} ({$tenant->name})");
            } catch (\Throwable $e) {
                report($e);
                $this->error("Failed to delete tenant #{$tenant->id} ({$tenant->name}): {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
