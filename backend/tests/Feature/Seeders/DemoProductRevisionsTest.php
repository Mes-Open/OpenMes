<?php

namespace Tests\Feature\Seeders;

use App\Enums\RevisionLifecycle;
use App\Models\ProductRevision;
use Database\Seeders\MachineShopDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Which drawing a part is currently built to.
 *
 * No seeder created a revision, so Product Revisions came up empty in every
 * dataset. It matters most in machining: this shop's own routing tells the
 * operator to check the revision against the order before cutting, because
 * working to a superseded drawing scraps the part and the bar it came from.
 */
class DemoProductRevisionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MachineShopDemoSeeder::class);
    }

    public function test_the_demo_has_revisions(): void
    {
        $this->assertGreaterThanOrEqual(6, ProductRevision::count(), 'The revisions page would be empty.');
    }

    public function test_they_cover_the_whole_lifecycle(): void
    {
        $states = ProductRevision::pluck('lifecycle_status')->map(fn ($s) => $s->value)->unique();

        // A page where everything is Released shows none of what the lifecycle
        // is for — no supersession, no change in flight.
        foreach ([RevisionLifecycle::Draft, RevisionLifecycle::Released, RevisionLifecycle::Obsolete] as $state) {
            $this->assertContains($state->value, $states->all(), "No revision is {$state->value}.");
        }
    }

    public function test_a_part_has_at_most_one_released_revision(): void
    {
        $released = ProductRevision::where('lifecycle_status', RevisionLifecycle::Released)
            ->get()
            ->groupBy('product_type_id')
            ->filter(fn ($group) => $group->count() > 1);

        // Two current drawings for one part is the ambiguity the whole feature
        // exists to prevent.
        $this->assertTrue($released->isEmpty(), 'A part has more than one released revision.');
    }

    public function test_only_a_released_revision_names_the_routing_in_use(): void
    {
        foreach (ProductRevision::all() as $revision) {
            if ($revision->lifecycle_status === RevisionLifecycle::Released) {
                $this->assertNotNull(
                    $revision->process_template_id,
                    "{$revision->revision_code} is released but names no routing.",
                );

                continue;
            }

            // A draft is not being built yet and an obsolete one no longer is,
            // so neither should point at the routing the shop is running.
            $this->assertNull($revision->process_template_id);
        }
    }

    public function test_stamps_match_the_state_reached(): void
    {
        foreach (ProductRevision::all() as $revision) {
            match ($revision->lifecycle_status) {
                RevisionLifecycle::Draft => $this->assertNull($revision->released_at, 'A draft claims a release date.'),
                RevisionLifecycle::Released => $this->assertNotNull($revision->released_at),
                RevisionLifecycle::Obsolete => $this->assertNotNull($revision->obsolete_at, 'An obsolete revision has no end date.'),
            };
        }
    }

    public function test_reseeding_does_not_duplicate_revisions(): void
    {
        $first = ProductRevision::count();

        $this->seed(MachineShopDemoSeeder::class);

        $this->assertSame($first, ProductRevision::count(), 'A repeat run duplicated the revisions.');
    }
}
