<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemSettingsStockPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'production_period' => 'none',
            'workflow_mode' => 'status',
            'schedule_view_mode' => 'weekly',
            'schedule_shifts_per_day' => 1,
            'schedule_horizon_weeks' => 6,
            'realtime_mode' => 'polling',
            'production_tracking_mode' => 'per_operation',
            'production_qty_edit_policy' => 'none',
            'scanner_mode' => 'hid',
        ], $overrides);
    }

    public function test_stock_policy_can_warn_and_allow_without_being_reset_by_older_clients(): void
    {
        foreach ([true, false] as $block) {
            $this->actingAs($this->admin)->post('/settings/system', $this->payload([
                'block_negative_stock' => $block,
            ]))->assertSessionHasNoErrors();
            $this->assertSame($block, json_decode(DB::table('system_settings')->where('key', 'block_negative_stock')->value('value'), true));
            $this->post('/settings/system', $this->payload())->assertSessionHasNoErrors();
            $this->assertSame($block, json_decode(DB::table('system_settings')->where('key', 'block_negative_stock')->value('value'), true));
        }
    }
}
