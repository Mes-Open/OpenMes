<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApiKey;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TimezoneRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * An ERP key's expiry is an instant. An ISO 8601 value with a zone ("…Z",
 * "+02:00") used to be stored by its wall-clock digits alone, read back in the
 * plant timezone — a key meant to expire at 23:00 UTC in a New York plant
 * kept working until 04:00 UTC the next day.
 */
class ApiKeyExpiryTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        TimezoneRegistry::save('America/New_York');
        TimezoneRegistry::refresh();

        Role::findOrCreate('Admin', 'web');
        $tenant = Tenant::create(['name' => 'Admin Tenant']);
        $this->admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->admin->assignRole('Admin');
    }

    protected function tearDown(): void
    {
        TimezoneRegistry::flush();
        date_default_timezone_set('UTC');

        parent::tearDown();
    }

    public function test_expiry_sent_in_utc_is_the_same_instant(): void
    {
        $this->travelTo(Carbon::parse('2026-11-19T22:00:00Z'));

        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/api-keys', [
            'name' => 'Expiring',
            'scopes' => ['erp:masterdata:read'],
            'expires_at' => '2026-11-19T23:00:00Z',
        ])->assertCreated();

        $this->assertTrue(
            Carbon::parse($response->json('data.expires_at'))->equalTo(Carbon::parse('2026-11-19T23:00:00Z')),
            'expires_at came back as '.$response->json('data.expires_at'),
        );

        $plaintext = $response->json('plaintext_key');
        $this->getJson('/api/v1/erp/me', ['X-Api-Key' => $plaintext])->assertOk();

        $this->travelTo(Carbon::parse('2026-11-19T23:01:00Z'));
        $this->assertFalse(ApiKey::sole()->isUsable());
        $this->getJson('/api/v1/erp/me', ['X-Api-Key' => $plaintext])->assertUnauthorized();
    }

    public function test_expiry_with_an_offset_is_the_same_instant(): void
    {
        $this->travelTo(Carbon::parse('2026-11-19T22:00:00Z'));

        $key = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/api-keys', [
            'name' => 'Offset',
            'scopes' => ['erp:masterdata:read'],
            'expires_at' => '2026-11-20T01:00:00+02:00',
        ])->assertCreated()->json('data.id');

        $this->assertTrue(ApiKey::findOrFail($key)->expires_at->equalTo(Carbon::parse('2026-11-19T23:00:00Z')));
    }

    public function test_expiry_without_a_zone_is_plant_local(): void
    {
        $this->travelTo(Carbon::parse('2026-11-19T22:00:00Z'));

        $key = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/api-keys', [
            'name' => 'Local',
            'scopes' => ['erp:masterdata:read'],
            'expires_at' => '2026-11-19 18:00:00',
        ])->assertCreated()->json('data.id');

        // 18:00 in New York (UTC-5) is 23:00 UTC.
        $this->assertTrue(ApiKey::findOrFail($key)->expires_at->equalTo(Carbon::parse('2026-11-19T23:00:00Z')));
    }
}
