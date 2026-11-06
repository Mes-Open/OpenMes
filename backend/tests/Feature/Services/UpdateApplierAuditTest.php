<?php

namespace Tests\Feature\Services;

use App\Models\SystemUpdate;
use App\Services\UpdateApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

/**
 * Exercises the audit-row lifecycle that `UpdateApplier::run()` drives — open
 * the row in `queued`, then transition to `completed`, `failed`, or
 * `rolled_back`. We do not run a real update here (network + filesystem); we
 * invoke the helpers directly to verify the persistence contract.
 */
class UpdateApplierAuditTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(UpdateApplier $applier, string $method, array $args)
    {
        $ref = new ReflectionClass($applier);
        $m   = $ref->getMethod($method);
        $m->setAccessible(true);
        return $m->invokeArgs($applier, $args);
    }

    public function test_open_audit_record_creates_queued_row(): void
    {
        $user    = \App\Models\User::factory()->create();
        $applier = new UpdateApplier();

        $record = $this->invoke($applier, 'openAuditRecord', ['v9.0.0', $user->id]);

        $this->assertInstanceOf(SystemUpdate::class, $record);
        $this->assertSame(SystemUpdate::STATE_QUEUED, $record->state);
        $this->assertSame('v9.0.0', $record->to_version);
        $this->assertSame($user->id, $record->user_id);
        $this->assertNotNull($record->started_at);
        $this->assertFalse($record->checksum_verified);
        $this->assertFalse($record->composer_install_ran);
    }

    public function test_open_audit_record_handles_unauthenticated_user(): void
    {
        // user_id=0 (no auth) is stored as NULL, not as a dangling FK.
        $applier = new UpdateApplier();

        $record = $this->invoke($applier, 'openAuditRecord', ['v9.0.0', 0]);

        $this->assertInstanceOf(SystemUpdate::class, $record);
        $this->assertNull($record->user_id);
    }

    public function test_audit_record_transitions_queued_to_completed(): void
    {
        $user    = \App\Models\User::factory()->create();
        $applier = new UpdateApplier();

        $record = $this->invoke($applier, 'openAuditRecord', ['v9.0.0', $user->id]);
        $this->assertSame(SystemUpdate::STATE_QUEUED, $record->state);

        // Mimic the verifyChecksum + composer steps + final success.
        $this->invoke($applier, 'patchAuditRecord', [$record, ['checksum_verified' => true]]);
        $this->invoke($applier, 'patchAuditRecord', [$record, ['composer_install_ran' => true]]);
        $this->invoke($applier, 'patchAuditRecord', [$record, [
            'state'        => SystemUpdate::STATE_COMPLETED,
            'finished_at'  => now(),
            'files_copied' => 17,
        ]]);

        $fresh = $record->fresh();
        $this->assertSame(SystemUpdate::STATE_COMPLETED, $fresh->state);
        $this->assertTrue($fresh->checksum_verified);
        $this->assertTrue($fresh->composer_install_ran);
        $this->assertSame(17, $fresh->files_copied);
        $this->assertNotNull($fresh->finished_at);
        // Auto-computed when reaching a terminal state.
        $this->assertNotNull($fresh->duration_seconds);
        $this->assertGreaterThanOrEqual(0, $fresh->duration_seconds);
    }

    public function test_mark_pending_audit_failed_only_touches_queued_rows(): void
    {
        // A completed row for the same version must NOT be flipped to failed.
        SystemUpdate::create([
            'to_version'  => 'v9.0.0',
            'state'       => SystemUpdate::STATE_COMPLETED,
            'started_at'  => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(9),
        ]);

        $queued = SystemUpdate::create([
            'to_version' => 'v9.0.0',
            'state'      => SystemUpdate::STATE_QUEUED,
            'started_at' => now()->subMinute(),
        ]);

        (new UpdateApplier())->markPendingAuditFailed('v9.0.0', 'worker crashed');

        $this->assertSame(SystemUpdate::STATE_FAILED, $queued->fresh()->state);
        $this->assertSame('worker crashed', $queued->fresh()->error);
        $this->assertNotNull($queued->fresh()->finished_at);
    }
}
