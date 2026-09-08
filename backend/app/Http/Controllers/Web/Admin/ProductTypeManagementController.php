<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Concerns\StaysOnList;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductTypeRequest;
use App\Http\Requests\UpdateProductTypeRequest;
use App\Models\BatchStepLotConsumption;
use App\Models\ProductType;
use App\Models\SerialUnit;
use App\Services\CustomFieldService;
use App\Services\Media\ImageSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use InvalidArgumentException;

class ProductTypeManagementController extends Controller
{
    use StaysOnList;

    /**
     * Display a listing of product types.
     *
     * The rows themselves live-sync via the `product_types` collection
     * (see Pages/admin/product-types/Index.jsx). Only the cross-table counts —
     * which don't map to per-row sync — are passed as a prop, keyed by id.
     */
    public function index()
    {
        $counts = ProductType::withCount(['processTemplates', 'workOrders'])
            ->get(['id'])
            ->mapWithKeys(fn ($pt) => [$pt->id => [
                'process_templates' => $pt->process_templates_count,
                'work_orders' => $pt->work_orders_count,
            ]]);

        return Inertia::render('admin/product-types/Index', [
            'counts' => $counts,
            // Option lists for the list page's create/edit drawer. Optional, so the
            // queries only run once someone opens it — most visits never do.
            'customFields' => Inertia::optional(fn () => app(CustomFieldService::class)->clientConfig('product_type')),
            // `image_url` is an accessor over `image_path`, and neither is in the
            // `product_types` collection — so the drawer can't read the current
            // photo off the row the way it reads every other field.
            'imageUrls' => Inertia::optional(fn () => ProductType::whereNotNull('image_path')
                ->get(['id', 'image_path'])
                ->mapWithKeys(fn ($pt) => [$pt->id => $pt->imageUrl()])),
        ]);
    }

    /**
     * Show the form for creating a new product type
     */
    public function create(CustomFieldService $cf)
    {
        return Inertia::render('admin/product-types/Create', [
            'customFields' => $cf->clientConfig('product_type'),
        ]);
    }

    /**
     * Store a newly created product type
     */
    public function store(StoreProductTypeRequest $request, CustomFieldService $cf, ImageSanitizer $sanitizer)
    {
        // Store the photo before the insert: nothing about it depends on the
        // row, so a file we can't accept fails the form instead of leaving a
        // product type behind with an error pinned to it.
        try {
            $image = $this->storeImage($request->file('image'), $sanitizer);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['image' => $e->getMessage()]);
        }

        $validated = $request->validated();

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['unit_of_measure'] = $validated['unit_of_measure'] ?? 'pcs';
        unset($validated['custom_field_files'], $validated['image']);
        if ($cf->touched($request)) {
            $validated['custom_fields'] = $cf->fromRequest($request, 'product_type') ?: null;
        }

        ProductType::make($validated)->forceFill($image)->save();

