<?php

namespace Tests\Feature\Web;

use App\Models\Line;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A record URL with a non-numeric id is "not found". On PostgreSQL the binding
 * query itself fails (bigint vs 'abc', SQLSTATE 22P02) and became a 500; the
 * suite runs on SQLite, which only finds no row, so these tests pin that the
 * binding query is never sent rather than the status alone.
 */
class MalformedModelKeyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        return $admin;
    }

    /** @return array<int, string> SQL bound with the malformed key */
    private function queriesWith(string $value, callable $request): array
    {
        $hits = [];
        DB::listen(function (QueryExecuted $q) use ($value, &$hits) {
            if (in_array($value, $q->bindings, true)) {
                $hits[] = $q->sql;
            }
        });
        $request();

        return $hits;
    }

    public function test_admin_record_pages_answer_404_without_querying(): void
    {
        $admin = $this->admin();

        foreach (['/admin/material-lots/abc', '/admin/work-orders/abc', '/admin/lines/abc', '/admin/materials/null'] as $url) {
            $value = basename($url);
            $hits = $this->queriesWith($value, fn () => $this->actingAs($admin)->get($url)->assertNotFound());
            $this->assertSame([], $hits, "{$url} sent its key to the database");
        }
    }

    public function test_api_record_routes_answer_404(): void
    {
        Sanctum::actingAs($this->admin(), ['*']);

        $hits = $this->queriesWith('abc', fn () => $this->getJson('/api/v1/material-lots/abc/where-used')->assertNotFound());
        $this->assertSame([], $hits);
    }

    public function test_numeric_ids_still_bind(): void
    {
        $admin = $this->admin();
        $line = Line::factory()->create();

        $this->actingAs($admin)->get("/admin/lines/{$line->id}")->assertOk();
        $this->actingAs($admin)->get('/admin/lines/99999999')->assertNotFound();
    }

    public function test_a_guest_is_still_sent_to_the_login_page(): void
    {
        $this->get('/admin/lines/abc')->assertRedirect(route('login'));
    }

    public function test_a_custom_binding_field_is_left_alone(): void
    {
        Route::middleware('web')->get('/__binding-probe/{line:code}', fn (Line $line) => $line->code);
        Line::factory()->create(['code' => 'ABC-1']);

        $this->get('/__binding-probe/ABC-1')->assertOk()->assertSee('ABC-1');
    }
}
