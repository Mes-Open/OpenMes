<?php

namespace App\Services\Erp;

use App\Models\AuditLog;
use App\Models\BomItem;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Erp\Concerns\ReportsImportRows;

/**
 * ERP → OpenMES routing (process template) import.
 *
 * An ERP routing is an ordered list of operations per product. Each row here is
 * one product's routing and becomes a ProcessTemplate with its TemplateSteps, so
 * a product and its routing from the ERP are enough to accept the first work
 * order without anyone touching OpenMES first.
 *
 * Versioning follows the admin UI: `new_version` (the default) adds version
 * max+1 as active and leaves earlier versions alone — work orders already
 * released carry a frozen `process_snapshot`, and every consumer picks the
 * newest active version. `replace_draft` rewrites an inactive template that no
 * work order refers to, for routings a technologist still wants to review.
 *
 * ERP operation numbers (10, 20, 30…) only order the steps: they are stored as
 * 1..n because step sequencing looks up the previous step as `step_number - 1`.
 */
class ProcessTemplateImportService
{
    use ReportsImportRows;

    public const MODE_NEW_VERSION = 'new_version';

    public const MODE_REPLACE_DRAFT = 'replace_draft';

    public const MODES = [self::MODE_NEW_VERSION, self::MODE_REPLACE_DRAFT];

    /** Fields compared to decide whether a re-sent routing changed. */
    private const COMPARED_FIELDS = ['name', 'workstation_id', 'setup_time_minutes', 'run_time_per_unit_minutes', 'instruction', 'requires_confirmation'];

    /** @var array<string, int|null> "line|workstation" code pair => workstation id, per request */
    private array $workstations = [];

    /** @var array<string, int|null> line code => line id, per request */
    private array $lines = [];

    /**
     * @param  array<int, array<string, mixed>>  $rows  one row per product routing
     * @param  'new_version'|'replace_draft'  $mode
     * @return array{imported: int, updated: int, skipped: int, errors: array<int, array<string, mixed>>}
     */
    public function import(array $rows, string $mode = self::MODE_NEW_VERSION): array
    {
        $this->workstations = [];
        $this->lines = [];

        return $this->processRows($rows, function (array $row) use ($mode) {
            $productCode = trim((string) ($row['product_type_code'] ?? ''));

            if ($productCode === '') {
                return $this->error('product_type_code', __('Product code is required'));
            }

            $product = ProductType::where('code', $productCode)->first();

            if (! $product) {
                return $this->error('product_type_code', __("Product ':code' not found", ['code' => $productCode]));
            }

            $steps = $this->resolveSteps($row['steps'] ?? []);

            if (isset($steps['status'])) {
                return $steps; // a row error
            }

            $name = trim((string) ($row['template_name'] ?? '')) ?: $product->name;

            return $mode === self::MODE_REPLACE_DRAFT
                ? $this->replaceDraft($product, $name, $steps)
                : $this->newVersion($product, $name, $steps);
        });
    }

    /**
     * A one-step active template for a product type that has none, so an ERP
     * product is orderable straight after its import. Returns null when the type
     * already has a template of any state — a technologist's routing is never
     * overwritten by a placeholder.
     */
    public function createDefault(ProductType $product, ?string $stepName = null): ?ProcessTemplate
    {
        if ($product->processTemplates()->exists()) {
            return null;
        }

        $template = $this->createTemplate($product, $product->name, true, [[
            'step_number' => 1,
            'name' => $stepName ?: __('Production'),
            'kind' => TemplateStep::KIND_PRODUCTION,
        ]]);

        $this->audit($template, null, 'default');

        return $template;
    }

