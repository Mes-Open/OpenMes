<?php

namespace Tests\Feature\Packaging;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The scanner mode reaching the packing station.
 *
 * Settings → System has offered this choice since it was merged, the request
 * validates it, and the controller passed it as a prop — but the page never read
 * the prop, so picking `manual` changed nothing and left the operator with no way
 * to enter a code at all. The page now honours it; this pins the server half of
 * that plumbing, which is the half a test can see.
 */
class ScannerModeTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        Role::findOrCreate('Operator', 'web');
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        return $operator;
    }

    private function storeMode(string $mode): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'scanner_mode'],
            ['value' => json_encode($mode), 'updated_at' => now()],
        );
    }

    public function test_the_station_is_told_which_scanner_mode_is_configured(): void
    {
        $this->storeMode('manual');

        $this->actingAs($this->operator())
            ->get(route('packaging.station'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('packaging/Station')
                ->where('scannerMode', 'manual'));
    }

    public function test_an_installation_that_never_chose_defaults_to_the_keyboard_reader(): void
    {
        // No row at all: the common case, and the one where a wrong default would
        // silently stop every reader on the shop floor from working.
        DB::table('system_settings')->where('key', 'scanner_mode')->delete();

        $this->actingAs($this->operator())
            ->get(route('packaging.station'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('scannerMode', 'hid'));
    }

    public function test_the_mode_chosen_in_settings_is_the_one_the_station_receives(): void
    {
        Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)
            ->post(route('settings.update-system'), $this->systemSettingsPayload(['scanner_mode' => 'manual']))
            ->assertSessionHasNoErrors();

        $this->get(route('packaging.station'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('scannerMode', 'manual'));
    }

    public function test_an_unknown_mode_is_refused(): void
    {
        Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)
            ->post(route('settings.update-system'), $this->systemSettingsPayload(['scanner_mode' => 'bluetooth']))
            ->assertSessionHasErrors('scanner_mode');
    }

    /** @return array<string, mixed> */
    private function systemSettingsPayload(array $overrides = []): array
    {
        return array_replace([
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
}