        return $this->saved($request, redirect()->route('admin.product-types.index'), 'Product type created successfully.');
    }

    /**
     * Display the specified product type
     */
    public function show(ProductType $productType, CustomFieldService $cf)
    {
        $productType->load(['processTemplates.steps']);
        $recentWorkOrders = $productType->workOrders()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $totalWorkOrderCount = $productType->workOrders()->count();

        // Work order ids for this product type (tenant- and soft-delete-scoped
        // by the relation), reused for both the components and serials lookups.
        $workOrderIds = $productType->workOrders()->pluck('work_orders.id');

        $componentsUsed = $this->componentsConsumedBy($productType);
        $serials = $this->serialsProducedFor($workOrderIds);

        return Inertia::render('admin/product-types/Show', [
            'productType' => [
                'id' => $productType->id,
                'code' => $productType->code,
                'name' => $productType->name,
                'description' => $productType->description,
                'unit_of_measure' => $productType->unit_of_measure,
                'is_active' => $productType->is_active,
                'image_url' => $productType->imageUrl(),
                'custom_fields' => $productType->custom_fields,
                'process_templates' => $productType->processTemplates->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'version' => $t->version,
                    'is_active' => $t->is_active,
                    'steps' => $t->steps->map(fn ($s) => ['id' => $s->id])->values(),
                ])->values(),
                'total_work_order_count' => $totalWorkOrderCount,
            ],
            'recentWorkOrders' => $recentWorkOrders->map(fn ($wo) => [
                'id' => $wo->id,
                // work_orders has `order_no`, not work_order_number; these orders
                // all belong to this product type, so product_name is its name.
                'work_order_number' => $wo->order_no,
                'product_name' => $productType->name,
                'planned_qty' => $wo->planned_qty,
                'status' => $wo->status,
                'created_at' => $wo->created_at?->toIso8601String(),
            ])->values(),
            'componentsUsed' => $componentsUsed,
            'serials' => $serials,
            'customFields' => $cf->clientConfig('product_type'),
        ]);
    }

    /**
     * Materials actually consumed while producing this product type, aggregated
     * across every (non-deleted) work order → batch → step → lot consumption.
     * This is real genealogy ("what went in"), not the planned BOM.
     */
    private function componentsConsumedBy(ProductType $productType): \Illuminate\Support\Collection
    {
        return BatchStepLotConsumption::query()
            ->join('batch_steps', 'batch_steps.id', '=', 'batch_step_lot_consumption.batch_step_id')
            ->join('batches', 'batches.id', '=', 'batch_steps.batch_id')
            ->join('work_orders', 'work_orders.id', '=', 'batches.work_order_id')
            ->join('material_lots', 'material_lots.id', '=', 'batch_step_lot_consumption.material_lot_id')
            ->join('materials', 'materials.id', '=', 'material_lots.material_id')
            ->where('work_orders.product_type_id', $productType->id)
            ->whereNull('batch_steps.deleted_at')
            ->whereNull('batches.deleted_at')
            ->whereNull('work_orders.deleted_at')
            ->whereNull('material_lots.deleted_at')
            ->whereNull('materials.deleted_at')
            ->groupBy('materials.id', 'materials.code', 'materials.name', 'materials.unit_of_measure')
            ->orderByDesc(DB::raw('SUM(batch_step_lot_consumption.quantity_consumed)'))
            ->get([
                'materials.id as id',
                'materials.code as code',
                'materials.name as name',
                'materials.unit_of_measure as unit_of_measure',
                DB::raw('SUM(batch_step_lot_consumption.quantity_consumed) as total_consumed'),
                DB::raw('COUNT(DISTINCT material_lots.id) as lot_count'),
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'unit_of_measure' => $row->unit_of_measure,
                'total_consumed' => (float) $row->total_consumed,
                'lot_count' => (int) $row->lot_count,
            ])
            ->values();
    }

    /**
     * Serialized units produced under the given work orders: total, a status
     * breakdown and the 20 most recent units (linked to the traceability
     * console by serial number).
     */
    private function serialsProducedFor(\Illuminate\Support\Collection $workOrderIds): array
    {
        $base = SerialUnit::query()->whereIn('work_order_id', $workOrderIds);

        $recent = (clone $base)
            ->with(['workOrder:id,order_no', 'batch:id,batch_number,lot_number'])
            ->orderByDesc('produced_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return [
            'total' => (clone $base)->count(),
            'status_counts' => (clone $base)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'recent' => $recent->map(fn (SerialUnit $unit) => [
                'id' => $unit->id,
                'serial_no' => $unit->serial_no,
                'status' => $unit->status,
                'produced_at' => $unit->produced_at?->toIso8601String(),
                'work_order' => $unit->workOrder?->order_no,
                'batch' => $unit->batch?->lot_number
                    ?? ($unit->batch ? '#'.$unit->batch->batch_number : null),
            ])->values(),
        ];
    }

    /**
     * Show the form for editing a product type
     */
    public function edit(ProductType $productType, CustomFieldService $cf)
    {
        return Inertia::render('admin/product-types/Edit', [
            'productType' => $productType->only(
                'id', 'code', 'name', 'description', 'unit_of_measure', 'is_active', 'custom_fields'
            ) + ['image_url' => $productType->imageUrl()],
            'customFields' => $cf->clientConfig('product_type'),
        ]);
    }

    /**
     * Update the specified product type
     */
    public function update(
        UpdateProductTypeRequest $request,
        ProductType $productType,
        CustomFieldService $cf,
        ImageSanitizer $sanitizer,
    ) {
        // A new upload always wins over the remove flag.
        try {
            $image = $this->storeImage($request->file('image'), $sanitizer);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['image' => $e->getMessage()]);
        }
        if (! $image && $request->boolean('remove_image')) {
            $image = ['image_path' => null, 'image_mime' => null];
        }

        $validated = $request->validated();

        $validated['is_active'] = $request->boolean('is_active');
        $validated['unit_of_measure'] = $validated['unit_of_measure'] ?? 'pcs';
        unset($validated['custom_field_files'], $validated['image'], $validated['remove_image']);
        if ($cf->touched($request)) {
            $validated['custom_fields'] = $cf->fromRequest($request, 'product_type', $productType->custom_fields) ?: null;
        }

        $replaced = $image ? $productType->image_path : null;

        // One write, so subscribers see one CollectionChanged carrying the
        // whole edit rather than a half-applied row followed by the rest.
        $productType->fill($validated)->forceFill($image)->save();

        if ($replaced) {
            Storage::delete($replaced);
        }

        return $this->saved($request, redirect()->route('admin.product-types.index'), 'Product type updated successfully.');
    }

    /**
     * Stream the product photo. Files live on the private disk under a
     * server-generated name and are only ever served through here.
     */
    public function image(ProductType $productType)
    {
        abort_unless($productType->image_path && Storage::exists($productType->image_path), 404);

        // mime comes from our own re-encode — a known-safe raster type.
        return Storage::response($productType->image_path, null, [
            'Content-Type' => $productType->image_mime,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * Re-encode the upload (destroying anything smuggled inside it) and store
     * it under a random server-generated name, returning the columns that
     * point at it — the caller folds them into its own write.
     *
     * @return array{image_path?: string, image_mime?: string} empty when nothing was uploaded
     *
     * @throws InvalidArgumentException when the file is not a clean raster image
     */
    private function storeImage(?UploadedFile $file, ImageSanitizer $sanitizer): array
    {
        if (! $file) {
            return [];
        }

        $clean = $sanitizer->sanitize($file->getRealPath());

        // Never the client filename, never a client-supplied extension. The
        // random name is unique on its own, so the path needs no row id and
        // can therefore be written before the row exists.
        $path = 'product-type-images/'.Str::random(40).'.'.$clean['extension'];
        Storage::put($path, $clean['bytes']);

        return ['image_path' => $path, 'image_mime' => $clean['mime']];
    }

    /**
     * Remove the specified product type
     */
    public function destroy(ProductType $productType)
    {
        // Check if product type has work orders
        if ($productType->workOrders()->count() > 0) {
            return redirect()->route('admin.product-types.index')
                ->with('error', 'Cannot delete product type with existing work orders. Deactivate it instead.');
        }

        // Check if product type has process templates
        if ($productType->processTemplates()->count() > 0) {
            return redirect()->route('admin.product-types.index')
                ->with('error', 'Cannot delete product type with existing process templates. Deactivate it instead.');
        }

        $productType->delete();

        return redirect()->route('admin.product-types.index')
            ->with('success', 'Product type deleted successfully.');
    }

    /**
     * Toggle product type active status
     */
    public function toggleActive(ProductType $productType)
    {
        $productType->update(['is_active' => ! $productType->is_active]);

        $status = $productType->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.product-types.index')
            ->with('success', "Product type {$status} successfully.");
    }
}