    /**
     * Resolves and normalises the payload's steps before anything is written, so
     * a routing with one unknown workstation fails as a whole row.
     *
     * @param  mixed  $steps
     * @return array<int, array<string, mixed>>|array<string, mixed> step attributes, or a row error
     */
    private function resolveSteps($steps): array
    {
        if (! is_array($steps) || $steps === []) {
            return $this->error('steps', __('A routing needs at least one step'));
        }

        $steps = array_values($steps);
        $seen = [];

        foreach ($steps as $i => $step) {
            $number = (int) ($step['step_number'] ?? 0);

            if (isset($seen[$number])) {
                return $this->error("steps.{$i}.step_number", __("Step number ':number' is listed twice", ['number' => $number]));
            }

            $seen[$number] = true;
        }

        // Keep the payload index next to each step so errors point at the
        // caller's own position, not the sorted one.
        $indexed = array_map(null, array_keys($steps), $steps);
        usort($indexed, fn ($a, $b) => (int) $a[1]['step_number'] <=> (int) $b[1]['step_number']);

        $resolved = [];

        foreach ($indexed as $position => [$i, $step]) {
            $workstationCode = trim((string) ($step['workstation_code'] ?? ''));
            $lineCode = trim((string) ($step['line_code'] ?? ''));
            $workstationId = null;

            if ($lineCode !== '' && $workstationCode === '') {
                return $this->error("steps.{$i}.line_code", __('A line code needs a workstation code: steps are routed to workstations'));
            }

            if ($lineCode !== '' && $this->lineId($lineCode) === null) {
                return $this->error("steps.{$i}.line_code", __("Line ':code' not found", ['code' => $lineCode]));
            }

            if ($workstationCode !== '') {
                $workstationId = $this->workstationId($workstationCode, $lineCode);

                if ($workstationId === null) {
                    return $this->error("steps.{$i}.workstation_code", $lineCode !== ''
                        ? __("Workstation ':code' not found on line ':line'", ['code' => $workstationCode, 'line' => $lineCode])
                        : __("Workstation ':code' not found", ['code' => $workstationCode]));
                }
            }

            $resolved[] = [
                'step_number' => $position + 1,
                'name' => trim((string) $step['name']),
                'kind' => TemplateStep::KIND_PRODUCTION,
                'workstation_id' => $workstationId,
                'setup_time_minutes' => isset($step['setup_time_minutes']) ? (int) $step['setup_time_minutes'] : null,
                'run_time_per_unit_minutes' => isset($step['run_time_per_unit_minutes']) ? round((float) $step['run_time_per_unit_minutes'], 2) : null,
                'instruction' => isset($step['instruction']) && trim((string) $step['instruction']) !== '' ? (string) $step['instruction'] : null,
                'requires_confirmation' => (bool) ($step['requires_confirmation'] ?? false),
            ];
        }

        return $resolved;
    }

    /** @param  array<int, array<string, mixed>>  $steps */
    private function newVersion(ProductType $product, string $name, array $steps): array
    {
        $active = $product->activeProcessTemplate();

        // An ERP sync re-sends the same routing every night; that must not
        // stack up v2, v3, v4… of an unchanged process. Compared with the
        // version new orders get, so an inactive draft on top of it does not
        // turn an unchanged routing into a new version.
        if ($active && $this->sameSteps($active, $steps)) {
            return $this->skipped();
        }

        // The template the recipe currently lives on — the one the BOM importer
        // would pick. Recipes hang off the template, so without a carry-over the
        // new version (and every order frozen from it) would have no BOM.
        $bomSource = $active ?? $product->processTemplates()->orderByDesc('version')->first();

        $template = $this->createTemplate($product, $name, true, $steps);

        if ($bomSource) {
            $this->copyBom($bomSource, $template);
        }

        $this->audit($template, null, self::MODE_NEW_VERSION);

        return $this->created(['code' => $product->code, 'version' => $template->version]);
    }

    /** @param  array<int, array<string, mixed>>  $steps */
    private function replaceDraft(ProductType $product, string $name, array $steps): array
    {
        // A draft is an inactive template no work order was ever frozen from;
        // an active template or one an order refers to is never rewritten. It
        // must also be newer than the newest active version: an older, retired
        // one would stay unused when activated, as orders take the newest active.
        $activeVersion = $product->activeProcessTemplate()?->version;

        $draft = $product->processTemplates()
            ->where('is_active', false)
            ->when($activeVersion !== null, fn ($q) => $q->where('version', '>', $activeVersion))
            ->orderByDesc('version')
            ->get()
            ->first(fn (ProcessTemplate $t) => ! WorkOrder::where('process_snapshot->template_id', $t->id)->exists());

        if (! $draft) {
            $template = $this->createTemplate($product, $name, false, $steps);
            $this->audit($template, null, self::MODE_REPLACE_DRAFT);

            return $this->created(['code' => $product->code, 'version' => $template->version]);
        }

        $before = $this->stepState($draft);

        if ($this->sameSteps($draft, $steps) && $draft->name === $name) {
            return $this->skipped();
        }

        $draft->update(['name' => $name]);

        $wanted = collect($steps)->keyBy('step_number');
        $existing = $draft->steps()->get()->keyBy('step_number');

        // Steps whose number is gone are soft-deleted first, which also frees
        // their number under the partial unique index. Recipe lines pointing at
        // them are detached rather than lost.
        foreach ($existing->diffKeys($wanted) as $stale) {
            BomItem::where('template_step_id', $stale->id)->update(['template_step_id' => null]);
            $stale->delete();
        }

        // Matching numbers are updated in place so their media and checklists
        // survive; new numbers are created.
        foreach ($wanted as $number => $attributes) {
            if ($step = $existing->get($number)) {
                $step->update($attributes);
            } else {
                $draft->steps()->create($attributes);
            }
        }

        $this->audit($draft->fresh(), $before, self::MODE_REPLACE_DRAFT);

        return $this->updated(['code' => $product->code, 'version' => $draft->version]);
    }

