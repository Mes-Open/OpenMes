<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PruneExpiredTenantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_tenant_is_deleted(): void
    {
        $tenant = Tenant::create(['name' => 'Expired demo', 'expires_at' => now()->subHour()]);
        User::factory()->create(['tenant_id' => $tenant->id]);

        Artisan::call('tenants:prune');

        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
    }

    public function test_non_expired_tenant_is_kept(): void
    {
        $tenant = Tenant::create(['name' => 'Active demo', 'expires_at' => now()->addHour()]);

        Artisan::call('tenants:prune');

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
    }

    /**
     * The demo failure: pruning a tenant cascades to its users, but a user
     * referenced by packaging_checklists.checked_by (formerly restrictOnDelete)
     * blocked the delete with a 23503 FK violation. The reference must now null
     * out so the tenant — and its user — delete cleanly.
     */
    public function test_expired_tenant_with_user_referenced_by_packaging_checklist_is_deleted(): void
    {
        $tenant = Tenant::create(['name' => 'Demo with checklist', 'expires_at' => now()->subHour()]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $workOrder = WorkOrder::factory()->create(['tenant_id' => $tenant->id]);
        $batch = Batch::factory()->create(['work_order_id' => $workOrder->id]);

        DB::table('packaging_checklists')->insert([
            'batch_id' => $batch->id,
            'checked_by' => $user->id,
            'checked_at' => now(),
            'udi_readable' => true,
            'packaging_condition' => true,
            'labels_readable' => true,
            'label_matches_product' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Artisan::call('tenants:prune');

        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
