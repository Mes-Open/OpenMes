<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PinLoginEventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        // Make sure the feature is on. The default seeded setting may or may
        // not be present in tests, so upsert defensively.
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'pin_login_enabled'],
            ['value' => json_encode(true), 'description' => 'PIN login enabled']
        );
    }

    public function test_pin_login_writes_audit_log(): void
    {
        $user = User::factory()->create([
            'username' => 'pinuser',
            'password' => Hash::make('irrelevant-for-pin'),
            'pin'      => Hash::make('123456'),
        ]);
        $user->assignRole('Operator');

        $response = $this->post('/login/pin', [
            'username' => 'pinuser',
            'pin'      => '123456',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);

        $log = AuditLog::where('action', 'login')
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log, 'Expected an audit_logs row with action=login after PIN login');
        $this->assertSame('App\\Models\\User', $log->entity_type);
        $this->assertSame($user->id, (int) $log->entity_id);
    }

    public function test_invalid_pin_writes_failed_login_audit_log_and_does_not_authenticate(): void
    {
        User::factory()->create([
            'username' => 'pinuser',
            'pin'      => Hash::make('123456'),
        ]);

        $response = $this->post('/login/pin', [
            'username' => 'pinuser',
            'pin'      => '999999',
        ]);

        $response->assertSessionHasErrors();
        $this->assertGuest();

        // No successful login audit row.
        $this->assertSame(
            0,
            AuditLog::where('action', 'login')->count(),
            'No successful-login audit row should exist for a bad PIN'
        );
    }
}
