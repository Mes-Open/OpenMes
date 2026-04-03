<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class PruneExpiredTenants extends Command
{
    protected $signature   = 'tenants:prune';
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
            $tenant->delete();
            $this->info("Deleted tenant #{$tenant->id} ({$tenant->name})");
        }

        return self::SUCCESS;
    }
}
