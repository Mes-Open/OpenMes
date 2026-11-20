<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ModuleRegistry;
use App\Support\TabRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A page the menu files under a module (adminNav `tab:`) must also be blocked
 * by that module. Material Types and Workstation Devices disappeared from the
 * menu with their module, but no tab owned their URL, so they stayed open to
 * an admin (and closed to everybody else, whatever the access matrix said).
 */
class ModuleHiddenPagesBlockedTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    /** @return array<string, array{string, string, string}> */
    public static function pages(): array
    {
        return [
            'material types' => ['/admin/material-types', 'materials', 'materials'],
            'workstation devices' => ['/admin/workstation-devices', 'structure', 'structure'],
        ];
    }

    /** @dataProvider pages */
    public function test_the_page_belongs_to_its_modules_tab(string $url, string $tab, string $module): void
    {
        $this->assertSame($tab, TabRegistry::tabForPath($url));
        $this->assertSame($module, TabRegistry::moduleFor($tab));
    }

    /** @dataProvider pages */
    public function test_the_page_404s_with_its_module_off(string $url, string $tab, string $module): void
    {
        ModuleRegistry::save(array_values(array_diff(ModuleRegistry::optionalKeys(), [$module])));

        $this->actingAs($this->admin)->get($url)->assertNotFound();
    }

    /** @dataProvider pages */
    public function test_the_page_opens_with_its_module_on(string $url): void
    {
        ModuleRegistry::save(ModuleRegistry::optionalKeys());

        $this->actingAs($this->admin)->get($url)->assertOk();
    }
}
