<?php

namespace Tests\Feature;

use App\Models\ProcessTemplate;
use App\Models\ProcessTemplatePhoto;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Operators at a workstation must see the work-instruction photos of the
 * process their order was built from — surfaced on the work-order detail page
 * (the photos themselves live on the process template, PR #56).
 */
class OperatorProcessPhotosTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Operator', 'guard_name' => 'web']);
        Role::create(['name' => 'Supervisor', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);

        $this->operator = User::factory()->create(['account_type' => 'operator']);
        $this->operator->assignRole('Operator');
    }

    private function templateIdOf(WorkOrder $wo): int
    {
        return $wo->process_snapshot['template_id'];
    }

    /** The detail page requires the order's line selected in session. */
    private function showOrder(WorkOrder $wo)
    {
        return $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => $wo->line_id])
            ->get("/operator/work-order/{$wo->id}");
    }

    public function test_operator_sees_photos_of_their_orders_process(): void
    {
        $wo = WorkOrder::factory()->create();
        $templateId = $this->templateIdOf($wo);

        $photos = ProcessTemplatePhoto::factory()->count(3)->create([
            'process_template_id' => $templateId,
        ]);

        $response = $this->showOrder($wo);

        $response->assertOk();
        $props = $response->getOriginalContent()->getData()['page']['props'];

        $this->assertCount(3, $props['processPhotos']);
        $ids = array_column($props['processPhotos'], 'id');
        $this->assertEqualsCanonicalizing($photos->pluck('id')->all(), $ids);

        // Each entry exposes a stream URL, never a storage path.
        foreach ($props['processPhotos'] as $p) {
            $this->assertStringContainsString("/process-templates/{$templateId}/photos/{$p['id']}", $p['url']);
            $this->assertArrayNotHasKey('storage_path', $p);
        }
    }

    public function test_photos_of_other_processes_are_not_shown(): void
    {
        $wo = WorkOrder::factory()->create();

        // Photos belonging to an unrelated template must not leak in.
        $otherTemplate = ProcessTemplate::factory()->create();
        ProcessTemplatePhoto::factory()->count(2)->create([
            'process_template_id' => $otherTemplate->id,
        ]);

        $response = $this->showOrder($wo);

        $response->assertOk();
        $this->assertCount(0, $response->getOriginalContent()->getData()['page']['props']['processPhotos']);
    }

    public function test_no_photos_yields_empty_list(): void
    {
        $wo = WorkOrder::factory()->create();

        $response = $this->showOrder($wo);

        $response->assertOk();
        $this->assertSame([], $response->getOriginalContent()->getData()['page']['props']['processPhotos']);
    }

    public function test_photos_respect_sort_order(): void
    {
        $wo = WorkOrder::factory()->create();
        $templateId = $this->templateIdOf($wo);

        $second = ProcessTemplatePhoto::factory()->create(['process_template_id' => $templateId, 'sort_order' => 2]);
        $first = ProcessTemplatePhoto::factory()->create(['process_template_id' => $templateId, 'sort_order' => 1]);

        $response = $this->showOrder($wo);

        $ids = array_column($response->getOriginalContent()->getData()['page']['props']['processPhotos'], 'id');
        $this->assertSame([$first->id, $second->id], $ids);
    }
}