    /** @param  array<int, array<string, mixed>>  $steps */
    private function createTemplate(ProductType $product, string $name, bool $active, array $steps): ProcessTemplate
    {
        // Same rule as the admin UI: max+1 over live templates. Soft-deleted
        // versions are counted too so the (product, version) unique index holds
        // whether or not it is partial.
        $version = (int) ProcessTemplate::withTrashed()->where('product_type_id', $product->id)->max('version') + 1;

        $template = ProcessTemplate::create([
            'product_type_id' => $product->id,
            'name' => $name,
            'version' => $version,
            'is_active' => $active,
        ]);

        foreach ($steps as $attributes) {
            $template->steps()->create($attributes);
        }

        return $template;
    }

    private function copyBom(ProcessTemplate $from, ProcessTemplate $to): void
    {
        $stepIds = $to->steps()->pluck('id', 'step_number');

        foreach ($from->bomItems()->with('templateStep')->get() as $item) {
            $copy = $item->replicate(['process_template_id', 'template_step_id']);
            $copy->process_template_id = $to->id;
            // Attached to the step with the same number in the new routing, or to
            // the order as a whole when that step no longer exists.
            $copy->template_step_id = $item->templateStep ? ($stepIds[$item->templateStep->step_number] ?? null) : null;
            $copy->save();
        }
    }

    /** @param  array<int, array<string, mixed>>  $steps */
    private function sameSteps(ProcessTemplate $template, array $steps): bool
    {
        $current = $this->stepState($template);
        $incoming = array_map(fn (array $s) => $this->comparable($s), $steps);

        return $current === $incoming;
    }

    /** @return array<int, array<string, mixed>> */
    private function stepState(ProcessTemplate $template): array
    {
        return $template->steps()->get()
            ->map(fn (TemplateStep $s) => $this->comparable($s->only(self::COMPARED_FIELDS)))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array<string, mixed>
     */
    private function comparable(array $step): array
    {
        return [
            'name' => (string) $step['name'],
            'workstation_id' => $step['workstation_id'] !== null ? (int) $step['workstation_id'] : null,
            'setup_time_minutes' => $step['setup_time_minutes'] !== null ? (int) $step['setup_time_minutes'] : null,
            'run_time_per_unit_minutes' => $step['run_time_per_unit_minutes'] !== null ? round((float) $step['run_time_per_unit_minutes'], 2) : null,
            'instruction' => $step['instruction'] ?: null,
            'requires_confirmation' => (bool) $step['requires_confirmation'],
        ];
    }

    private function lineId(string $code): ?int
    {
        if (! array_key_exists($code, $this->lines)) {
            // Line carries the tenant scope, so another tenant's line is unknown.
            $this->lines[$code] = Line::where('code', $code)->value('id');
        }

        return $this->lines[$code];
    }

    private function workstationId(string $code, string $lineCode): ?int
    {
        $key = $lineCode.'|'.$code;

        if (! array_key_exists($key, $this->workstations)) {
            // Workstation has no tenant scope of its own: going through its line
            // is what confines the lookup to the API key's tenant.
            $this->workstations[$key] = Workstation::where('code', $code)
                ->whereHas('line', fn ($q) => $lineCode !== '' ? $q->where('code', $lineCode) : $q)
                ->orderBy('id')
                ->value('id');
        }

        return $this->workstations[$key];
    }

    /** @param  array<int, array<string, mixed>>|null  $before */
    private function audit(ProcessTemplate $template, ?array $before, string $mode): void
    {
        AuditLog::create([
            'user_id' => null,
            'entity_type' => ProcessTemplate::class,
            'entity_id' => $template->id,
            'action' => 'process_template.erp_imported',
            'before_state' => $before !== null ? ['steps' => $before] : null,
            'after_state' => [
                'mode' => $mode,
                'version' => $template->version,
                'is_active' => $template->is_active,
                'steps' => $this->stepState($template),
            ],
        ]);
    }
}
