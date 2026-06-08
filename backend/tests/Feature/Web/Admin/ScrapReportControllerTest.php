<?php

namespace Tests\Feature\Web\Admin;

use App\Models\ScrapEntry;
use App\Models\ScrapReason;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScrapReportControllerTest extends TestCase
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

    public function test_report_page_renders_with_pareto_data(): void
    {
        $reason = ScrapReason::factory()->create(['name' => 'Porosity', 'code' => 'POR-1']);
        $wo = WorkOrder::factory()->create(['produced_qty' => 100]);
        ScrapEntry::factory()->create([
            'work_order_id' => $wo->id,
            'scrap_reason_id' => $reason->id,
            'quantity' => 20,
            'reported_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.scrap-reports.index'));

        $response->assertOk();
        $response->assertSee('Porosity');
        $response->assertSee('POR-1');
    }

    public function test_invalid_date_range_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.scrap-reports.index', [
            'date_from' => '2026-06-10',
            'date_to' => '2026-06-01',
        ]));

        $response->assertSessionHasErrors('date_to');
    }
}
