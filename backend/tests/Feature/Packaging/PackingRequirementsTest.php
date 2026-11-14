<?php

namespace Tests\Feature\Packaging;

use App\Models\SerialUnit;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A plant whose ERP receives finished units by serial number on an order can
 * refuse packing a unit that has no serial number yet or no work order.
 */
class PackingRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private WorkOrder $wo;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Operator', 'web');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->wo = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_IN_PROGRESS]);
    }

    private function require(string $key): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => $key], ['value' => 'true']);
    }

    private function scan(string $identifier)
    {
        return $this->actingAs($this->operator)->postJson(route('packaging.scan-unit'), ['psn' => $identifier]);
    }

    public function test_by_default_a_unit_with_only_its_psn_and_no_order_is_packed(): void
    {
        SerialUnit::create(['psn' => 'P-1', 'status' => SerialUnit::STATUS_IN_PRODUCTION]);

        $this->scan('P-1')->assertOk();
    }

    public function test_a_unit_without_a_serial_number_is_refused_when_required(): void
    {
        $this->require('unit_pack_requires_serial');
        SerialUnit::create(['psn' => 'P-2', 'work_order_id' => $this->wo->id, 'status' => SerialUnit::STATUS_IN_PRODUCTION]);
        SerialUnit::create(['serial_no' => 'SN-3', 'psn' => 'P-3', 'work_order_id' => $this->wo->id, 'status' => SerialUnit::STATUS_IN_PRODUCTION]);

        $this->scan('P-2')->assertStatus(422)->assertJsonPath('message', 'Unit P-2 has no serial number yet - apply its product label before packing.');
        $this->assertNull(SerialUnit::where('psn', 'P-2')->value('packed_at'));
        $this->scan('P-3')->assertOk();
    }

    public function test_a_unit_without_a_work_order_is_refused_when_required(): void
    {
        $this->require('unit_pack_requires_order');
        SerialUnit::create(['serial_no' => 'SN-4', 'status' => SerialUnit::STATUS_IN_PRODUCTION]);

        $this->scan('SN-4')->assertStatus(422)->assertJsonPath('message', 'Unit SN-4 is not on a work order and cannot be packed.');
    }
}
