<?php

namespace Tests\Feature\Web\Operator;

use App\Models\LotSequence;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Lot\LotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The station issues process and unit serials from purpose-specific sequences. */
class IssueUnitIdentifierTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Operator', 'web');
        Role::findOrCreate('Admin', 'web');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        Carbon::setTestNow('2026-09-17 10:00:00'); // day 260, ISO week 38, Thursday
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function sequence(string $purpose, string $pattern, int $pad, ?int $productTypeId = null): LotSequence
    {
        return LotSequence::create(['name' => "seq-$purpose-".uniqid(), 'purpose' => $purpose, 'pattern' => $pattern, 'prefix' => '', 'pad_size' => $pad, 'reset_period' => 'daily', 'year_prefix' => false, 'product_type_id' => $productTypeId]);
    }

    private function issue(string $purpose, ?int $workOrderId = null)
    {
        return $this->actingAs($this->operator)->postJson(route('operator.unit-labels.issue'), ['purpose' => $purpose, 'work_order_id' => $workOrderId]);
    }

    public function test_issues_date_coded_process_and_unit_serials(): void
    {
        $this->sequence('process_serial', 'PSN-[year2][doy]-[seq]', 1);
        $this->sequence('unit_serial', 'SN [year1][week][weekday] [seq]', 4);

        $this->issue('process_serial')->assertOk()->assertJsonPath('identifier', 'PSN-26260-1');
        $this->issue('process_serial')->assertOk()->assertJsonPath('identifier', 'PSN-26260-2');
        $this->issue('unit_serial')->assertOk()->assertJsonPath('identifier', 'SN 6384 0001');

        Carbon::setTestNow('2026-09-18 06:00:00');
        $this->issue('process_serial')->assertOk()->assertJsonPath('identifier', 'PSN-26261-1');
        $this->issue('unit_serial')->assertOk()->assertJsonPath('identifier', 'SN 6385 0001');
    }

    public function test_the_products_own_sequence_wins_over_the_global_one(): void
    {
        $this->sequence('process_serial', 'G-[seq]', 1);
        $wo = WorkOrder::factory()->create();
        $this->sequence('process_serial', 'P-[seq]', 1, $wo->product_type_id);

        $this->issue('process_serial', $wo->id)->assertOk()->assertJsonPath('identifier', 'P-1');
        $this->issue('process_serial')->assertOk()->assertJsonPath('identifier', 'G-1');
    }

    public function test_lot_sequences_are_not_used_for_serials_and_vice_versa(): void
    {
        $this->sequence('lot', 'LOT-[seq]', 3);

        $this->issue('process_serial')->assertUnprocessable();
        $this->assertSame('LOT-001', app(LotService::class)->generateLot());
        $this->assertNull(app(LotService::class)->previewNext(null, LotSequence::PURPOSE_UNIT_SERIAL));
    }

    public function test_only_serial_purposes_can_be_issued(): void
    {
        $this->issue('lot')->assertUnprocessable()->assertJsonValidationErrors(['purpose']);
    }

    public function test_guest_cannot_issue(): void
    {
        $this->postJson(route('operator.unit-labels.issue'), ['purpose' => 'unit_serial'])->assertUnauthorized();
    }

    public function test_a_product_may_have_one_sequence_of_each_kind(): void
    {
        $product = ProductType::factory()->create();
        $payload = fn (string $purpose) => ['name' => "n-$purpose", 'purpose' => $purpose, 'pattern' => '[seq]', 'pad_size' => 1, 'product_type_id' => $product->id];

        $this->actingAs($this->admin)->post('/admin/lot-sequences', $payload('lot'))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post('/admin/lot-sequences', $payload('process_serial'))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->from('/admin/lot-sequences')->post('/admin/lot-sequences', $payload('process_serial'))->assertSessionHasErrors(['product_type_id']);

        $this->assertSame(2, LotSequence::where('product_type_id', $product->id)->count());
    }
}
