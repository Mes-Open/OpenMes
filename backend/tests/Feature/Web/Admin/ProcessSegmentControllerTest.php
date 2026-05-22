<?php

namespace Tests\Feature\Web\Admin;

use App\Models\ProcessSegment;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProcessSegmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Supervisor', 'web');
        Role::findOrCreate('Operator', 'web');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'code'                       => 'PSG-INJ-60',
            'name'                       => 'Injection Molding 60s cycle',
            'description'                => 'Standard production injection cycle',
            'segment_type'               => ProcessSegment::TYPE_PRODUCTION,
            'estimated_duration_minutes' => 60,
            'required_operators'         => 1,
            'standard_instruction'       => 'Run injection at 220°C, dwell 60s.',
            'required_skill_ids'         => [],
            'parameters_raw'             => '{"temperature_c": 220}',
            'is_active'                  => '1',
        ], $overrides);
    }

    public function test_admin_can_list_segments(): void
    {
        ProcessSegment::factory()->create(['code' => 'PSG-LIST-1', 'name' => 'Listed Segment']);

        $response = $this->actingAs($this->admin)->get(route('admin.process-segments.index'));

        $response->assertStatus(200);
        $response->assertSee('Listed Segment');
    }

    public function test_admin_can_view_segment_show_with_usage(): void
    {
        $segment  = ProcessSegment::factory()->create(['code' => 'PSG-SHOW']);
        $template = ProcessTemplate::factory()->create();
        TemplateStep::create([
            'process_template_id' => $template->id,
            'process_segment_id'  => $segment->id,
            'step_number'         => 1,
            'name'                => 'Linked step',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.process-segments.show', $segment));

        $response->assertStatus(200);
        $response->assertSee('PSG-SHOW');
        $response->assertSee('Linked step');
    }

    public function test_admin_can_create_segment(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.process-segments.store'), $this->validPayload());

        $response->assertRedirect(route('admin.process-segments.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('process_segments', [
            'code'         => 'PSG-INJ-60',
            'segment_type' => ProcessSegment::TYPE_PRODUCTION,
            'is_active'    => true,
        ]);

        $segment = ProcessSegment::where('code', 'PSG-INJ-60')->first();
        $this->assertSame(['temperature_c' => 220], $segment->parameters);
    }

    public function test_admin_can_edit_segment(): void
    {
        $segment = ProcessSegment::factory()->create(['code' => 'PSG-EDIT', 'name' => 'Original']);

        $payload = $this->validPayload([
            'code' => 'PSG-EDIT',
            'name' => 'Renamed',
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.process-segments.update', $segment), $payload);

        $response->assertRedirect(route('admin.process-segments.show', $segment));
        $this->assertDatabaseHas('process_segments', [
            'id'   => $segment->id,
            'name' => 'Renamed',
        ]);
    }

    public function test_admin_can_delete_unused_segment(): void
    {
        $segment = ProcessSegment::factory()->create();

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.process-segments.destroy', $segment));

        $response->assertRedirect(route('admin.process-segments.index'));
        $this->assertDatabaseMissing('process_segments', ['id' => $segment->id]);
    }

    public function test_delete_blocked_when_segment_in_use(): void
    {
        $segment  = ProcessSegment::factory()->create();
        $template = ProcessTemplate::factory()->create();
        TemplateStep::create([
            'process_template_id' => $template->id,
            'process_segment_id'  => $segment->id,
            'step_number'         => 1,
            'name'                => 'Uses segment',
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.process-segments.destroy', $segment));

        $response->assertRedirect(route('admin.process-segments.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('process_segments', ['id' => $segment->id]);
    }

    public function test_non_admin_is_forbidden_from_segments_index(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor');

        $response = $this->actingAs($supervisor)->get(route('admin.process-segments.index'));
        $response->assertStatus(403);
    }

    public function test_index_can_filter_by_segment_type(): void
    {
        ProcessSegment::factory()->create([
            'code' => 'PSG-INSP-1',
            'name' => 'Inspection ABC',
            'segment_type' => ProcessSegment::TYPE_INSPECTION,
        ]);
        ProcessSegment::factory()->create([
            'code' => 'PSG-PROD-1',
            'name' => 'Production XYZ',
            'segment_type' => ProcessSegment::TYPE_PRODUCTION,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.process-segments.index', ['segment_type' => 'inspection']));

        $response->assertStatus(200);
        $response->assertSee('Inspection ABC');
        $response->assertDontSee('Production XYZ');
    }
}
