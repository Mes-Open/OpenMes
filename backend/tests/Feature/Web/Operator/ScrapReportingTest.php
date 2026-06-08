<?php

namespace Tests\Feature\Web\Operator;

use App\Models\ScrapReason;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScrapReportingTest extends TestCase
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

    public function test_operator_can_report_scrap_against_work_order_on_their_line(): void
    {
        $wo = WorkOrder::factory()->create();
        $reason = ScrapReason::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $wo->line_id])
            ->post(route('operator.scrap.store'), [
                'work_order_id' => $wo->id,
                'scrap_reason_id' => $reason->id,
                'quantity' => 7.5,
                'notes' => 'Cracked during handling',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('scrap_entries', [
            'work_order_id' => $wo->id,
            'scrap_reason_id' => $reason->id,
            'quantity' => 7.5,
            'reported_by' => $this->operator->id,
        ]);
    }

    public function test_scrap_against_wrong_line_is_blocked(): void
    {
        $wo = WorkOrder::factory()->create();
        $reason = ScrapReason::factory()->create();

        $response = $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $wo->line_id + 999])
            ->post(route('operator.scrap.store'), [
                'work_order_id' => $wo->id,
                'scrap_reason_id' => $reason->id,
                'quantity' => 5,
            ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('scrap_entries', ['work_order_id' => $wo->id]);
    }

    public function test_inactive_reason_is_rejected(): void
    {
        $wo = WorkOrder::factory()->create();
        $reason = ScrapReason::factory()->inactive()->create();

        $response = $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $wo->line_id])
            ->post(route('operator.scrap.store'), [
                'work_order_id' => $wo->id,
                'scrap_reason_id' => $reason->id,
                'quantity' => 5,
            ]);

        $response->assertSessionHasErrors('scrap_reason_id');
    }
}
