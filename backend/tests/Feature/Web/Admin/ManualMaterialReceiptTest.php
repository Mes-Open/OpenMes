<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualMaterialReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_reconciles_negative_balance_and_retry_does_not_double_credit(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $material = Material::factory()->create(['stock_quantity' => -10, 'reserved_quantity' => 0]);
        $url = "/admin/materials/{$material->id}/receipts";
        foreach ([1, 2] as $attempt) {
            $this->actingAs($admin)->post($url, ['quantity' => 100, 'reference' => 'PZ-001'])
                ->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertSame(90.0, (float) $material->fresh()->stock_quantity);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseHas('stock_movements', [
            'material_id' => $material->id, 'quantity' => 100, 'balance_after' => 90,
            'performed_by' => $admin->id, 'reason' => 'PZ-001', 'source_type' => 'manual_receipt',
        ]);
        $this->post($url, ['quantity' => 50, 'reference' => 'PZ-001'])->assertSessionHasErrors('reference');
        $this->assertSame(90.0, (float) $material->fresh()->stock_quantity);
        $this->post($url, ['quantity' => -1, 'reference' => 'PZ-002'])->assertSessionHasErrors('quantity');
        $this->post($url, ['quantity' => 1, 'reference' => ''])->assertSessionHasErrors('reference');
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_operator_cannot_receive_material(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $operator = User::factory()->create();
        $operator->assignRole('Operator');
        $material = Material::factory()->create(['stock_quantity' => 0]);
        $this->actingAs($operator)->post("/admin/materials/{$material->id}/receipts", [
            'quantity' => 100, 'reference' => 'PZ-001',
        ])->assertForbidden();
        $this->assertSame(0.0, (float) $material->fresh()->stock_quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }
}
