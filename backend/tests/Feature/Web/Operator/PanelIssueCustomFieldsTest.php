<?php

namespace Tests\Feature\Web\Operator;

use App\Models\CustomFieldDefinition;
use App\Models\Issue;
use App\Models\IssueType;
use App\Models\Line;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Report issue from the execution panel (Queue / Workstation) asks for the
 * issue custom fields like the order page does: a required one would refuse
 * the report with nothing on screen to fill in.
 */
class PanelIssueCustomFieldsTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->line = Line::factory()->create();
        $this->operator = User::factory()->create()->assignRole('Operator');
        $this->operator->lines()->attach($this->line->id);
        CustomFieldDefinition::create([
            'entity_type' => 'issue', 'key' => 'root_cause', 'label' => 'Root cause',
            'type' => 'text', 'required' => true, 'position' => 1,
        ]);
    }

    private function asOperator(): static
    {
        return $this->actingAs($this->operator)->withSession(['selected_line_id' => $this->line->id]);
    }

    public function test_queue_and_workstation_pages_carry_the_issue_custom_fields(): void
    {
        foreach (['/operator/queue' => 'operator/Queue', '/operator/workstation' => 'operator/Workstation'] as $url => $component) {
            $this->asOperator()->get($url)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
                ->component($component)
                ->has('issueCustomFields', 1)
                ->where('issueCustomFields.0.key', 'root_cause')
                ->where('issueCustomFields.0.required', true));
        }
    }

    public function test_the_report_needs_the_required_field_and_keeps_it(): void
    {
        $order = WorkOrder::factory()->create(['line_id' => $this->line->id, 'status' => WorkOrder::STATUS_IN_PROGRESS]);
        $type = IssueType::factory()->create(['is_blocking' => false]);
        $body = ['work_order_id' => $order->id, 'issue_type_id' => $type->id, 'title' => 'Jammed feeder'];

        $this->asOperator()->post('/operator/issue', $body)->assertSessionHasErrors('custom_fields.root_cause');
        $this->assertSame(0, Issue::count());

        $this->asOperator()->post('/operator/issue', $body + ['custom_fields' => ['root_cause' => 'Worn belt']])->assertSessionHasNoErrors();
        $this->assertSame('Worn belt', Issue::firstOrFail()->custom_fields['root_cause'] ?? null);
    }
}
