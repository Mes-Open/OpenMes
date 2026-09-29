<?php

namespace App\Services\Production;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\LotSequence;
use App\Models\TemplateStep;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Which operator screens a bench needs, so the person at it sees only the
 * tabs for their step: a packing bench gets Packing, an assembly bench gets
 * the queue, the workstation table and (for serialised products) SN labels.
 *
 * Derived from the routing: the kinds of steps routed to the bench (by the
 * bench itself or by its workstation type), in active process templates and
 * in batches still open there. An admin can pin the list per bench
 * (`workstations.operator_screens`). Supervisors and admins, and anyone
 * looking at the whole line, see every screen.
 *
 * The tabs are UX, not access control: a screen off the list still opens from
 * a bookmark or a link.
 */
class OperatorScreens
{
    public const QUEUE = 'queue';

    public const WORKSTATION = 'workstation';

    public const UNIT_LABELS = 'unit_labels';

    public const PACKING = 'packing';

    /** Canonical order, as the tabs read left to right. */
    public const ALL = [self::QUEUE, self::WORKSTATION, self::UNIT_LABELS, self::PACKING];

    /** @var array<int, array<int, string>> derived screens per workstation id; the service is request-scoped */
    private array $cache = [];

    public function __construct(private readonly OperatorWorkstationSelection $selection) {}

    /**
     * The screens for this user at the bench this session works at. The bench
     * comes from the same source the operator pages use (the one picked, else a
     * workstation account's own), so the whole-line view shows every tab.
     */
    public function forRequest(Request $request): array
    {
        $user = $request->user();
        if (! $user || ! $request->hasSession()) {
            return self::ALL;
        }
        $workstationId = $this->selection->storedWorkstationId($request);

        return $this->for($user, $workstationId ? Workstation::find($workstationId) : null);
    }

    public function for(User $user, ?Workstation $workstation): array
    {
        if (! $workstation || $user->hasAnyRole(['Admin', 'Supervisor'])) {
            return self::ALL;
        }

        return $this->forWorkstation($workstation);
    }

    /** The bench's own list: pinned by an admin, else from the routing. */
    public function forWorkstation(Workstation $workstation): array
    {
        $pinned = array_values(array_intersect(self::ALL, (array) ($workstation->operator_screens ?? [])));

        return $pinned !== [] ? $pinned : $this->derived($workstation);
    }

    /** What the routing sends to the bench, whatever an admin pinned. */
    public function derived(Workstation $workstation): array
    {
        return $this->derivedFor(collect([$workstation]))[$workstation->id];
    }

    /**
     * Derived screens for several benches in one pass (the admin list shows
     * them for every bench of a line).
     *
     * @param  Collection<int, Workstation>  $workstations
     * @return array<int, array<int, string>> keyed by workstation id
     */
    public function derivedFor(Collection $workstations): array
    {
        $missing = $workstations->reject(fn (Workstation $w) => isset($this->cache[$w->id]));
        if ($missing->isNotEmpty()) {
            foreach ($this->derive($missing) as $id => $screens) {
                $this->cache[$id] = $screens;
            }
        }

        return $workstations->mapWithKeys(fn (Workstation $w) => [$w->id => $this->cache[$w->id]])->all();
    }

