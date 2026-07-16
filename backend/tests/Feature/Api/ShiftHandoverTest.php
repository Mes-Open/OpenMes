<?php

namespace Tests\Feature\Api;

use App\Models\Line;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftHandoverTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->line = Line::factory()->create();
    }

    public function test_supervisor_or_admin_can_get_shift_balance(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/shift-handover?line_id='.$this->line->id)
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'lines',
                    'selected_line_id',
                    'balance' => [
                        'line_id',
                        'shift_id',
                        'window',
                        'produced_qty',
                        'good_qty',
                        'packed_qty',
                        'shipped_qty',
                        'discrepancies',
                    ],
                    'recent',
                ],
            ]);
    }

    public function test_guest_cannot_access(): void
    {
        $this->getJson('/api/v1/shift-handover')->assertStatus(401);
    }

    public function test_operator_cannot_access(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator, 'sanctum')
            ->getJson('/api/v1/shift-handover')
            ->assertStatus(403);
    }

    public function test_admin_can_store_handover_snapshot(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/shift-handover', [
                'line_id' => $this->line->id,
                'notes' => 'End of A-shift.',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('shift_handovers', [
            'line_id' => $this->line->id,
            'confirmed_by' => $this->admin->id,
            'notes' => 'End of A-shift.',
        ]);
    }
}
