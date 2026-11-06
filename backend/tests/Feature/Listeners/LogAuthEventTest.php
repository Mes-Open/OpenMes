<?php

namespace Tests\Feature\Listeners;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LogAuthEventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Pretend we're in an HTTP context so the helpers populate IP / UA.
        $this->withServerVariables([
            'REMOTE_ADDR'     => '203.0.113.42',
            'HTTP_USER_AGENT' => 'PHPUnit-LogAuthEventTest/1.0',
        ]);
    }

    public function test_successful_login_creates_audit_log(): void
    {
        $user = User::factory()->create();

        Auth::login($user);

        $log = AuditLog::where('action', 'login')->first();

        $this->assertNotNull($log, 'Expected an audit_logs row with action=login');
        $this->assertSame('App\\Models\\User', $log->entity_type);
        $this->assertSame($user->id, (int) $log->entity_id);
        $this->assertSame($user->id, (int) $log->user_id);
        $this->assertNotNull($log->ip_address);
    }

    public function test_logout_creates_audit_log(): void
    {
        $user = User::factory()->create();

        Auth::login($user);
        Auth::logout();

        $log = AuditLog::where('action', 'logout')->first();

        $this->assertNotNull($log, 'Expected an audit_logs row with action=logout');
        $this->assertSame('App\\Models\\User', $log->entity_type);
        // user_id can come from the event's user (still resolvable at logout time)
        $this->assertSame($user->id, (int) $log->user_id);
    }

    public function test_failed_login_creates_audit_log_with_null_user_id(): void
    {
        $credentials = ['email' => 'someone@example.com', 'password' => 'wrong'];

        event(new Failed('web', null, $credentials));

        $log = AuditLog::where('action', 'login_failed')->first();

        $this->assertNotNull($log, 'Expected an audit_logs row with action=login_failed');
        $this->assertNull($log->user_id);
        $this->assertNull($log->entity_id);
        $this->assertSame('App\\Models\\User', $log->entity_type);
        $this->assertIsArray($log->before_state);
        $this->assertSame('someone@example.com', $log->before_state['username'] ?? null);
        // Password must never be persisted.
        $this->assertArrayNotHasKey('password', $log->before_state);
    }

    public function test_failed_login_with_username_credential_records_username(): void
    {
        event(new Failed('web', null, ['username' => 'jdoe', 'password' => 'wrong']));

        $log = AuditLog::where('action', 'login_failed')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame('jdoe', $log->before_state['username'] ?? null);
        $this->assertArrayNotHasKey('password', $log->before_state);
    }

    public function test_listener_failure_does_not_break_auth(): void
    {
        // Drop the audit_logs table so AuditLog::create() inside the listener throws.
        \Schema::drop('audit_logs');

        $user = User::factory()->create();

        // No exception must leak out of the auth flow even though the listener fails.
        Auth::login($user);

        $this->assertTrue(Auth::check());
        $this->assertSame($user->id, Auth::id());
    }
}
