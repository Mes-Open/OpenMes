<?php

namespace Tests\Feature;

use App\Http\Controllers\Web\OnboardingController;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OnboardingWizardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Supervisor', 'guard_name' => 'web']);
        Role::create(['name' => 'Operator', 'guard_name' => 'web']);

        foreach (['view work orders', 'create work orders', 'edit work orders', 'delete work orders'] as $perm) {
            Permission::create(['name' => $perm, 'guard_name' => 'web']);
        }
        $adminRole->givePermissionTo(Permission::all());

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        DB::table('system_settings')->updateOrInsert(
            ['key' => 'onboarding_completed'],
            ['value' => json_encode(false)]
        );
    }

    public function test_wizard_shows_when_no_lines_exist(): void
    {
        $this->assertTrue(OnboardingController::shouldShowWizard());
    }

    public function test_wizard_skipped_when_lines_exist(): void
    {
        Line::factory()->create();
        $this->assertFalse(OnboardingController::shouldShowWizard());
    }

    public function test_wizard_skipped_when_marked_completed(): void
    {
        DB::table('system_settings')
            ->where('key', 'onboarding_completed')
            ->update(['value' => json_encode(true)]);

        $this->assertFalse(OnboardingController::shouldShowWizard());
    }

    public function test_step1_creates_line(): void
    {
        $response = $this->actingAs($this->admin)->post(route('onboarding.step1'), [
            'code' => 'LINE-01',
            'name' => 'Test Line',
            'description' => 'My first line',
        ]);

        $response->assertRedirect(route('onboarding.step2'));
        $this->assertDatabaseHas('lines', ['code' => 'LINE-01', 'name' => 'Test Line']);
    }

    public function test_step2_creates_product_type(): void
    {
        $line = Line::factory()->create();

        $response = $this->actingAs($this->admin)
            ->withSession(['onboarding.line_id' => $line->id])
            ->post(route('onboarding.step2'), [
                'code' => 'PROD-01',
                'name' => 'Test Product',
            ]);

        $response->assertRedirect(route('onboarding.step3'));
        $this->assertDatabaseHas('product_types', ['code' => 'PROD-01']);
    }

    public function test_step3_creates_template_with_steps(): void
    {
        $pt = ProductType::factory()->create();

        $response = $this->actingAs($this->admin)
            ->withSession(['onboarding.product_type_id' => $pt->id])
            ->post(route('onboarding.step3'), [
                'name' => 'Assembly Process',
                'steps' => [
                    ['name' => 'Preparation', 'estimated_duration_minutes' => 10],
                    ['name' => 'Assembly', 'estimated_duration_minutes' => 30],
                    ['name' => 'Packaging', 'estimated_duration_minutes' => 5],
                ],
            ]);

        $response->assertRedirect(route('onboarding.step4'));
        $this->assertDatabaseHas('process_templates', ['name' => 'Assembly Process']);
        $this->assertEquals(3, ProcessTemplate::first()->steps()->count());
    }

    public function test_step4_creates_work_order(): void
    {
        $line = Line::factory()->create();
        $pt = ProductType::factory()->create();
        ProcessTemplate::factory()->withSteps(2)->create(['product_type_id' => $pt->id]);

        $response = $this->actingAs($this->admin)
            ->withSession([
                'onboarding.line_id' => $line->id,
                'onboarding.product_type_id' => $pt->id,
                'onboarding.template_id' => 1,
            ])
            ->post(route('onboarding.step4'), [
                'order_no' => 'WO-TEST-001',
                'planned_qty' => 500,
                'description' => 'First order',
            ]);

        $response->assertRedirect(route('onboarding.complete'));
        $this->assertDatabaseHas('work_orders', ['order_no' => 'WO-TEST-001', 'planned_qty' => 500]);
    }

    public function test_complete_marks_onboarding_done(): void
    {
        $response = $this->actingAs($this->admin)->get(route('onboarding.complete'));

        $response->assertStatus(200);
        $this->assertEquals(
            true,
            json_decode(DB::table('system_settings')->where('key', 'onboarding_completed')->value('value'))
        );
    }

    public function test_skip_marks_completed(): void
    {
        $response = $this->actingAs($this->admin)->post(route('onboarding.skip'));

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertEquals(
            true,
            json_decode(DB::table('system_settings')->where('key', 'onboarding_completed')->value('value'))
        );
    }

    public function test_full_wizard_flow(): void
    {
        // Step 1
        $this->actingAs($this->admin)->post(route('onboarding.step1'), [
            'code' => 'L-01', 'name' => 'My Line',
        ])->assertRedirect(route('onboarding.step2'));

        // Step 2
        $this->actingAs($this->admin)->post(route('onboarding.step2'), [
            'code' => 'PT-01', 'name' => 'My Product',
        ])->assertRedirect(route('onboarding.step3'));

        // Step 3
        $this->actingAs($this->admin)->post(route('onboarding.step3'), [
            'name' => 'My Process',
            'steps' => [['name' => 'Step 1'], ['name' => 'Step 2']],
        ])->assertRedirect(route('onboarding.step4'));

        // Step 4
        $this->actingAs($this->admin)->post(route('onboarding.step4'), [
            'order_no' => 'WO-001', 'planned_qty' => 100,
        ])->assertRedirect(route('onboarding.complete'));

        // Verify data created
        $this->assertEquals(1, Line::count());
        $this->assertEquals(1, ProductType::count());
        $this->assertEquals(1, ProcessTemplate::count());
        $this->assertEquals(2, ProcessTemplate::first()->steps()->count());
        $this->assertEquals(1, WorkOrder::count());
    }

    public function test_validation_errors_on_empty_step1(): void
    {
        $response = $this->actingAs($this->admin)->post(route('onboarding.step1'), []);
        $response->assertSessionHasErrors(['code', 'name']);
    }

    public function test_step2_redirects_without_session(): void
    {
        $response = $this->actingAs($this->admin)->get(route('onboarding.step2'));
        $response->assertRedirect(route('onboarding.step1'));
    }
}
