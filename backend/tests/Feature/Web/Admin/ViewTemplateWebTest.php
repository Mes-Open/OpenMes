<?php

namespace Tests\Feature\Web\Admin;

use App\Models\User;
use App\Models\ViewTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ViewTemplateWebTest extends TestCase
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

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Cutting board',
            'description' => 'Columns for the cutting line',
            'columns' => [
                ['label' => 'Thickness', 'key' => 'thickness', 'source' => 'extra_data'],
                ['label' => 'Status', 'key' => 'status', 'source' => 'field'],
            ],
        ], $overrides);
    }

    public function test_admin_can_create(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.view-templates.store'), $this->payload())
            ->assertRedirect(route('admin.view-templates.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('view_templates', ['name' => 'Cutting board']);
    }

    public function test_columns_are_required(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.view-templates.store'), $this->payload(['columns' => []]))
            ->assertSessionHasErrors('columns');
    }

    public function test_duplicate_name_rejected(): void
    {
        ViewTemplate::create($this->payload());

        $this->actingAs($this->admin)
            ->post(route('admin.view-templates.store'), $this->payload())
            ->assertSessionHasErrors('name');
    }

    public function test_admin_can_update(): void
    {
        $vt = ViewTemplate::create($this->payload());

        $this->actingAs($this->admin)
            ->put(route('admin.view-templates.update', $vt), $this->payload(['name' => 'Renamed board']))
            ->assertRedirect(route('admin.view-templates.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('view_templates', ['id' => $vt->id, 'name' => 'Renamed board']);
    }

    public function test_admin_can_delete(): void
    {
        $vt = ViewTemplate::create($this->payload());

        $this->actingAs($this->admin)
            ->delete(route('admin.view-templates.destroy', $vt))
            ->assertRedirect(route('admin.view-templates.index'));

        $this->assertSoftDeleted('view_templates', ['id' => $vt->id]);
    }

    public function test_operator_is_forbidden(): void
    {
        $this->actingAs($this->operator)
            ->get(route('admin.view-templates.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected(): void
    {
        $this->post(route('admin.view-templates.store'), $this->payload())
            ->assertRedirect(route('login'));
    }
}
