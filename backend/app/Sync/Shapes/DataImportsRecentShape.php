<?php

namespace App\Sync\Shapes;

use App\Import\ImportRegistry;
use App\Models\User;
use App\Sync\Shape;

/**
 * Import runs (Admin → Import) from the last 90 days. The history list shows
 * the latest twenty and the run page one row, so a plant importing daily for
 * years must not ship every run to every admin on page load. Tenant scoping
 * is applied by CollectionController on top of this.
 */
class DataImportsRecentShape extends Shape
{
    public const DAYS = 90;

    public function table(): string
    {
        return 'csv_imports';
    }

    public function columns(): array
    {
        return [
            'id', 'tenant_id', 'user_id', 'entity', 'original_filename', 'filename', 'status', 'dry_run',
            'total_rows', 'processed_rows', 'created_rows', 'updated_rows', 'skipped_rows', 'failed_rows',
            'started_at', 'completed_at', 'created_at',
        ];
    }

    public function where(User $user): ?string
    {
        $recent = "created_at >= '".now()->subDays(self::DAYS)->toDateTimeString()."'";

        // The admin importer loads master data; the supervisor screen only ever
        // shows work orders. Scoping the page's query alone would still stream
        // every run over this collection's channel, so the gate has to be here
        // as well — a filename and its per-entity counters are exactly what a
        // supervisor should not learn about a materials or BOM import.
        if ($user->can('tab:import')) {
            return $recent;
        }

        $allowed = array_keys(app(ImportRegistry::class)->forSection('supervisor'));

        return $recent." AND entity IN ('".implode("', '", $allowed)."')";
    }
}
