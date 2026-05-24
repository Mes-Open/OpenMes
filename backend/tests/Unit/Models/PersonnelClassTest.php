<?php

namespace Tests\Unit\Models;

use App\Models\PersonnelClass;
use App\Models\Skill;
use App\Models\User;
use App\Models\Worker;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonnelClassTest extends TestCase
{
    use RefreshDatabase;

    private function attachSkill(
        Worker $worker,
        Skill $skill,
        string $certLevel = 'operator',
        ?string $until = null,
        ?int $byUserId = null
    ): void {
        $worker->skills()->syncWithoutDetaching([
            $skill->id => [
                'cert_level'      => $certLevel,
                'certified_from'  => now()->subDay()->toDateString(),
                'certified_until' => $until,
                'certified_by_id' => $byUserId,
            ],
        ]);
    }

    public function test_relations_and_required_skills_helper(): void
    {
        $weld = Skill::create(['code' => 'WELD', 'name' => 'Welding']);
        $qc   = Skill::create(['code' => 'QC',   'name' => 'Quality Control']);
        Skill::create(['code' => 'OTH', 'name' => 'Other']);

        $pc = PersonnelClass::factory()->create([
            'required_skill_ids' => [$weld->id, $qc->id],
        ]);

        $skills = $pc->requiredSkills();
        $this->assertCount(2, $skills);
        $this->assertTrue($skills->pluck('id')->contains($weld->id));

        // workers() relation: empty + adding workers attaches them.
        $this->assertCount(0, $pc->workers);
        $worker = Worker::factory()->create(['personnel_class_id' => $pc->id]);
        $this->assertCount(1, $pc->fresh()->workers);
        $this->assertSame($pc->id, $worker->personnelClass->id);
    }

    public function test_level_meets_uses_strict_ranking(): void
    {
        $pc = PersonnelClass::factory()->create();

        $this->assertTrue($pc->levelMeets('expert', 'operator'));
        $this->assertTrue($pc->levelMeets('trainer', 'expert'));
        $this->assertTrue($pc->levelMeets('operator', 'operator'));
        $this->assertFalse($pc->levelMeets('trainee', 'operator'));
        $this->assertFalse($pc->levelMeets('operator', 'expert'));
        // Unknown levels rank as 0 → cannot satisfy a real requirement.
        $this->assertFalse($pc->levelMeets('bogus', 'trainee'));
    }

    public function test_worker_meets_requirements_with_no_required_skills_is_true(): void
    {
        $pc     = PersonnelClass::factory()->create(['required_skill_ids' => null]);
        $worker = Worker::factory()->create();

        $this->assertTrue($pc->workerMeetsRequirements($worker));
    }

    public function test_worker_meets_requirements_returns_true_when_all_skills_present_at_level(): void
    {
        $weld = Skill::create(['code' => 'WELD', 'name' => 'Welding']);
        $qc   = Skill::create(['code' => 'QC',   'name' => 'QC']);

        $pc = PersonnelClass::factory()->create([
            'required_skill_ids'          => [$weld->id, $qc->id],
            'default_required_cert_level' => [$weld->id => 'operator', $qc->id => 'expert'],
        ]);

        $worker = Worker::factory()->create();
        $this->attachSkill($worker, $weld, 'operator');
        $this->attachSkill($worker, $qc, 'expert');

        $this->assertTrue($pc->workerMeetsRequirements($worker));
    }

    public function test_worker_meets_requirements_fails_when_level_too_low(): void
    {
        $weld = Skill::create(['code' => 'WELD', 'name' => 'Welding']);

        $pc = PersonnelClass::factory()->create([
            'required_skill_ids'          => [$weld->id],
            'default_required_cert_level' => [$weld->id => 'expert'],
        ]);

        $worker = Worker::factory()->create();
        $this->attachSkill($worker, $weld, 'operator');

        $this->assertFalse($pc->workerMeetsRequirements($worker));
    }

    public function test_worker_meets_requirements_fails_when_skill_missing(): void
    {
        $weld = Skill::create(['code' => 'WELD', 'name' => 'Welding']);
        $qc   = Skill::create(['code' => 'QC',   'name' => 'QC']);

        $pc = PersonnelClass::factory()->create([
            'required_skill_ids' => [$weld->id, $qc->id],
        ]);

        $worker = Worker::factory()->create();
        $this->attachSkill($worker, $weld, 'expert');
        // qc skill not attached

        $this->assertFalse($pc->workerMeetsRequirements($worker));
    }

    public function test_worker_meets_requirements_treats_null_until_as_never_expires(): void
    {
        $weld = Skill::create(['code' => 'WELD', 'name' => 'Welding']);

        $pc = PersonnelClass::factory()->create([
            'required_skill_ids' => [$weld->id],
        ]);

        $worker = Worker::factory()->create();
        // null certified_until → never expires.
        $this->attachSkill($worker, $weld, 'operator', null);

        $this->assertTrue($pc->workerMeetsRequirements($worker));
    }

    public function test_worker_meets_requirements_fails_when_certification_expired(): void
    {
        $weld = Skill::create(['code' => 'WELD', 'name' => 'Welding']);

        $pc = PersonnelClass::factory()->create([
            'required_skill_ids' => [$weld->id],
        ]);

        $worker = Worker::factory()->create();
        // Expired yesterday.
        $this->attachSkill($worker, $weld, 'operator', Carbon::yesterday()->toDateString());

        $this->assertFalse($pc->workerMeetsRequirements($worker));
    }

    public function test_worker_meets_requirements_with_trainer_satisfies_any_level(): void
    {
        $weld = Skill::create(['code' => 'WELD', 'name' => 'Welding']);

        $pc = PersonnelClass::factory()->create([
            'required_skill_ids'          => [$weld->id],
            'default_required_cert_level' => [$weld->id => 'expert'],
        ]);

        $worker = Worker::factory()->create();
        $this->attachSkill($worker, $weld, 'trainer');

        $this->assertTrue($pc->workerMeetsRequirements($worker));
    }

    public function test_expiring_skills_helper_returns_only_within_window(): void
    {
        $weld = Skill::create(['code' => 'WELD', 'name' => 'Welding']);
        $qc   = Skill::create(['code' => 'QC',   'name' => 'QC']);
        $cnc  = Skill::create(['code' => 'CNC',  'name' => 'CNC']);
        $forever = Skill::create(['code' => 'FOR', 'name' => 'Forever']);

        $worker = Worker::factory()->create();
        $this->attachSkill($worker, $weld, 'operator', now()->addDays(5)->toDateString());   // expiring
        $this->attachSkill($worker, $qc, 'operator',   now()->addDays(60)->toDateString());  // out of window
        $this->attachSkill($worker, $cnc, 'operator',  now()->subDay()->toDateString());     // already expired
        $this->attachSkill($worker, $forever, 'operator', null);                              // never expires

        $expiring = $worker->expiringSkills(30);
        $this->assertCount(1, $expiring);
        $this->assertSame($weld->id, $expiring->first()->id);
    }

    public function test_expired_skills_helper_returns_only_past_due(): void
    {
        $weld = Skill::create(['code' => 'WELD', 'name' => 'Welding']);
        $qc   = Skill::create(['code' => 'QC',   'name' => 'QC']);

        $worker = Worker::factory()->create();
        $this->attachSkill($worker, $weld, 'operator', now()->subDays(5)->toDateString());  // expired
        $this->attachSkill($worker, $qc, 'operator',   now()->addDays(5)->toDateString());  // valid

        $expired = $worker->expiredSkills();
        $this->assertCount(1, $expired);
        $this->assertSame($weld->id, $expired->first()->id);
    }
}
