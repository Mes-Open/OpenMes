<?php

namespace Tests\Feature\Packaging;

use App\Models\LabelTemplate;
use App\Models\SerialUnit;
use App\Models\User;
use Database\Seeders\LabelTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Packing scans the process serial and gets the unit's own serial back for the carton label. */
class UnitCartonLabelTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Operator', 'web');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    private function scan(string $psn)
    {
        return $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => $psn]);
    }

    public function test_guest_cannot_scan(): void
    {
        $this->postJson(route('packaging.scan-unit'), ['psn' => 'P-1'])->assertUnauthorized();
    }

    public function test_scan_resolves_the_unit_and_records_the_packing_event(): void
    {
        $unit = SerialUnit::create(['serial_no' => 'SN-1001-0001', 'psn' => 'P-0001', 'status' => SerialUnit::STATUS_IN_PRODUCTION]);

        $this->scan(' P-0001 ')
            ->assertOk()
            ->assertJsonPath('unit.serial_no', 'SN-1001-0001')
            ->assertJsonPath('label_pdf', route('packaging.labels.serial-unit.pdf', $unit))
            ->assertJsonPath('label_zpl', route('packaging.labels.serial-unit.zpl', $unit));

        $this->assertSame('packed', $unit->history()->firstOrFail()->parameters['event']);
        $this->assertSame(SerialUnit::STATUS_COMPLETED, $unit->fresh()->status);
    }

    public function test_unknown_process_serial_is_404(): void
    {
        $this->scan('NOPE')->assertNotFound();
    }

    public function test_a_scrapped_or_blocked_unit_gets_no_carton_label(): void
    {
        SerialUnit::create(['serial_no' => 'SN-BAD', 'psn' => 'P-BAD', 'status' => SerialUnit::STATUS_SCRAPPED]);
        SerialUnit::create(['serial_no' => 'SN-HELD', 'psn' => 'P-HELD', 'status' => SerialUnit::STATUS_BLOCKED]);

        $this->scan('P-BAD')->assertUnprocessable();
        $this->scan('P-HELD')->assertUnprocessable();
        $this->assertSame(0, SerialUnit::where('psn', 'P-BAD')->firstOrFail()->history()->count());
    }

    public function test_with_shared_process_serials_the_latest_unit_wins(): void
    {
        \Illuminate\Support\Facades\DB::table('system_settings')->updateOrInsert(['key' => 'unit_psn_unique'], ['value' => 'false']);
        SerialUnit::create(['serial_no' => 'SN-OLD', 'psn' => 'P-SHARED']);
        SerialUnit::create(['serial_no' => 'SN-NEW', 'psn' => 'P-SHARED']);

        $this->scan('P-SHARED')->assertOk()->assertJsonPath('unit.serial_no', 'SN-NEW');
    }

    public function test_default_templates_include_the_serial_unit_label_and_the_pdf_renders(): void
    {
        $this->seed(LabelTemplatesSeeder::class);
        $this->assertTrue(LabelTemplate::where('type', LabelTemplate::TYPE_SERIAL_UNIT)->where('is_default', true)->exists());

        $unit = SerialUnit::create(['serial_no' => 'SN-PDF', 'psn' => 'P-PDF']);
        $this->actingAs($this->operator)
            ->get(route('packaging.labels.serial-unit.pdf', $unit))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
