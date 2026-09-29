<?php

namespace Tests\Feature\Web\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The audit log page lists what was changed; some rows name no entity at all. */
class AuditLogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_rows_without_an_entity_do_not_break_the_type_filter(): void
    {
        Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        AuditLog::create(['user_id' => $admin->id, 'entity_type' => \App\Models\WorkOrder::class, 'entity_id' => 1, 'action' => 'updated']);
        AuditLog::create(['user_id' => $admin->id, 'entity_type' => null, 'entity_id' => null, 'action' => 'login']);

        $this->actingAs($admin)->get(route('admin.audit-logs'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('entityTypes', ['WorkOrder']));
    }

    public function test_the_deprecations_log_channel_is_declared(): void
    {
        // Declared in config, not added at runtime: under Octane the runtime copy
        // never reached the log manager ("Log [deprecations] is not defined").
        $this->assertNotNull(config('logging.channels.deprecations'));
        app('log')->channel('deprecations')->warning('probe');
        $this->assertTrue(true);
    }
}
