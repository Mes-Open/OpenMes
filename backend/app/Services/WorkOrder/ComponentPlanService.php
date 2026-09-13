<?php

namespace App\Services\WorkOrder;

use App\Models\ProcessTemplate;
use App\Models\ProductType;
use Illuminate\Validation\ValidationException;

/** Pure, bounded planning. No orders are written until the whole tree is valid. */
class ComponentPlanService
{
    public const MAX_DEPTH = 20;

    public const MAX_NODES = 1000;

    private array $templates = [];

    private array $activeTemplates = [];

    private int $nodes = 0;

    public function plan(array $snapshot, float $quantity): array
    {
        $this->templates = $this->activeTemplates = [];
        $this->nodes = 0;
        if (empty($snapshot['template_id']) || empty($snapshot['steps'])) {
            $this->fail('root', 'A process with production steps is required.');
        }

        return [
            'version' => 1,
            'quantity' => $quantity,
            'generated_at' => now()->toIso8601String(),
            'components' => $this->expand($snapshot, $quantity, '', []),
        ];
    }

    /** Recalculate an unstarted order from its frozen graph, never current master data. */
    public function resize(array $plan, float $quantity): array
    {
        $recalculate = function (array $nodes, float $parentQty) use (&$recalculate): array {
            foreach ($nodes as &$node) {
                [$required, $planned] = $this->quantities($node['specification'], $parentQty, $node['path'], $node['snapshot'] !== null);
                $node['required_qty'] = $required;
                $node['planned_qty'] = $planned;
                $node['children'] = $recalculate($node['children'], $planned);
            }

            return $nodes;
        };
        $history = $plan['history'] ?? [];
        unset($plan['history']);
        $history[] = $plan;
        $plan['components'] = $recalculate($plan['components'], $quantity);
        $plan['quantity'] = $quantity;
        $plan['version']++;
        $plan['generated_at'] = now()->toIso8601String();
        $plan['history'] = $history;

        return $plan;
    }

    public function quantities(array $line, float $quantity, string $path, bool $manufactured): array
    {
        $required = $this->multiply($quantity, (float) $line['quantity_per_unit'], $path);
        $scrap = (float) ($line['scrap_percentage'] ?? 0);
        if ($scrap < 0 || $scrap > 100) {
            $this->fail($path, 'Scrap must be between 0 and 100.');
        }
        $planned = $this->multiply($required, 1 + $scrap / 100, $path);
        if ($manufactured) {
            $unit = strtolower($line['unit_of_measure'] ?? 'pcs');
            $precision = in_array($unit, ['pcs', 'pc', 'piece', 'pieces', 'ea', 'unit', 'szt'], true) ? 1 : 100;
            $required = ceil(round($required * $precision, 6)) / $precision;
            $planned = max($required, ceil(round($planned * $precision, 6)) / $precision);
        }

        return [$required, $planned];
    }

    private function expand(array $snapshot, float $quantity, string $path, array $stack): array
    {
        $templateId = (int) $snapshot['template_id'];
        if (in_array($templateId, $stack, true) || count($stack) >= self::MAX_DEPTH) {
            $this->fail($path, 'Circular BOM or maximum depth exceeded.');
        }
        $stack[] = $templateId;
        $result = [];
        foreach ($snapshot['source_bom'] ?? $snapshot['bom'] ?? [] as $index => $line) {
            $nodePath = ltrim($path.'/'.($line['bom_item_id'] ?? $index), '/');
            if (++$this->nodes > self::MAX_NODES) {
                $this->fail($nodePath, 'Too many component occurrences.');
            }
            $manufactured = ($line['component_kind'] ?? 'material') === 'product_type' || ($line['is_manufactured'] ?? false);
            [$required, $planned] = $this->quantities($line, $quantity, $nodePath, $manufactured);
            $stepNumbers = array_column($snapshot['steps'], 'step_number');
            $consumingStep = $line['step_number'] ?? null;
            if (! in_array($consumingStep, $stepNumbers, true)) {
                $consumingStep = $stepNumbers[0] ?? null;
            }
            $childSnapshot = null;
            $children = [];
            if ($manufactured) {
                $childTemplateId = $line['component_template_id'] ?? $line['producing_process_template_id'] ?? null;
                if (! $childTemplateId && ! empty($line['product_type_id'])) {
                    $productId = (int) $line['product_type_id'];
                    if (! array_key_exists($productId, $this->activeTemplates)) {
                        $this->activeTemplates[$productId] = ProductType::find($productId)?->activeProcessTemplate()?->id;
                    }
                    $childTemplateId = $this->activeTemplates[$productId];
                }
                if (! $childTemplateId) {
                    $this->fail($nodePath, 'Manufactured component has no producing process.');
                }
                if (! isset($this->templates[$childTemplateId])) {
                    $template = ProcessTemplate::with(['steps', 'bomItems.material.materialType', 'bomItems.productType', 'bomItems.templateStep'])->find($childTemplateId);
                    if (! $template || $template->steps->isEmpty()) {
                        $this->fail($nodePath, 'Manufactured component process is missing or has no steps.');
                    }
                    $this->templates[$childTemplateId] = app(WorkOrderService::class)->attachEngineeringSnapshot(array_merge($template->toSnapshot(), ['product_type_id' => $template->product_type_id]), ['product_type_id' => $template->product_type_id]);
                }
                $childSnapshot = $this->templates[$childTemplateId];
                if (! empty($line['product_type_id']) && (int) $childSnapshot['product_type_id'] !== (int) $line['product_type_id']) {
                    $this->fail($nodePath, 'Component process belongs to another product.');
                }
                $children = $this->expand($childSnapshot, $planned, $nodePath, $stack);
            }
            $result[] = [
                'path' => $nodePath,
                'specification' => $line,
                'required_qty' => $required,
                'planned_qty' => $planned,
                'consuming_step_number' => $consumingStep,
                'snapshot' => $childSnapshot,
                'children' => $children,
            ];
        }

        return $result;
    }

    private function multiply(float $a, float $b, string $path): float
    {
        if (! is_finite($a) || ! is_finite($b) || $a <= 0 || $b <= 0 || $a * $b > 99999999) {
            $this->fail($path, 'Component quantity is invalid or exceeds 99999999.');
        }
        // Match BOM decimal:4 arithmetic; round at each edge before unit rounding.
        $left = (int) round($a * 10000);
        $right = (int) round($b * 10000);
        $result = intdiv($left * $right + 5000, 10000) / 10000;
        if ($result <= 0) {
            $this->fail($path, 'Component quantity is below supported precision.');
        }

        return $result;
    }

    private function fail(string $path, string $message): never
    {
        throw ValidationException::withMessages(['generate_components' => __('Component :path: :message', ['path' => $path ?: 'root', 'message' => __($message)])]);
    }
}
