<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Support\TimezoneRegistry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /api/auth/me` tells a client which zone the plant runs on, so it can
 * show the API's UTC timestamps in plant time.
 */
class AuthMePlantTimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TimezoneRegistry::flush();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        TimezoneRegistry::flush();
        date_default_timezone_set('UTC');
        config(['app.timezone' => 'UTC']);

        parent::tearDown();
    }

    private function me(User $user): \Illuminate\Testing\TestResponse
    {
        $token = $user->createToken('t')->plainTextToken;

        return $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/auth/me')->assertOk();
    }

    public function test_reports_the_zone_set_in_settings(): void
    {
        TimezoneRegistry::save('Europe/Warsaw');
        Carbon::setTestNow(Carbon::parse('2026-01-15 12:00:00', 'UTC'));

        $this->me(User::factory()->operator()->create())
            ->assertJsonPath('plant.timezone', 'Europe/Warsaw')
            ->assertJsonPath('plant.utc_offset', '+01:00');
    }

    public function test_offset_follows_daylight_saving(): void
    {
        TimezoneRegistry::save('Europe/Warsaw');
        Carbon::setTestNow(Carbon::parse('2026-07-15 12:00:00', 'UTC'));

        $this->me(User::factory()->operator()->create())->assertJsonPath('plant.utc_offset', '+02:00');
    }

    public function test_a_zone_west_of_utc(): void
    {
        TimezoneRegistry::save('America/Chicago');
        Carbon::setTestNow(Carbon::parse('2026-01-15 12:00:00', 'UTC'));

        $this->me(User::factory()->admin()->create())
            ->assertJsonPath('plant.timezone', 'America/Chicago')
            ->assertJsonPath('plant.utc_offset', '-06:00');
    }

    public function test_falls_back_to_the_configured_zone_when_none_is_saved(): void
    {
        $this->me(User::factory()->operator()->create())
            ->assertJsonPath('plant.timezone', (string) config('app.timezone'));
    }

    public function test_a_change_in_settings_shows_on_the_next_request(): void
    {
        $user = User::factory()->operator()->create();
        TimezoneRegistry::save('Europe/Warsaw');
        $this->me($user)->assertJsonPath('plant.timezone', 'Europe/Warsaw');

        TimezoneRegistry::save('Asia/Tokyo');
        $this->me($user)->assertJsonPath('plant.timezone', 'Asia/Tokyo');
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }
}
