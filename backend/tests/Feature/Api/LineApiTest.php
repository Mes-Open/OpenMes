<?php

namespace Tests\Feature\Api;

use App\Models\Line;
use App\Models\User;
use App\Models\Workstation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LineApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('Admin');
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // ── GET /api/v1/lines ────────────────────────────────────────────────────

    public function test_authenticated_user_can_list_lines(): void
    {
        Line::factory()->count(3)->create();

        $response = $this->withHeaders($this->auth())->getJson('/api/v1/lines');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'code'],
                ],
            ]);
    }

    public function test_lines_list_returns_only_active_lines(): void
    {
        Line::factory()->count(2)->create(['is_active' => true]);
        Line::factory()->create(['is_active' => false]);

        $response = $this->withHeaders($this->auth())->getJson('/api/v1/lines');

        $data = $response->json('data');
        foreach ($data as $line) {
            $this->assertTrue($line['is_active']);
        }
    }

    public function test_unauthenticated_user_cannot_list_lines(): void
    {
        $response = $this->getJson('/api/v1/lines');

        $response->assertStatus(401);
    }

    public function test_lines_list_includes_workstation_count(): void
    {
        $line = Line::factory()->create();
        Workstation::factory()->count(3)->create(['line_id' => $line->id]);

        $response = $this->withHeaders($this->auth())->getJson('/api/v1/lines');

        $response->assertStatus(200);
        $data = collect($response->json('data'));
        $lineData = $data->firstWhere('id', $line->id);

        $this->assertNotNull($lineData);
    }

    // ── GET /api/v1/lines/{line} ─────────────────────────────────────────────

    public function test_authenticated_user_can_get_single_line(): void
    {
        $line = Line::factory()->create(['name' => 'Test Line', 'code' => 'TL-01']);

        $response = $this->withHeaders($this->auth())->getJson("/api/v1/lines/{$line->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id'   => $line->id,
                    'name' => 'Test Line',
                    'code' => 'TL-01',
                ],
            ]);
    }

    public function test_get_nonexistent_line_returns_404(): void
    {
        $response = $this->withHeaders($this->auth())->getJson('/api/v1/lines/999999');

        $response->assertStatus(404);
    }

    public function test_line_response_includes_workstations(): void
    {
        $line = Line::factory()->create();
        Workstation::factory()->count(2)->create(['line_id' => $line->id]);

        $response = $this->withHeaders($this->auth())->getJson("/api/v1/lines/{$line->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'code', 'workstations'],
            ]);

        $this->assertCount(2, $response->json('data.workstations'));
    }

    public function test_lines_list_is_sorted_by_name(): void
    {
        Line::factory()->create(['name' => 'Zebra Line']);
        Line::factory()->create(['name' => 'Alpha Line']);
        Line::factory()->create(['name' => 'Middle Line']);

        $response = $this->withHeaders($this->auth())->getJson('/api/v1/lines');

        $names = array_column($response->json('data'), 'name');
        $sorted = $names;
        sort($sorted);

        $this->assertEquals($sorted, $names);
    }
}
