<?php

namespace Tests\Feature\Api;

use App\Models\ProcessSegment;
use App\Models\ProcessTemplate;
use App\Models\TemplateStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessSegmentApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $operator;
    private string $adminToken;
    private string $operatorToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->operatorToken = $this->operator->createToken('test')->plainTextToken;
    }

    private function authAdmin()
    {
        return $this->withHeader('Authorization', "Bearer {$this->adminToken}");
    }

    private function authOperator()
    {
        return $this->withHeader('Authorization', "Bearer {$this->operatorToken}");
    }

    public function test_admin_can_create_segment_via_api(): void
    {
        $response = $this->authAdmin()->postJson('/api/v1/process-segments', [
            'code'                       => 'PSG-API-1',
            'name'                       => 'API created',
            'segment_type'               => ProcessSegment::TYPE_PRODUCTION,
            'estimated_duration_minutes' => 30,
            'required_operators'         => 2,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'PSG-API-1')
            ->assertJsonPath('data.segment_type', 'production');
    }

    public function test_any_authenticated_user_can_list_segments(): void
    {
        ProcessSegment::factory()->create(['code' => 'PSG-API-LIST']);

        $response = $this->authOperator()->getJson('/api/v1/process-segments');
        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_operator_cannot_create_segment(): void
    {
        $response = $this->authOperator()->postJson('/api/v1/process-segments', [
            'code'         => 'PSG-OP',
            'name'         => 'Op tried',
            'segment_type' => ProcessSegment::TYPE_PRODUCTION,
            'required_operators' => 1,
        ]);
        $response->assertStatus(403);
    }

    public function test_admin_can_update_segment_via_api(): void
    {
        $segment = ProcessSegment::factory()->create(['code' => 'PSG-API-UPD', 'name' => 'Old']);

        $response = $this->authAdmin()->patchJson('/api/v1/process-segments/' . $segment->id, [
            'name' => 'New name',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New name');
    }

    public function test_admin_cannot_delete_segment_in_use(): void
    {
        $segment  = ProcessSegment::factory()->create();
        $template = ProcessTemplate::factory()->create();
        TemplateStep::create([
            'process_template_id' => $template->id,
            'process_segment_id'  => $segment->id,
            'step_number'         => 1,
            'name'                => 'In use',
        ]);

        $response = $this->authAdmin()->deleteJson('/api/v1/process-segments/' . $segment->id);
        $response->assertStatus(422);
        $this->assertDatabaseHas('process_segments', ['id' => $segment->id]);
    }
}
