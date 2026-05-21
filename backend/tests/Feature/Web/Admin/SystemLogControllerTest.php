<?php

namespace Tests\Feature\Web\Admin;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemLogControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $logPath;

    private bool $createdLogsDir = false;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Operator', 'web');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $logsDir = storage_path('logs');
        if (! is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
            $this->createdLogsDir = true;
        }

        $this->logPath = storage_path('logs/laravel-'.today()->format('Y-m-d').'.log');
    }

    protected function tearDown(): void
    {
        if (is_file($this->logPath)) {
            @unlink($this->logPath);
        }

        parent::tearDown();
    }

    public function test_index_requires_admin_role(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $response = $this->actingAs($operator)->get(route('admin.logs.system'));

        $response->assertStatus(403);
    }

    public function test_default_tab_is_app(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.logs.system'));

        $response->assertStatus(200);
        $response->assertSee('System Logs');
        $response->assertSee('Application log');
        $response->assertSee('Failed jobs');
        $response->assertSee('Deployments');
    }

    public function test_failed_jobs_tab_shows_rows_when_present(): void
    {
        \DB::table('failed_jobs')->insert([
            'uuid' => 'test-uuid-12345',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'TestJob']),
            'exception' => "RuntimeException: queue worker exploded\n#0 /var/www/whatever.php(42)",
            'failed_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.logs.system', ['tab' => 'failed_jobs']));

        $response->assertStatus(200);
        $response->assertSee('queue worker exploded');
        $response->assertSee('database');
    }

    public function test_failed_jobs_tab_shows_empty_state_when_no_rows(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.logs.system', ['tab' => 'failed_jobs']));

        $response->assertStatus(200);
        $response->assertSee('No failed jobs.');
    }

    public function test_failed_jobs_tab_shows_info_card_when_table_missing(): void
    {
        Schema::dropIfExists('failed_jobs');

        $response = $this->actingAs($this->admin)
            ->get(route('admin.logs.system', ['tab' => 'failed_jobs']));

        $response->assertStatus(200);
        $response->assertSee('Failed jobs table is missing.');
    }

    public function test_deployments_tab_shows_info_card_when_table_missing(): void
    {
        // system_updates table is intentionally missing on this branch.
        $this->assertFalse(Schema::hasTable('system_updates'));

        $response = $this->actingAs($this->admin)
            ->get(route('admin.logs.system', ['tab' => 'deployments']));

        $response->assertStatus(200);
        $response->assertSee('Deployment audit log is not available');
        $response->assertSee('v0.12+ schema');
    }

    public function test_app_log_reader_parses_laravel_format(): void
    {
        $today = today()->format('Y-m-d');
        $logLine = "[{$today} 10:30:00] testing.ERROR: Sample error message for the reader {\"context\":\"yes\"}\n";
        file_put_contents($this->logPath, $logLine);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.logs.system', ['tab' => 'app', 'date' => $today]));

        $response->assertStatus(200);
        $response->assertSee('Sample error message for the reader');
        $response->assertSee('error', false);
    }

    public function test_app_log_filters_by_level(): void
    {
        $today = today()->format('Y-m-d');
        $contents = "[{$today} 10:30:00] local.INFO: User logged in normally\n"
            ."[{$today} 10:31:00] local.ERROR: Database connection died\n"
            ."[{$today} 10:32:00] local.DEBUG: Some debug output\n";
        file_put_contents($this->logPath, $contents);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.logs.system', ['tab' => 'app', 'date' => $today, 'level' => 'error']));

        $response->assertStatus(200);
        $response->assertSee('Database connection died');
        $response->assertDontSee('User logged in normally');
        $response->assertDontSee('Some debug output');
    }

    public function test_app_log_filters_by_search(): void
    {
        $today = today()->format('Y-m-d');
        $contents = "[{$today} 10:30:00] local.INFO: User logged in\n"
            ."[{$today} 10:31:00] local.ERROR: WAREHOUSE module crashed\n";
        file_put_contents($this->logPath, $contents);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.logs.system', ['tab' => 'app', 'date' => $today, 'search' => 'WAREHOUSE']));

        $response->assertStatus(200);
        $response->assertSee('WAREHOUSE module crashed');
        $response->assertDontSee('User logged in');
    }

    public function test_app_log_empty_when_no_file(): void
    {
        // Temporarily stash the host laravel.log so the controller has no fallback file.
        $fallback = storage_path('logs/laravel.log');
        $stashed = $fallback.'.systemlogtest.bak';

        if (is_file($this->logPath)) {
            @unlink($this->logPath);
        }
        if (is_file($fallback)) {
            @rename($fallback, $stashed);
        }

        try {
            $response = $this->actingAs($this->admin)
                ->get(route('admin.logs.system', [
                    'tab' => 'app',
                    'date' => Carbon::now()->subYears(10)->format('Y-m-d'),
                ]));

            $response->assertStatus(200);
            $response->assertSee('No log entries match your filters.');
        } finally {
            if (is_file($stashed)) {
                @rename($stashed, $fallback);
            }
        }
    }

    public function test_app_log_handles_multiline_stack_traces(): void
    {
        $today = today()->format('Y-m-d');
        $contents = "[{$today} 10:30:00] local.ERROR: Boom\n"
            ."Stack trace:\n"
            ."#0 /var/www/app.php(7): something()\n"
            ."#1 {main}\n"
            ."[{$today} 10:31:00] local.INFO: After error\n";
        file_put_contents($this->logPath, $contents);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.logs.system', ['tab' => 'app', 'date' => $today]));

        $response->assertStatus(200);
        $response->assertSee('Boom');
        $response->assertSee('After error');
    }

    public function test_tail_endpoint_returns_json(): void
    {
        $today = today()->format('Y-m-d');
        file_put_contents(
            $this->logPath,
            "[{$today} 10:30:00] local.WARNING: Tail probe entry\n"
        );

        $response = $this->actingAs($this->admin)->get(route('admin.logs.system.tail'));

        $response->assertStatus(200);
        $response->assertJsonStructure(['entries']);
    }

    public function test_invalid_tab_returns_404(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.logs.system', ['tab' => 'bogus']));

        $response->assertStatus(404);
    }
}
