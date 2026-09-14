<?php

namespace Tests\Feature\Web\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The contract every list page's create/edit drawer relies on, checked across a
 * spread of resources rather than repeated in each controller's own test.
 *
 * Two halves, and both matter:
 *   - `stay` is answered with back(), so the list keeps its filters, paging and
 *     scroll while the saved row live-syncs in. Without it — the standalone
 *     /create page — the redirect is unchanged.
 *   - the drawer's option lists are Inertia::optional(), absent from a plain
 *     list render and present on the partial reload the drawer fires. A list
 *     that shipped them eagerly would pay for a form most visitors never open.
 */
class ListDrawerTest extends TestCase
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

    /** A resource with no option lists at all. */
    public function test_scrap_reason_drawer_stays_on_the_list(): void
    {
        $payload = ['code' => 'SR-DRW', 'name' => 'Drawer reason', 'category' => 'machine'];

        $this->actingAs($this->admin)
            ->from(route('admin.scrap-reasons.index'))
            ->post(route('admin.scrap-reasons.store'), $payload + ['stay' => 1])
            ->assertRedirect(route('admin.scrap-reasons.index'));

        $this->assertDatabaseHas('scrap_reasons', ['code' => 'SR-DRW']);
    }

    public function test_scrap_reason_create_page_still_redirects(): void
    {
        // Same endpoint, no `stay` — the standalone page's behaviour is untouched.
        $this->actingAs($this->admin)
            ->from(route('admin.scrap-reasons.create'))
            ->post(route('admin.scrap-reasons.store'), [
                'code' => 'SR-PAGE', 'name' => 'Page reason', 'category' => 'machine',
            ])
            ->assertRedirect(route('admin.scrap-reasons.index'));
    }

    /** @return array<int, array{0: string, 1: array<int, string>}> */
    public static function optionalPropLists(): array
    {
        return [
            'shifts' => ['admin.shifts.index', ['lines', 'customFields']],
            'tools' => ['admin.tools.index', ['workstationTypes', 'customFields']],
        ];
    }

    /**
     * @param  array<int, string>  $optional
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('optionalPropLists')]
    public function test_list_withholds_drawer_options_until_asked(string $route, array $optional): void
    {
        $plain = $this->actingAs($this->admin)->get(route($route));
        $plain->assertOk();
        foreach ($optional as $prop) {
            $plain->assertInertia(fn ($page) => $page->missing($prop));
        }

        $this->actingAs($this->admin)
            ->get(route($route), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => Inertia::getVersion(),
                'X-Inertia-Partial-Component' => $this->componentFor($route),
                'X-Inertia-Partial-Data' => implode(',', $optional),
            ])
            // A partial reload answers with the Inertia JSON envelope rather than
            // an HTML page, which assertInertia (which reads data-page) can't see.
            ->assertJsonStructure(['props' => $optional]);
    }

    private function componentFor(string $route): string
    {
        return 'admin/'.str($route)->after('admin.')->before('.index')->toString().'/Index';
    }
}
