<?php

namespace Tests\Unit\Models;

use App\Models\ProcessSegment;
use App\Models\ProcessTemplate;
use App\Models\Skill;
use App\Models\TemplateStep;
use App\Models\User;
use App\Models\WorkstationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessSegmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_workstation_type_and_created_by_relations(): void
    {
        $wsType = WorkstationType::create(['code' => 'WT-PSG', 'name' => 'Press']);
        $user   = User::factory()->create();

        $segment = ProcessSegment::factory()->create([
            'workstation_type_id' => $wsType->id,
            'created_by_id'       => $user->id,
        ]);

        $this->assertSame($wsType->id, $segment->workstationType->id);
        $this->assertSame($user->id, $segment->createdBy->id);
    }

    public function test_template_steps_relation(): void
    {
        $segment  = ProcessSegment::factory()->create();
        $template = ProcessTemplate::factory()->create();

        TemplateStep::create([
            'process_template_id' => $template->id,
            'process_segment_id'  => $segment->id,
            'step_number'         => 1,
            'name'                => 'Linked step',
        ]);

        $this->assertCount(1, $segment->templateSteps);
        $this->assertSame('Linked step', $segment->templateSteps->first()->name);
    }

    public function test_required_skills_helper_returns_collection(): void
    {
        $weld = Skill::create(['code' => 'WELD', 'name' => 'Welding']);
        $qc   = Skill::create(['code' => 'QC',   'name' => 'Quality Control']);
        $other = Skill::create(['code' => 'OTH',  'name' => 'Other']);

        $segment = ProcessSegment::factory()->create([
            'required_skill_ids' => [$weld->id, $qc->id],
        ]);

        $skills = $segment->requiredSkills();
        $this->assertCount(2, $skills);
        $this->assertTrue($skills->pluck('id')->contains($weld->id));
        $this->assertTrue($skills->pluck('id')->contains($qc->id));
        $this->assertFalse($skills->pluck('id')->contains($other->id));
    }

    public function test_required_skills_helper_returns_empty_collection_when_none(): void
    {
        $segment = ProcessSegment::factory()->create(['required_skill_ids' => []]);
        $this->assertCount(0, $segment->requiredSkills());

        $segment2 = ProcessSegment::factory()->create(['required_skill_ids' => null]);
        $this->assertCount(0, $segment2->requiredSkills());
    }

    public function test_template_step_effective_instruction_falls_back_to_segment(): void
    {
        $segment = ProcessSegment::factory()->create([
            'standard_instruction' => 'SEGMENT instructions',
        ]);
        $template = ProcessTemplate::factory()->create();

        // Step without its own instruction → fall back to segment.
        $stepFallback = TemplateStep::create([
            'process_template_id' => $template->id,
            'process_segment_id'  => $segment->id,
            'step_number'         => 1,
            'name'                => 'Without override',
        ]);

        // Step with its own instruction → override wins.
        $stepOverride = TemplateStep::create([
            'process_template_id' => $template->id,
            'process_segment_id'  => $segment->id,
            'step_number'         => 2,
            'name'                => 'With override',
            'instruction'         => 'STEP instructions',
        ]);

        $this->assertSame('SEGMENT instructions', $stepFallback->fresh('processSegment')->effectiveInstruction());
        $this->assertSame('STEP instructions', $stepOverride->fresh('processSegment')->effectiveInstruction());
    }

    public function test_template_step_effective_duration_falls_back_to_segment(): void
    {
        $segment = ProcessSegment::factory()->create([
            'estimated_duration_minutes' => 45,
        ]);
        $template = ProcessTemplate::factory()->create();

        $fallback = TemplateStep::create([
            'process_template_id' => $template->id,
            'process_segment_id'  => $segment->id,
            'step_number'         => 1,
            'name'                => 'fallback',
        ]);

        $override = TemplateStep::create([
            'process_template_id' => $template->id,
            'process_segment_id'  => $segment->id,
            'step_number'         => 2,
            'name'                => 'override',
            'estimated_duration_minutes' => 120,
        ]);

        $this->assertSame(45, $fallback->fresh('processSegment')->effectiveDuration());
        $this->assertSame(120, $override->fresh('processSegment')->effectiveDuration());
    }
}
