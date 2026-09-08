<?php

namespace Tests\Feature\Seeders;

use App\Models\DowntimeReason;
use App\Models\IssueType;
use App\Models\LabelTemplate;
use App\Models\LineStatus;
use App\Models\MaterialType;
use App\Models\ScrapReason;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The reference data a fresh install needs before anyone can use it.
 *
 * Regression guard: the installer used to run three hand-picked seeders instead
 * of this one, so a new install came up with no scrap or downtime reasons, no
 * material types and no label templates — the operator's "report scrap" picker
 * was an empty dropdown. Both install paths now call DatabaseSeeder, and it runs
 * on every container start, so it also has to be safe to repeat.
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, int> */
    private function counts(): array
    {
        return [
            'roles' => Role::count(),
            'issue_types' => IssueType::count(),
            'line_statuses' => LineStatus::count(),
            'material_types' => MaterialType::count(),
            'downtime_reasons' => DowntimeReason::count(),
            'scrap_reasons' => ScrapReason::count(),
            'label_templates' => LabelTemplate::count(),
        ];
    }

    public function test_it_seeds_every_reference_set_a_fresh_install_needs(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach ($this->counts() as $what => $count) {
            $this->assertGreaterThan(0, $count, "A fresh install must have {$what}.");
        }
    }

    public function test_the_operator_facing_pickers_are_not_empty(): void
    {
        $this->seed(DatabaseSeeder::class);

        // These two feed dropdowns an operator has to choose from to report
        // scrap or classify a stop; empty means the flow cannot be completed.
        $this->assertGreaterThan(0, ScrapReason::active()->count());
        $this->assertGreaterThan(0, DowntimeReason::where('is_active', true)->count());
    }

    public function test_both_install_paths_actually_call_it(): void
    {
        // The bug was never in this seeder — it was that nothing invoked it.
        // The Docker entrypoint is a shell script and the web installer shells
        // out through Artisan, so neither is reachable from a normal test; the
        // invariant worth pinning is simply that both still name it.
        $entrypoint = file_get_contents(base_path('docker-entrypoint.sh'));
        $installer = file_get_contents(app_path('Http/Controllers/InstallController.php'));

        $this->assertStringContainsString(
            'db:seed --class=DatabaseSeeder',
            $entrypoint,
            'The container entrypoint must seed the full reference set, not a hand-picked subset.',
        );
        $this->assertStringContainsString(
            "'--class' => 'DatabaseSeeder'",
            $installer,
            'The web installer must seed the full reference set, not a hand-picked subset.',
        );
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $this->seed(DatabaseSeeder::class);
        $first = $this->counts();

        // The container entrypoint runs this on every boot, so a repeat must not
        // duplicate rows or fail on a unique index.
        $this->seed(DatabaseSeeder::class);

        $this->assertSame($first, $this->counts());
    }
}
