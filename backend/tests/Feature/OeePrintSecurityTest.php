<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OeePrintSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/oee/print')->assertRedirect(route('login'));
    }

    public function test_operator_gets_forbidden(): void
    {
        $this->actingAs($this->operator)
            ->get('/admin/oee/print')
            ->assertForbidden();
    }

    public function test_admin_can_print_full_report(): void
    {
        Line::factory()->count(2)->create();

        $this->actingAs($this->admin)
            ->get('/admin/oee/print')
            ->assertOk()
            ->assertSee('OEE Report');
    }

    public function test_invalid_line_id_redirects_back_with_validation_error(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/oee/print?line_id=abc')
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->get('/admin/oee/print?line_id=99999999')
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->get('/admin/oee/print?line_id=-1')
            ->assertRedirect();

        // array param must not crash
        $this->actingAs($this->admin)
            ->get('/admin/oee/print?line_id[]=1&line_id[]=2')
            ->assertRedirect();
    }

    public function test_invalid_date_format_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/oee/print?date_from=garbage')
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->get('/admin/oee/print?date_from=' . urlencode('<script>alert(1)</script>'))
            ->assertRedirect();

        // date_to before date_from
        $this->actingAs($this->admin)
            ->get('/admin/oee/print?date_from=2026-05-15&date_to=2026-05-10')
            ->assertRedirect();
    }

    public function test_date_range_capped_at_366_days(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/oee/print?date_from=2000-01-01&date_to=2030-01-01')
            ->assertStatus(422);
    }

    public function test_referer_cannot_inject_open_redirect_into_back_link(): void
    {
        Line::factory()->create();

        $response = $this->actingAs($this->admin)
            ->withHeader('Referer', 'https://evil.com/phish')
            ->get('/admin/oee/print');

        $response->assertOk();
        $response->assertDontSee('https://evil.com', false);
        $response->assertSee(route('admin.oee.index'), false);
    }

    public function test_line_name_with_html_is_escaped(): void
    {
        $line = Line::factory()->create([
            'name' => '<script>alert(1)</script>',
            'code' => '"><img src=x onerror=alert(1)>',
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/oee/print?line_id=' . $line->id);

        $response->assertOk();
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $response->assertDontSee('"><img src=x', false);
    }

    public function test_sql_injection_attempt_does_not_succeed(): void
    {
        Line::factory()->create();

        // Validation must catch invalid line_id before it hits SQL.
        $response = $this->actingAs($this->admin)
            ->get("/admin/oee/print?" . http_build_query(['line_id' => "1' OR 1=1--"]));

        $response->assertRedirect();
    }
}
