<?php

namespace Tests\Feature\Web\Admin;

use App\Models\ScrapEntry;
use App\Models\ScrapReason;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScrapReasonControllerTest extends TestCase
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

    public function test_admin_can_list_scrap_reasons(): void
    {
        ScrapReason::factory()->create(['name' => 'Burr on edge']);

        // The Inertia index renders the React page; rows live-sync via the
        // `scrap_reasons` collection rather than being server-rendered, so
        // we only assert the page loads for an admin.
        $response = $this->actingAs($this->admin)->get(route('admin.scrap-reasons.index'));

        $response->assertOk();
    }

    public function test_admin_can_create_scrap_reason(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.scrap-reasons.store'), [
            'code' => 'WELD-CRACK',
            'name' => 'Weld crack',
            'category' => ScrapReason::CATEGORY_METHOD,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.scrap-reasons.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('scrap_reasons', [
            'code' => 'WELD-CRACK',
            'category' => 'method',
            'is_active' => true,
        ]);
    }

    public function test_category_must_be_a_valid_5m_value(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.scrap-reasons.store'), [
            'code' => 'BAD',
            'name' => 'Bad category',
            'category' => 'gremlins',
        ]);

        $response->assertSessionHasErrors('category');
    }

    public function test_code_must_be_unique(): void
    {
        ScrapReason::factory()->create(['code' => 'DUP-1']);

        $response = $this->actingAs($this->admin)->post(route('admin.scrap-reasons.store'), [
            'code' => 'DUP-1',
            'name' => 'Duplicate',
            'category' => ScrapReason::CATEGORY_MAN,
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_admin_can_update_scrap_reason(): void
    {
        $reason = ScrapReason::factory()->create(['name' => 'Old']);

        $response = $this->actingAs($this->admin)->put(route('admin.scrap-reasons.update', $reason), [
            'code' => $reason->code,
            'name' => 'New name',
            'category' => ScrapReason::CATEGORY_MACHINE,
        ]);

        $response->assertRedirect(route('admin.scrap-reasons.index'));
        $this->assertDatabaseHas('scrap_reasons', ['id' => $reason->id, 'name' => 'New name', 'category' => 'machine']);
    }

    public function test_admin_can_toggle_active(): void
    {
        $reason = ScrapReason::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin)->post(route('admin.scrap-reasons.toggle-active', $reason));

        $this->assertFalse($reason->fresh()->is_active);
    }

    public function test_reason_with_entries_cannot_be_deleted(): void
    {
        $reason = ScrapReason::factory()->create();
        ScrapEntry::factory()->create(['scrap_reason_id' => $reason->id]);

        $response = $this->actingAs($this->admin)->delete(route('admin.scrap-reasons.destroy', $reason));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('scrap_reasons', ['id' => $reason->id]);
    }

    public function test_unused_reason_can_be_deleted(): void
    {
        $reason = ScrapReason::factory()->create();

        $this->actingAs($this->admin)->delete(route('admin.scrap-reasons.destroy', $reason));

        $this->assertSoftDeleted('scrap_reasons', ['id' => $reason->id]);
    }

    public function test_non_admin_cannot_manage_scrap_reasons(): void
    {
        Role::findOrCreate('Operator', 'web');
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)->get(route('admin.scrap-reasons.index'))->assertForbidden();
    }
}
