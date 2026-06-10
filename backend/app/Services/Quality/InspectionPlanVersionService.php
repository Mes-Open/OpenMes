<?php

namespace App\Services\Quality;

use App\Models\InspectionPlan;
use Illuminate\Support\Facades\DB;

/**
 * Versioning rules for inspection plans.
 *
 * A plan is a DRAFT until published (published_at = null) and can be edited in
 * place. Once PUBLISHED it is immutable; editing it produces a new draft
 * version in the same version group. Publishing a version makes it the single
 * live version (is_active) and retires the previous live one — so inspections
 * always run against the latest published version, while old versions stay
 * intact for historical reproducibility.
 */
class InspectionPlanVersionService
{
    /**
     * Create the next draft version from a published plan, copying its fields
     * and criteria. Returns the new draft.
     */
    public function createNewVersion(InspectionPlan $plan, array $attributes): InspectionPlan
    {
        return DB::transaction(function () use ($plan, $attributes) {
            $rootId = $plan->rootId();
            $nextVersion = (int) InspectionPlan::query()
                ->where(fn ($q) => $q->where('id', $rootId)->orWhere('root_id', $rootId))
                ->max('version') + 1;

            return InspectionPlan::create([
                ...$attributes,
                'version' => $nextVersion,
                'root_id' => $rootId,
                'published_at' => null, // draft
                'is_active' => false,
                'tenant_id' => $plan->tenant_id,
            ]);
        });
    }

    /**
     * Publish a draft: stamp published_at, make it the live version, and retire
     * every other version in the group. No-op if already published.
     */
    public function publish(InspectionPlan $plan): InspectionPlan
    {
        if ($plan->isPublished()) {
            return $plan;
        }

        return DB::transaction(function () use ($plan) {
            $rootId = $plan->rootId();

            // Retire the other versions one model at a time (not a mass
            // query-builder update) so each row fires its model events and the
            // realtime collection reflects the archival immediately.
            InspectionPlan::query()
                ->where(fn ($q) => $q->where('id', $rootId)->orWhere('root_id', $rootId))
                ->where('id', '!=', $plan->id)
                ->where('is_active', true)
                ->get()
                ->each(fn (InspectionPlan $other) => $other->update(['is_active' => false]));

            $plan->update([
                'published_at' => now(),
                'is_active' => true,
            ]);

            return $plan->fresh();
        });
    }
}
