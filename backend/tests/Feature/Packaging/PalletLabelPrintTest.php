<?php

namespace Tests\Feature\Packaging;

use App\Models\LabelTemplate;
use App\Models\Pallet;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PalletLabelPrintTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Supervisor', 'guard_name' => 'web']);
        Role::create(['name' => 'Operator', 'guard_name' => 'web']);

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    private function palletTemplate(): LabelTemplate
    {
        return LabelTemplate::create([
            'name' => 'Standard Pallet',
            'type' => LabelTemplate::TYPE_PALLET,
            'size' => '100x100',
            'fields_config' => LabelTemplate::defaultFieldsFor(LabelTemplate::TYPE_PALLET),
            'barcode_format' => 'code128',
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    private function pallet(): Pallet
    {
        $wo = WorkOrder::factory()->create();

        return Pallet::create(['work_order_id' => $wo->id, 'status' => 'open', 'qty' => 5]);
    }

    public function test_pallet_label_pdf_streams(): void
    {
        $this->palletTemplate();
        $pallet = $this->pallet();

        $response = $this->actingAs($this->operator)
            ->get(route('packaging.labels.pallet.pdf', $pallet));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_pallet_label_zpl_contains_pallet_no(): void
    {
        $this->palletTemplate();
        $pallet = $this->pallet();

        $response = $this->actingAs($this->operator)
            ->get(route('packaging.labels.pallet.zpl', $pallet));

        $response->assertOk();
        $this->assertStringContainsString($pallet->pallet_no, $response->getContent());
    }

    public function test_pallet_label_404_when_no_template_configured(): void
    {
        $pallet = $this->pallet();

        $this->actingAs($this->operator)
            ->get(route('packaging.labels.pallet.pdf', $pallet))
            ->assertNotFound();
    }

    public function test_guest_is_redirected_from_pallet_label_routes(): void
    {
        $this->palletTemplate();
        $pallet = $this->pallet();

        $this->get(route('packaging.labels.pallet.pdf', $pallet))->assertRedirect();
        $this->get(route('packaging.labels.pallet.zpl', $pallet))->assertRedirect();
    }

    public function test_user_without_packaging_role_is_forbidden(): void
    {
        $this->palletTemplate();
        $pallet = $this->pallet();
        $user = User::factory()->create(); // no Operator/Supervisor/Admin role

        $this->actingAs($user)
            ->get(route('packaging.labels.pallet.pdf', $pallet))
            ->assertForbidden();
        $this->actingAs($user)
            ->get(route('packaging.labels.pallet.zpl', $pallet))
            ->assertForbidden();
    }
}
