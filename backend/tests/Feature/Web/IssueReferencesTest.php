<?php

namespace Tests\Feature\Web;

use App\Extension\FilterRegistry;
use App\Models\Issue;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The `issues.references` filter: a module labels issues (e.g. with a
 * non-conformance number) and the Issues page gets them as `issueReferences`.
 */
class IssueReferencesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');
    }

    public function test_without_a_filter_the_page_gets_no_references(): void
    {
        Issue::factory()->create();

        $this->actingAs($this->admin)->get('/admin/issues')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('shared/issues/Index')->missing('issueReferences'));
    }

    public function test_a_filter_labels_issues_on_both_pages(): void
    {
        $issue = Issue::factory()->create();
        app(FilterRegistry::class)->addFilter('issues.references', fn (array $refs) => $refs + [$issue->id => 'NC/2026/0001']);

        foreach (['admin' => $this->admin, 'supervisor' => $this->supervisor] as $prefix => $user) {
            $this->actingAs($user)->get("/{$prefix}/issues")
                ->assertOk()
                ->assertInertia(fn ($page) => $page->where('issueReferences', [$issue->id => 'NC/2026/0001']));
        }
    }

    public function test_the_filter_gets_the_viewer_and_bad_entries_are_dropped(): void
    {
        $issue = Issue::factory()->create();
        $seen = null;
        app(FilterRegistry::class)->addFilter('issues.references', function (array $refs, array $context) use ($issue, &$seen) {
            $seen = $context['user'] ?? null;

            return [$issue->id => '  NC-7  ', 'abc' => 'x', '1.5' => 'x', 0 => 'zero', $issue->id + 1 => '', $issue->id + 2 => ['array'], $issue->id + 3 => 42];
        });

        $this->actingAs($this->admin)->get('/admin/issues')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('issueReferences', [$issue->id => 'NC-7', $issue->id + 3 => '42']));
        $this->assertTrue($this->admin->is($seen));
    }

    public function test_a_filter_with_nothing_to_say_keeps_the_column_off(): void
    {
        app(FilterRegistry::class)->addFilter('issues.references', fn (array $refs) => $refs);

        $this->actingAs($this->admin)->get('/admin/issues')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->missing('issueReferences'));
    }

    public function test_a_filter_returning_a_non_map_keeps_the_column_off(): void
    {
        app(FilterRegistry::class)->addFilter('issues.references', fn () => 'NC/2026/0001');

        $this->actingAs($this->admin)->get('/admin/issues')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->missing('issueReferences'));
    }

    public function test_access_is_unchanged(): void
    {
        app(FilterRegistry::class)->addFilter('issues.references', fn () => [1 => 'NC/2026/0001']);
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->get('/admin/issues')->assertRedirect('/login');
        $this->get('/supervisor/issues')->assertRedirect('/login');
        $this->actingAs($operator)->get('/admin/issues')->assertForbidden();
        $this->actingAs($operator)->get('/supervisor/issues')->assertForbidden();
    }
}
