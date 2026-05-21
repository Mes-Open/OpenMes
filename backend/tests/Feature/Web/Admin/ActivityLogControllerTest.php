<?php

namespace Tests\Feature\Web\Admin;

use App\Models\AuditLog;
use App\Models\RequestLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ActivityLogControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Operator', 'web');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_index_requires_admin_role(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $response = $this->actingAs($operator)->get(route('admin.logs.activity'));
        $response->assertStatus(403);
    }

    public function test_index_shows_audit_logs(): void
    {
        AuditLog::create([
            'user_id'      => $this->admin->id,
            'entity_type'  => 'App\\Models\\WorkOrder',
            'entity_id'    => 42,
            'action'       => 'created',
            'before_state' => null,
            'after_state'  => ['status' => 'PENDING'],
            'ip_address'   => '10.0.0.1',
            'user_agent'   => 'phpunit',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.logs.activity'));

        $response->assertStatus(200);
        $response->assertSee('WorkOrder');
        $response->assertSee('#42');
    }

    public function test_index_shows_request_logs(): void
    {
        RequestLog::create([
            'user_id'     => $this->admin->id,
            'method'      => 'GET',
            'path'        => '/admin/dashboard',
            'route_name'  => 'admin.dashboard',
            'status'      => 200,
            'duration_ms' => 17,
            'ip_address'  => '10.0.0.2',
            'user_agent'  => 'phpunit',
            'sampled'     => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.logs.activity'));

        $response->assertStatus(200);
        $response->assertSee('/admin/dashboard');
        $response->assertSee('GET');
    }

    public function test_index_filters_by_user(): void
    {
        $other = User::factory()->create(['name' => 'Other User']);
        $other->assignRole('Admin');

        AuditLog::create([
            'user_id'     => $this->admin->id,
            'entity_type' => 'App\\Models\\WorkOrder',
            'entity_id'   => 1,
            'action'      => 'created',
            'after_state' => ['x' => 1],
            'ip_address'  => '10.0.0.1',
        ]);
        AuditLog::create([
            'user_id'     => $other->id,
            'entity_type' => 'App\\Models\\Line',
            'entity_id'   => 7,
            'action'      => 'updated',
            'before_state'=> ['name' => 'old'],
            'after_state' => ['name' => 'new'],
            'ip_address'  => '10.0.0.99',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.logs.activity', ['user_id' => $this->admin->id]));

        $response->assertStatus(200);
        // Audit row entity badges include the #id; only the admin's row should be present.
        $response->assertSee('WorkOrder');
        $response->assertSee('#1');
        $response->assertDontSee('#7');
    }

    public function test_index_filters_by_date_range(): void
    {
        // Old row outside window
        $old = AuditLog::create([
            'user_id'     => $this->admin->id,
            'entity_type' => 'App\\Models\\WorkOrder',
            'entity_id'   => 555,
            'action'      => 'created',
            'after_state' => ['x' => 1],
            'ip_address'  => '10.0.0.1',
        ]);
        // force created_at backwards (cannot update via Eloquent due to immutability hook)
        \DB::table('audit_logs')->where('id', $old->id)->update([
            'created_at' => Carbon::now()->subDays(60),
        ]);

        // Recent row in window
        AuditLog::create([
            'user_id'     => $this->admin->id,
            'entity_type' => 'App\\Models\\Line',
            'entity_id'   => 999,
            'action'      => 'updated',
            'after_state' => ['name' => 'new'],
            'ip_address'  => '10.0.0.1',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.logs.activity', [
            'from' => Carbon::now()->subDays(2)->format('Y-m-d'),
            'to'   => Carbon::now()->format('Y-m-d'),
        ]));

        $response->assertStatus(200);
        $response->assertSee('#999');
        $response->assertDontSee('#555');
    }

    public function test_index_filters_by_source_audit(): void
    {
        AuditLog::create([
            'user_id'     => $this->admin->id,
            'entity_type' => 'App\\Models\\WorkOrder',
            'entity_id'   => 11,
            'action'      => 'created',
            'after_state' => ['x' => 1],
            'ip_address'  => '10.0.0.1',
        ]);

        RequestLog::create([
            'user_id'     => $this->admin->id,
            'method'      => 'GET',
            'path'        => '/admin/secret-nav',
            'route_name'  => null,
            'status'      => 200,
            'duration_ms' => 5,
            'ip_address'  => '10.0.0.1',
            'user_agent'  => 'phpunit',
            'sampled'     => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.logs.activity', ['source' => 'audit']));

        $response->assertStatus(200);
        $response->assertSee('WorkOrder');
        $response->assertDontSee('/admin/secret-nav');
    }

    public function test_export_returns_csv(): void
    {
        AuditLog::create([
            'user_id'     => $this->admin->id,
            'entity_type' => 'App\\Models\\WorkOrder',
            'entity_id'   => 7,
            'action'      => 'created',
            'after_state' => ['x' => 1],
            'ip_address'  => '10.0.0.1',
        ]);
        RequestLog::create([
            'user_id'     => $this->admin->id,
            'method'      => 'GET',
            'path'        => '/admin/dashboard',
            'route_name'  => 'admin.dashboard',
            'status'      => 200,
            'duration_ms' => 9,
            'ip_address'  => '10.0.0.2',
            'user_agent'  => 'phpunit',
            'sampled'     => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.logs.activity.export'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->getContent();
        $lines = preg_split('/\r?\n/', trim($content));

        $this->assertGreaterThanOrEqual(2, count($lines), 'Expected header + at least 1 data line');
        $this->assertStringContainsString('created_at', $lines[0]);
        $this->assertStringContainsString('source', $lines[0]);
        // Body should mention at least one of the two seeded rows
        $body = implode("\n", array_slice($lines, 1));
        $this->assertTrue(
            str_contains($body, 'WorkOrder') || str_contains($body, '/admin/dashboard'),
            'CSV body should include at least one log row'
        );
    }

    public function test_index_handles_login_failed_with_null_user(): void
    {
        AuditLog::create([
            'user_id'     => null,
            'entity_type' => 'App\\Models\\User',
            'entity_id'   => null,
            'action'      => 'login_failed',
            'after_state' => ['username' => 'attacker'],
            'ip_address'  => '203.0.113.7',
            'user_agent'  => 'curl',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.logs.activity'));

        $response->assertStatus(200);
        $response->assertSee('Login failed');
    }
}
