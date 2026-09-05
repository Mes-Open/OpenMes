<?php

namespace App\Console\Commands;

use App\Models\CsvImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes uploads the importer no longer has any use for.
 *
 * A file is written as soon as one is picked (Admin → Import), and only the
 * job that imports it deletes it. Everything else leaves it behind: a user who
 * uploads, sees the preview and navigates away, a validation run that is never
 * followed by a real one, a session token that expires. Nothing else ever looks
 * at storage/app/imports, so those files accumulate until the disk notices.
 *
 * A file is kept while any run still needs it: PENDING or PROCESSING (the job
 * has not read it yet), or a finished dry run recent enough that the user may
 * still press "Run the import".
 */
class PruneImportUploads extends Command
{
    protected $signature = 'imports:prune-uploads {--hours=24 : Delete unreferenced uploads older than this}';

    protected $description = 'Delete abandoned import uploads from storage/app/imports';

    public function handle(): int
    {
        $cutoff = now()->subHours((int) $this->option('hours'));
        $disk = Storage::disk('local');

        // Paths a run still needs. A dry run keeps its file so the real import
        // can follow, but not forever — the same cutoff applies.
        $keep = CsvImport::withoutGlobalScopes()
            ->whereNotNull('file_path')
            ->where(fn ($q) => $q
                ->whereIn('status', CsvImport::ACTIVE_STATUSES)
                ->orWhere(fn ($d) => $d->where('dry_run', true)->where('created_at', '>=', $cutoff)))
            ->pluck('file_path')
            ->flip();

        $deleted = 0;
        $freed = 0;

        foreach ($disk->files('imports') as $path) {
            if ($keep->has($path) || $disk->lastModified($path) >= $cutoff->getTimestamp()) {
                continue;
            }

            $freed += $disk->size($path);
            $disk->delete($path);
            $deleted++;
        }

        $this->info($deleted === 0
            ? 'No abandoned import uploads to prune.'
            : "Pruned {$deleted} import upload(s), freeing ".round($freed / 1024).' KB.');

        return self::SUCCESS;
    }
}
