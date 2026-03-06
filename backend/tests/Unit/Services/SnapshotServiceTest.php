<?php

namespace Tests\Unit\Services;

use App\Models\ProcessTemplate;
use App\Services\ProcessTemplate\SnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SnapshotServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SnapshotService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SnapshotService::class);
    }

    public function test_creates_snapshot_with_all_required_keys(): void
    {
        $template = ProcessTemplate::factory()->withSteps(2)->create();

        $snapshot = $this->service->createSnapshot($template);

        $this->assertArrayHasKey('template_id', $snapshot);
        $this->assertArrayHasKey('template_name', $snapshot);
        $this->assertArrayHasKey('template_version', $snapshot);
        $this->assertArrayHasKey('product_type_id', $snapshot);
        $this->assertArrayHasKey('steps', $snapshot);
        $this->assertArrayHasKey('snapshot_created_at', $snapshot);
    }

    public function test_snapshot_contains_correct_template_data(): void
    {
        $template = ProcessTemplate::factory()->withSteps(3)->create([
            'name' => 'Test Template',
            'version' => 2,
        ]);

        $snapshot = $this->service->createSnapshot($template);

        $this->assertEquals($template->id, $snapshot['template_id']);
        $this->assertEquals('Test Template', $snapshot['template_name']);
        $this->assertEquals(2, $snapshot['template_version']);
        $this->assertEquals($template->product_type_id, $snapshot['product_type_id']);
    }

    public function test_snapshot_steps_are_ordered_by_step_number(): void
    {
        $template = ProcessTemplate::factory()->withSteps(4)->create();

        $snapshot = $this->service->createSnapshot($template);

        $stepNumbers = array_column($snapshot['steps'], 'step_number');
        $sorted = $stepNumbers;
        sort($sorted);

        $this->assertEquals($sorted, $stepNumbers);
    }

    public function test_snapshot_step_has_required_fields(): void
    {
        $template = ProcessTemplate::factory()->withSteps(1)->create();

        $snapshot = $this->service->createSnapshot($template);
        $step = $snapshot['steps'][0];

        $this->assertArrayHasKey('step_number', $step);
        $this->assertArrayHasKey('name', $step);
        $this->assertArrayHasKey('instruction', $step);
        $this->assertArrayHasKey('estimated_duration_minutes', $step);
        $this->assertArrayHasKey('workstation_id', $step);
    }

    public function test_snapshot_step_count_matches_template_steps(): void
    {
        $template = ProcessTemplate::factory()->withSteps(5)->create();

        $snapshot = $this->service->createSnapshot($template);

        $this->assertCount(5, $snapshot['steps']);
    }

    public function test_snapshot_created_at_is_iso8601(): void
    {
        $template = ProcessTemplate::factory()->withSteps(1)->create();

        $snapshot = $this->service->createSnapshot($template);

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $snapshot['snapshot_created_at']
        );
    }

    public function test_template_with_no_steps_creates_empty_steps_array(): void
    {
        $template = ProcessTemplate::factory()->create();

        $snapshot = $this->service->createSnapshot($template);

        $this->assertIsArray($snapshot['steps']);
        $this->assertCount(0, $snapshot['steps']);
    }

    public function test_modifying_template_after_snapshot_does_not_affect_snapshot(): void
    {
        $template = ProcessTemplate::factory()->withSteps(2)->create();

        $snapshot = $this->service->createSnapshot($template);

        // Modify the template
        $template->update(['name' => 'Modified Name', 'version' => 99]);

        // Snapshot should still have original data
        $this->assertEquals('Modified Name', $template->fresh()->name);
        $this->assertNotEquals('Modified Name', $snapshot['template_name']);
        $this->assertNotEquals(99, $snapshot['template_version']);
    }
}
