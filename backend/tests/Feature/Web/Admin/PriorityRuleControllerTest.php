<?php

namespace Tests\Feature\Web\Admin;

use App\Models\PriorityRule;
use App\Models\User;
use App\Support\PriorityBandRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PriorityRuleControllerTest extends TestCase
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

    public function test_admin_can_list_priority_rules(): void
    {
        PriorityRule::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.priority-rules.index'))
            ->assertOk();
    }

    public function test_admin_can_create_a_rule(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.priority-rules.store'), [
            'name' => 'Gold customer',
            'field_source' => 'customer.tier',
            'condition_type' => 'equals',
            'condition_value' => 'gold',
            'points' => 30,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.priority-rules.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('priority_rules', [
            'name' => 'Gold customer',
            'field_source' => 'customer.tier',
            'condition_type' => 'equals',
            'condition_value' => 'gold',
            'points' => 30,
        ]);
    }

    public function test_source_and_condition_must_be_valid(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.priority-rules.store'), [
            'name' => 'Bad',
            'field_source' => 'wo.moon_phase',
            'condition_type' => 'sometimes',
            'points' => 5,
        ]);

        $response->assertSessionHasErrors(['field_source', 'condition_type']);
    }

    public function test_between_requires_an_upper_bound(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.priority-rules.store'), [
            'name' => 'Mid range',
            'field_source' => 'customer.payment_score',
            'condition_type' => 'between',
            'condition_value' => '20',
            'points' => 10,
        ]);

        $response->assertSessionHasErrors('condition_value_max');
    }

    public function test_tier_equals_must_use_a_valid_tier(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.priority-rules.store'), [
            'name' => 'Bad tier',
            'field_source' => 'customer.tier',
            'condition_type' => 'equals',
            'condition_value' => 'platinum',
            'points' => 30,
        ]);

        $response->assertSessionHasErrors('condition_value');
    }

    public function test_tier_source_rejects_non_equals_conditions(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.priority-rules.store'), [
            'name' => 'Tier greater than',
            'field_source' => 'customer.tier',
            'condition_type' => 'greater_than',
            'condition_value' => 'gold',
            'points' => 10,
        ]);

        $response->assertSessionHasErrors('condition_type');
    }

    public function test_is_true_condition_needs_no_value(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.priority-rules.store'), [
            'name' => 'Flag',
            'field_source' => 'wo.planned_qty',
            'condition_type' => 'is_true',
            'points' => 5,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('priority_rules', ['name' => 'Flag', 'condition_value' => null]);
    }

    public function test_admin_can_update_a_rule(): void
    {
        $rule = PriorityRule::factory()->create(['points' => 10]);

        $response = $this->actingAs($this->admin)->put(route('admin.priority-rules.update', $rule), [
            'name' => $rule->name,
            'field_source' => 'customer.tier',
            'condition_type' => 'equals',
            'condition_value' => 'vip',
            'points' => 50,
        ]);

        $response->assertRedirect(route('admin.priority-rules.index'));
        $this->assertDatabaseHas('priority_rules', ['id' => $rule->id, 'points' => 50, 'condition_value' => 'vip']);
    }

    public function test_admin_can_toggle_active(): void
    {
        $rule = PriorityRule::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin)->post(route('admin.priority-rules.toggle-active', $rule));

        $this->assertFalse($rule->fresh()->is_active);
    }

    public function test_rule_is_soft_deleted(): void
    {
        $rule = PriorityRule::factory()->create();

        $this->actingAs($this->admin)->delete(route('admin.priority-rules.destroy', $rule));

        $this->assertSoftDeleted('priority_rules', ['id' => $rule->id]);
    }

    public function test_admin_can_update_bands(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.priority-rules.bands'), [
            'bands' => [15, 30, 45, 60],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame([15, 30, 45, 60], PriorityBandRegistry::bands());
    }

    public function test_bands_must_be_ascending(): void
    {
        $this->actingAs($this->admin)->post(route('admin.priority-rules.bands'), [
            'bands' => [40, 20, 60, 80],
        ])->assertSessionHasErrors('bands');

        // Unchanged from the default seeded mapping.
        $this->assertSame([20, 40, 60, 80], PriorityBandRegistry::bands());
    }

    public function test_non_admin_cannot_manage_rules(): void
    {
        Role::findOrCreate('Operator', 'web');
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)->get(route('admin.priority-rules.index'))->assertForbidden();
    }

    public function test_guest_cannot_manage_rules(): void
    {
        $this->get(route('admin.priority-rules.index'))->assertRedirect('/login');
        $this->post(route('admin.priority-rules.store'), [])->assertRedirect('/login');
        $this->post(route('admin.priority-rules.bands'), [])->assertRedirect('/login');
    }
}