    /**
     * @param  Collection<int, Workstation>  $workstations
     * @return array<int, array<int, string>>
     */
    private function derive(Collection $workstations): array
    {
        $ids = $workstations->pluck('id')->all();
        $types = $workstations->pluck('workstation_type_id')->filter()->unique()->values()->all();

        // Steps of active templates: pinned to a bench, or open to any bench of
        // a type (their own type, else their process segment's) and not pinned.
        $templateSteps = TemplateStep::query()
            ->whereHas('processTemplate', fn ($q) => $q->where('is_active', true))
            ->where(function ($q) use ($ids, $types) {
                $q->whereIn('workstation_id', $ids);
                if ($types !== []) {
                    $q->orWhere(fn ($t) => $t->whereNull('workstation_id')->where(fn ($ty) => $ty
                        ->whereIn('workstation_type_id', $types)
                        ->orWhere(fn ($seg) => $seg->whereNull('workstation_type_id')
                            ->whereHas('processSegment', fn ($ps) => $ps->whereIn('workstation_type_id', $types)))));
                }
            })
            ->with(['processTemplate:id,product_type_id', 'processTemplate.productType:id', 'processTemplate.productType.lines:id', 'processSegment:id,workstation_type_id'])
            ->get(['id', 'kind', 'process_template_id', 'workstation_id', 'workstation_type_id', 'process_segment_id']);

        // Orders already running keep their snapshot's routing: their open steps
        // count too, but not those of a cancelled batch or a finished order.
        $batchSteps = BatchStep::query()
            ->whereNotIn('status', [BatchStep::STATUS_DONE, BatchStep::STATUS_SKIPPED])
            ->whereHas('batch', fn ($b) => $b->whereNotIn('status', [Batch::STATUS_CANCELLED, Batch::STATUS_DONE])
                ->whereHas('workOrder', fn ($w) => $w->whereNotIn('status', [WorkOrder::STATUS_CANCELLED, WorkOrder::STATUS_DONE, WorkOrder::STATUS_REJECTED])))
            ->where(function ($q) use ($ids, $types) {
                $q->whereIn('workstation_id', $ids);
                if ($types !== []) {
                    $q->orWhere(fn ($t) => $t->whereNull('workstation_id')->whereIn('workstation_type_id', $types));
                }
            })
            ->with('batch:id,work_order_id', 'batch.workOrder:id,product_type_id,line_id')
            ->get(['id', 'kind', 'batch_id', 'workstation_id', 'workstation_type_id']);

        $serialProducts = $this->serialisedProducts(
            $templateSteps->toBase()->map(fn ($s) => $s->processTemplate?->product_type_id)
                ->concat($batchSteps->toBase()->map(fn ($s) => $s->batch?->workOrder?->product_type_id))
                ->filter()->unique()->values()->all()
        );

        $result = [];
        foreach ($workstations as $ws) {
            $steps = collect();
            foreach ($templateSteps as $s) {
                $typeId = $s->workstation_type_id ?? $s->processSegment?->workstation_type_id;
                $mine = $s->workstation_id === $ws->id
                    || ($s->workstation_id === null && $ws->workstation_type_id && $typeId === $ws->workstation_type_id
                        // by type only for products this bench's line makes
                        && ($s->processTemplate?->productType?->lines?->contains('id', $ws->line_id) ?? false));
                if ($mine) {
                    $steps->push(['kind' => $s->kind, 'product' => $s->processTemplate?->product_type_id]);
                }
            }
            foreach ($batchSteps as $s) {
                $mine = $s->workstation_id === $ws->id
                    || ($s->workstation_id === null && $ws->workstation_type_id && $s->workstation_type_id === $ws->workstation_type_id
                        && $s->batch?->workOrder?->line_id === $ws->line_id);
                if ($mine) {
                    $steps->push(['kind' => $s->kind, 'product' => $s->batch?->workOrder?->product_type_id]);
                }
            }
            $result[$ws->id] = $this->screensFrom($steps, $serialProducts);
        }

        return $result;
    }

    /** @param  array<int, int>  $serialProducts */
    private function screensFrom(Collection $steps, array $serialProducts): array
    {
        $packing = $steps->contains(fn ($s) => $s['kind'] === TemplateStep::KIND_PACKING);
        $production = $steps->filter(fn ($s) => ($s['kind'] ?? TemplateStep::KIND_PRODUCTION) !== TemplateStep::KIND_PACKING);

        $screens = [];
        if ($production->isNotEmpty()) {
            $screens[] = self::QUEUE;
            $screens[] = self::WORKSTATION;
            if ($production->contains(fn ($s) => in_array($s['product'], $serialProducts, true))) {
                $screens[] = self::UNIT_LABELS;
            }
        }
        if ($packing) {
            $screens[] = self::PACKING;
        }

        // A bench nothing is routed to still gets somewhere to work.
        return $screens === [] ? [self::QUEUE, self::WORKSTATION] : $screens;
    }

    /**
     * The products that number their units (a serial or process-serial sequence),
     * or whose routing makes a serial-tracked sub-assembly.
     *
     * @param  array<int, int>  $productTypeIds
     * @return array<int, int>
     */
    private function serialisedProducts(array $productTypeIds): array
    {
        if ($productTypeIds === []) {
            return [];
        }

        $numbered = LotSequence::query()
            ->whereIn('purpose', [LotSequence::PURPOSE_PROCESS_SERIAL, LotSequence::PURPOSE_UNIT_SERIAL])
            ->whereIn('product_type_id', $productTypeIds)
            ->distinct()->pluck('product_type_id');
        // A product whose routing makes a serial-tracked sub-assembly: its bench
        // registers each piece by its own serial on the label station.
        $makingSerialParts = \App\Models\ProcessTemplate::query()
            ->whereIn('product_type_id', $productTypeIds)
            ->whereIn('id', \App\Models\Material::query()->where('tracking_type', 'serial')->whereNotNull('producing_process_template_id')->select('producing_process_template_id'))
            ->distinct()->pluck('product_type_id');

        return $numbered->merge($makingSerialParts)->unique()->map(fn ($id) => (int) $id)->values()->all();
    }

    /**
     * Where an operator lands after picking a line and bench: the line's
     * default production view when the bench does production, else its first
     * screen (a packing bench goes straight to packing).
     */
    public function landingRoute(array $screens, string $lineDefaultView = 'queue'): string
    {
        $preferred = $lineDefaultView === 'workstation' ? self::WORKSTATION : self::QUEUE;
        $screen = in_array($preferred, $screens, true) ? $preferred : ($screens[0] ?? self::QUEUE);

        return self::routeName($screen);
    }

    public static function routeName(string $screen): string
    {
        return match ($screen) {
            self::WORKSTATION => 'operator.workstation',
            self::UNIT_LABELS => 'operator.unit-labels.station',
            self::PACKING => 'operator.packaging',
            default => 'operator.queue',
        };
    }
}
