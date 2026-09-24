<?php

namespace App\Console\Commands;

use App\Models\BomItem;
use App\Models\IssueType;
use App\Models\LabelTemplate;
use App\Models\Line;
use App\Models\LotSequence;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Imports the demo bundle JSON files (the main import file and its
 * traceability addition). Idempotent: re-running updates rows in place.
 *
 * Bundle reference rule: numeric *_id fields are 1-based positions into the
 * referenced entity array of the same file.
 */
class ImportLineBundle extends Command
{
    protected $signature = 'demo:import-line-bundle
        {paths* : Path to the bundle JSON file(s), in dependency order (main file first, then the addition)}';

    protected $description = 'Import the demo bundle (lines, workstations, templates, materials, BOM, issue types, lot sequences, label templates, work orders)';

    /**
     * Bundle key => [model, upsert key fields, ref field => source bundle key].
     * Order matters: referenced entities are processed before their referrers.
     */
    private const ENTITIES = [
        'lines' => [Line::class, ['code'], []],
        'workstations' => [Workstation::class, ['code'], ['line_id' => 'lines']],
        'product_types' => [ProductType::class, ['code'], []],
        'process_templates' => [ProcessTemplate::class, ['name'], ['product_type_id' => 'product_types']],
        'template_steps' => [TemplateStep::class, ['process_template_id', 'step_number'], ['process_template_id' => 'process_templates', 'workstation_id' => 'workstations']],
        'material_types' => [MaterialType::class, ['code'], []],
        'materials' => [Material::class, ['code'], ['material_type_id' => 'material_types']],
        'bom_items' => [BomItem::class, ['process_template_id', 'template_step_id', 'material_id'], ['process_template_id' => 'process_templates', 'template_step_id' => 'template_steps', 'material_id' => 'materials']],
        'issue_types' => [IssueType::class, ['code'], []],
        'lot_sequences' => [LotSequence::class, ['name'], ['product_type_id' => 'product_types']],
        'label_templates' => [LabelTemplate::class, ['name'], []],
        'work_orders' => [WorkOrder::class, ['order_no'], ['line_id' => 'lines', 'product_type_id' => 'product_types']],
    ];

    private const SKIPPED_KEYS = [
        'sites' => 'optional module (table/model not installed)',
        'areas' => 'optional module (table/model not installed)',
    ];

    public function handle(WorkOrderService $workOrders): int
    {
        $totalCreated = 0;
        $totalUpdated = 0;
        $errors = 0;

        foreach ((array) $this->argument('paths') as $path) {
            if (!is_file($path)) {
                $this->error("File not found: {$path}");
                $errors++;
                continue;
            }

            $file = json_decode((string) file_get_contents($path), true);
            if (!is_array($file)) {
                $this->error("Invalid JSON: {$path}");
                $errors++;
                continue;
            }

            $this->info("Importing {$path}");
            $posMap = [];

            foreach (self::ENTITIES as $key => [$modelClass, $keyFields, $refs]) {
                if (!isset($file[$key]) || !is_array($file[$key]) || $file[$key] === []) {
                    continue;
                }
                [$created, $updated, $failed] = $this->upsertEntity($key, $modelClass, $keyFields, $refs, $file[$key], $file, $posMap, $workOrders);
                $totalCreated += $created;
                $totalUpdated += $updated;
                $errors += $failed;
                $this->line(sprintf('  %-18s %d created, %d updated, %d failed', $key, $created, $updated, $failed));
            }

            foreach (self::SKIPPED_KEYS as $key => $reason) {
                if (isset($file[$key])) {
                    $this->warn("  {$key} skipped: {$reason}");
                }
            }
        }

        $this->info("Done: {$totalCreated} created, {$totalUpdated} updated, {$errors} errors.");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{0:int,1:int,2:int} created, updated, failed
     */
    private function upsertEntity(string $key, string $modelClass, array $keyFields, array $refs, array $rows, array $file, array &$posMap, WorkOrderService $workOrders): array
    {
        $probe = new $modelClass();
        if (!Schema::hasTable($probe->getTable())) {
            $this->warn("  {$key} skipped: table {$probe->getTable()} does not exist");

            return [0, 0, 0];
        }

        $fillable = array_flip($probe->getFillable());
        $posMap[$key] = [];
        $created = 0;
        $updated = 0;
        $failed = 0;

        foreach ($rows as $i => $row) {
            $pos = $i + 1;
            try {
                foreach ($refs as $field => $srcKey) {
                    if (array_key_exists($field, $row)) {
                        $row[$field] = $this->resolveRef($row[$field], $srcKey, $file, $posMap, $key, $pos, $field);
                    }
                }

                if ($key === 'work_orders'
                    && (($row['process_snapshot'] ?? null) === 'auto' || !array_key_exists('process_snapshot', $row))) {
                    $row['process_snapshot'] = $workOrders->buildProcessSnapshot($row['product_type_id'] ?? null) ?? ['steps' => []];
                }

                $attrs = array_intersect_key($row, $fillable);
                $keyValues = array_intersect_key($attrs, array_flip($keyFields));

                $model = $modelClass::query()->where($keyValues)->first();
                if ($model !== null) {
                    $model->update(array_diff_key($attrs, $keyValues));
                    $updated++;
                } else {
                    $model = $modelClass::create($attrs);
                    $created++;
                }
                $posMap[$key][$pos] = $model;
            } catch (Throwable $e) {
                $failed++;
                $label = $row['code'] ?? $row['name'] ?? $row['order_no'] ?? '';
                $this->error("  {$key}[{$pos}] {$label}: {$e->getMessage()}");
            }
        }

        return [$created, $updated, $failed];
    }

    /**
     * Resolve a bundle reference (1-based position in the referenced array of
     * this file) to the imported model's id.
     */
    private function resolveRef(mixed $value, string $srcKey, array $file, array $posMap, string $ownerKey, int $pos, string $field): ?int
    {
        $srcPos = (int) $value;
        $src = $file[$srcKey] ?? null;
        if (!is_array($src) || !isset($src[$srcPos - 1])) {
            $this->warn("  {$ownerKey}[{$pos}].{$field}: position {$srcPos} of {$srcKey} is not defined in this file, leaving null");

            return null;
        }

        return $posMap[$srcKey][$srcPos]->id ?? null;
    }
}
