<?php

namespace Tests\Feature\Web;

use App\Http\Middleware\DynamicCors;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The CORS allowlist from Settings → System: what may be saved, and which
 * responses it may open to other sites.
 */
class CorsSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    private function settingsPayload(array $changes): array
    {
        return array_merge([
            'production_period' => 'none',
            'workflow_mode' => 'status',
            'schedule_view_mode' => 'weekly',
            'schedule_shifts_per_day' => 1,
            'realtime_mode' => 'polling',
            'production_tracking_mode' => 'per_operation',
            'production_qty_edit_policy' => 'none',
            'scanner_mode' => 'hid',
        ], $changes);
    }

    /** Store an allowlist directly, as an older version may have left it. */
    private function storeOrigins(string $origins): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => 'cors_allowed_origins'], ['value' => json_encode($origins)]);
        Cache::forget(DynamicCors::CACHE_KEY);
    }

    private function preflight(string $path, string $origin)
    {
        return $this->call('OPTIONS', $path, [], [], [], [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);
    }

    // ── What may be saved ────────────────────────────────────────────────────

    public function test_settings_accept_a_wildcard_a_list_of_origins_or_nothing(): void
    {
        foreach (['', '*', 'https://erp.example.com', 'https://erp.example.com, http://10.0.0.5:8080', 'http://[::1]:3000'] as $value) {
            $this->actingAs($this->admin)
                ->post('/settings/system', $this->settingsPayload(['cors_allowed_origins' => $value]))
                ->assertSessionHasNoErrors();
        }
    }

    public function test_settings_reject_values_that_are_not_origins(): void
    {
        foreach (['null', 'https://erp.example.com/api', 'erp.example.com', '*, https://erp.example.com', 'javascript://x', "https://a.com\r\nX-Evil: 1"] as $value) {
            $this->actingAs($this->admin)
                ->post('/settings/system', $this->settingsPayload(['cors_allowed_origins' => $value]))
                ->assertSessionHasErrors('cors_allowed_origins');
        }
    }

    public function test_settings_tidy_spaces_trailing_slashes_and_method_case(): void
    {
        $this->actingAs($this->admin)
            ->post('/settings/system', $this->settingsPayload([
                'cors_allowed_origins' => ' https://erp.example.com/ ,https://shop.example.com',
                'cors_allowed_methods' => 'get, post',
            ]))
            ->assertSessionHasNoErrors();

        $stored = DB::table('system_settings')->whereIn('key', ['cors_allowed_origins', 'cors_allowed_methods'])->pluck('value', 'key');
        $this->assertSame('https://erp.example.com, https://shop.example.com', json_decode($stored['cors_allowed_origins'], true));
        $this->assertSame('GET, POST', json_decode($stored['cors_allowed_methods'], true));
    }

    public function test_saving_another_tab_leaves_the_allowlist_alone(): void
    {
        $this->storeOrigins('https://erp.example.com');

        // What the page posts from any tab but Security: no CORS fields.
        $this->actingAs($this->admin)
            ->post('/settings/system', $this->settingsPayload(['schedule_view_mode' => 'monthly']))
            ->assertSessionHasNoErrors();

        $this->assertSame('https://erp.example.com', json_decode(DB::table('system_settings')->where('key', 'cors_allowed_origins')->value('value'), true));
    }

    public function test_settings_reject_unknown_methods(): void
    {
        $this->actingAs($this->admin)
            ->post('/settings/system', $this->settingsPayload(['cors_allowed_methods' => 'GET, POST, PATCH']))
            ->assertSessionHasNoErrors();

        foreach (['GET, TRACE', "GET\r\nX-Evil: 1", 'GET;POST'] as $value) {
            $this->actingAs($this->admin)
                ->post('/settings/system', $this->settingsPayload(['cors_allowed_methods' => $value]))
                ->assertSessionHasErrors('cors_allowed_methods');
        }
    }

    public function test_the_settings_api_checks_cors_values_like_the_form(): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => 'cors_allowed_origins'], ['value' => json_encode('')]);
        DB::table('system_settings')->updateOrInsert(['key' => 'cors_allowed_methods'], ['value' => json_encode('GET, POST')]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/v1/system/settings/cors_allowed_origins', ['value' => 'null'])
            ->assertUnprocessable();
        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/v1/system/settings/cors_allowed_methods', ['value' => "GET\r\nX-Evil: 1"])
            ->assertUnprocessable();
        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/v1/system/settings/cors_allowed_origins', ['value' => 'https://erp.example.com'])
            ->assertSuccessful();
    }

    // ── What the allowlist opens ─────────────────────────────────────────────

    public function test_a_null_origin_is_never_allowed_even_when_stored(): void
    {
        $this->storeOrigins('null, https://erp.example.com');

        $response = $this->preflight('/api/v1/health', 'null');

        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNull($response->headers->get('Access-Control-Allow-Credentials'));
    }

    public function test_only_the_api_answers_cross_origin_requests(): void
    {
        $this->storeOrigins('https://erp.example.com');

        $api = $this->withHeaders(['Origin' => 'https://erp.example.com'])->get('/api/v1/health');
        $this->assertSame('https://erp.example.com', $api->headers->get('Access-Control-Allow-Origin'));

        // A signed-in page carries the CSRF token: no other site may read it.
        $page = $this->actingAs($this->admin)->withHeaders(['Origin' => 'https://erp.example.com'])->get('/settings/system');
        $this->assertNull($page->headers->get('Access-Control-Allow-Origin'));
        $this->assertNull($page->headers->get('Access-Control-Allow-Credentials'));
    }

    public function test_a_refused_origin_gets_no_cors_headers_but_the_response_varies_by_origin(): void
    {
        $this->storeOrigins('https://erp.example.com');

        $response = $this->preflight('/api/v1/health', 'https://evil.example.org');

        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNull($response->headers->get('Access-Control-Allow-Credentials'));
        $this->assertStringContainsString('Origin', implode(',', $response->headers->all('vary')));
    }

    public function test_origins_are_matched_exactly(): void
    {
        $this->storeOrigins('https://erp.example.com');

        foreach (['https://erp.example.com.evil.org', 'http://erp.example.com', 'https://erp.example.com:8443', 'https://evil-erp.example.com'] as $origin) {
            $this->assertNull(
                $this->preflight('/api/v1/health', $origin)->headers->get('Access-Control-Allow-Origin'),
                "{$origin} must not be allowed",
            );
        }
    }
}
