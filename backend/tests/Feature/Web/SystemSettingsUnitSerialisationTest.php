<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Support\UnitSerialisation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemSettingsUnitSerialisationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Admin', 'web');
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

    public function test_defaults_apply_when_nothing_is_stored(): void
    {
        $s = app(UnitSerialisation::class);

        $this->assertTrue($s->get('unit_identifier_normalize'));
        $this->assertTrue($s->get('unit_psn_unique'));
        $this->assertFalse($s->get('unit_psn_required'));
        $this->assertSame(1, $s->get('unit_test_max_attempts'));
        $this->assertSame('SN-1001-0001', $s->normalize(' sn-1001 -0001 '));
    }

    public function test_admin_saves_the_serialisation_settings(): void
    {
        $this->actingAs($this->admin)
            ->post('/settings/system', $this->payload([
                'unit_serial_pattern' => '^SN-[0-9]{4}-[0-9]{4}$',
                'unit_psn_pattern' => '^[0-9]{3}-[0-9]{2}-[0-9]+$',
                'unit_psn_required' => true,
                'unit_psn_unique' => false,
                'unit_identifier_normalize' => false,
                'unit_test_fail_policy' => 'scrap',
                'unit_test_max_attempts' => 5,
            ]))
            ->assertSessionHasNoErrors();

        $stored = fn (string $k) => json_decode(DB::table('system_settings')->where('key', $k)->value('value'), true);
        $this->assertSame('^SN-[0-9]{4}-[0-9]{4}$', $stored('unit_serial_pattern'));
        $this->assertTrue($stored('unit_psn_required'));
        $this->assertFalse($stored('unit_psn_unique'));
        $this->assertSame('scrap', $stored('unit_test_fail_policy'));
        $this->assertSame(5, $stored('unit_test_max_attempts'));

        app(UnitSerialisation::class)->forget();
        // Normalisation off: surrounding whitespace still goes, the identifier itself is kept as scanned.
        $this->assertSame('sn 1001 a', app(UnitSerialisation::class)->normalize(' sn 1001 a '));
    }

    public function test_admin_switches_lot_tracking_and_its_strategy_from_the_page(): void
    {
        $this->actingAs($this->admin)
            ->post('/settings/system', $this->payload(['lot_tracking_enabled' => true, 'lot_picking_strategy' => 'manual']))
            ->assertSessionHasNoErrors();
        $stored = fn (string $k) => json_decode(DB::table('system_settings')->where('key', $k)->value('value'), true);
        $this->assertTrue($stored('lot_tracking_enabled'));
        $this->assertSame('manual', $stored('lot_picking_strategy'));
        $this->assertTrue(app(\App\Services\Material\LotPickingService::class)->isLotTrackingEnabled());

        $this->actingAs($this->admin)->get('/settings/system')
            ->assertInertia(fn ($page) => $page->where('settings.lot_tracking_enabled', true)->where('settings.lot_picking_strategy', 'manual'));

        $this->actingAs($this->admin)
            ->post('/settings/system', $this->payload(['lot_tracking_enabled' => true, 'lot_picking_strategy' => 'random']))
            ->assertSessionHasErrors('lot_picking_strategy');
    }

    public function test_an_invalid_regex_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->from('/settings/system')
            ->post('/settings/system', $this->payload(['unit_serial_pattern' => '^[0-9(']))
            ->assertSessionHasErrors(['unit_serial_pattern']);
    }
}
