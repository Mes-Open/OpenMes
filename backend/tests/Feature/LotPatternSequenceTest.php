<?php

namespace Tests\Feature;

use App\Models\LotSequence;
use App\Models\ProductType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LotPatternSequenceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Operator', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ── Pattern generation ───────────────────────────────────────────────

    public function test_generates_lot_from_pattern(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 6, 14, 0, 0));

        $sequence = LotSequence::factory()->create([
            'pattern' => 'test-[date]-[seq]-[hour]',
            'pad_size' => 4,
        ]);

        $this->assertSame('test-20260606-0001-14', $sequence->generateNext());
        $this->assertSame('test-20260606-0002-14', $sequence->generateNext());
    }

    public function test_pattern_uses_product_type_code(): void
    {
        $productType = ProductType::factory()->create(['code' => 'FILTER']);
        $sequence = LotSequence::factory()->forProductType($productType)->create([
            'pattern' => '[product]-[seq]',
            'pad_size' => 3,
        ]);

        $this->assertSame('FILTER-001', $sequence->generateNext());
    }

    public function test_legacy_format_unchanged_when_pattern_is_null(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 6, 14, 0, 0));

        $sequence = LotSequence::factory()->create([
            'pattern' => null,
            'prefix' => 'FLT',
            'pad_size' => 4,
            'year_prefix' => true,
        ]);

        $this->assertSame('FLT-2026-0001', $sequence->generateNext());
    }

    public function test_preview_does_not_increment(): void
    {
        $sequence = LotSequence::factory()->create([
            'pattern' => 'X-[seq]',
            'pad_size' => 2,
        ]);

        $this->assertSame('X-01', $sequence->previewNext());
        $this->assertSame('X-01', $sequence->previewNext());
        $this->assertSame('X-01', $sequence->generateNext());
    }

    // ── Periodic reset ───────────────────────────────────────────────────

    public function test_daily_reset_restarts_counter_on_new_day(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 6, 23, 0, 0));

        $sequence = LotSequence::factory()->create([
            'pattern' => '[date]-[seq]',
            'pad_size' => 3,
            'reset_period' => 'daily',
        ]);

        $this->assertSame('20260606-001', $sequence->generateNext());
        $this->assertSame('20260606-002', $sequence->generateNext());

        Carbon::setTestNow(Carbon::create(2026, 6, 7, 1, 0, 0));

        $this->assertSame('20260607-001', $sequence->fresh()->generateNext());
    }

    public function test_hourly_reset_restarts_counter_on_new_hour(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 6, 14, 59, 0));

        $sequence = LotSequence::factory()->create([
            'pattern' => '[hour]-[seq]',
            'pad_size' => 2,
            'reset_period' => 'hourly',
        ]);

        $this->assertSame('14-01', $sequence->generateNext());

        Carbon::setTestNow(Carbon::create(2026, 6, 6, 15, 0, 0));

        $this->assertSame('15-01', $sequence->fresh()->generateNext());
        $this->assertSame('15-02', $sequence->fresh()->generateNext());
    }

    public function test_no_reset_keeps_counting_across_days(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 6, 12, 0, 0));

        $sequence = LotSequence::factory()->create([
            'pattern' => 'X-[seq]',
            'pad_size' => 2,
            'reset_period' => 'none',
        ]);

        $this->assertSame('X-01', $sequence->generateNext());

        Carbon::setTestNow(Carbon::create(2026, 6, 7, 12, 0, 0));

        $this->assertSame('X-02', $sequence->fresh()->generateNext());
    }

    // ── Web admin CRUD validation ────────────────────────────────────────

    public function test_admin_can_create_pattern_sequence(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/lot-sequences', [
            'name' => 'Pattern Seq',
            'pattern' => 'test-[date]-[seq]-[hour]',
            'pad_size' => 4,
            'reset_period' => 'daily',
        ]);

        $response->assertRedirect(route('admin.lot-sequences.index'));
        $this->assertDatabaseHas('lot_sequences', [
            'name' => 'Pattern Seq',
            'pattern' => 'test-[date]-[seq]-[hour]',
            'reset_period' => 'daily',
        ]);
    }

    public function test_pattern_without_seq_token_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/lot-sequences', [
            'name' => 'Bad Seq',
            'pattern' => 'test-[date]',
        ]);

        $response->assertSessionHasErrors('pattern');
    }

    public function test_pattern_with_unknown_token_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/lot-sequences', [
            'name' => 'Bad Seq',
            'pattern' => '[seq]-[banana]',
        ]);

        $response->assertSessionHasErrors('pattern');
    }

    public function test_prefix_required_without_pattern(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/lot-sequences', [
            'name' => 'Legacy Seq',
        ]);

        $response->assertSessionHasErrors('prefix');
    }

    public function test_invalid_reset_period_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/lot-sequences', [
            'name' => 'Bad Seq',
            'pattern' => '[seq]',
            'reset_period' => 'weekly',
        ]);

        $response->assertSessionHasErrors('reset_period');
    }

    public function test_admin_can_update_sequence_to_pattern_mode(): void
    {
        $sequence = LotSequence::factory()->create(['prefix' => 'OLD']);

        $response = $this->actingAs($this->admin)->put("/admin/lot-sequences/{$sequence->id}", [
            'name' => $sequence->name,
            'pattern' => '[year]-[seq]',
            'pad_size' => 5,
            'reset_period' => 'yearly',
        ]);

        $response->assertRedirect(route('admin.lot-sequences.index'));
        $this->assertSame('[year]-[seq]', $sequence->fresh()->pattern);
    }

    // ── Preview endpoint ─────────────────────────────────────────────────

    public function test_preview_endpoint_renders_pattern(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 6, 14, 0, 0));

        $response = $this->actingAs($this->admin)->postJson('/admin/lot-sequences/preview', [
            'pattern' => 'test-[date]-[seq]-[hour]',
            'pad_size' => 4,
        ]);

        $response->assertOk()->assertJson(['preview' => 'test-20260606-0001-14']);
    }

    public function test_preview_endpoint_validates_pattern(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/admin/lot-sequences/preview', [
            'pattern' => 'no-seq-here',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('pattern');
    }

    public function test_preview_endpoint_requires_admin(): void
    {
        $response = $this->actingAs($this->operator)->postJson('/admin/lot-sequences/preview', [
            'pattern' => '[seq]',
        ]);

        $response->assertForbidden();
    }

    public function test_preview_endpoint_requires_auth(): void
    {
        $response = $this->postJson('/admin/lot-sequences/preview', [
            'pattern' => '[seq]',
        ]);

        $response->assertUnauthorized();
    }
}
