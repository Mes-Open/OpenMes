<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\StoreStockDocumentRequest;
use App\Models\Material;
use App\Models\ProductType;
use App\Models\StockDocument;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Services\Warehouse\StockDocumentService;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Admin UI for warehouse documents (#212): the list live-syncs via the
 * `stock_documents` shape, and posting / cancelling delegates to
 * StockDocumentService so the balance and ledger side effects are identical to
 * the ERP and automatic paths.
 */
class StockDocumentController extends Controller
{
    public function __construct(private StockDocumentService $documents) {}

    public function index()
    {
        return Inertia::render('admin/stock-documents/Index', [
            // Covers every warehouse an existing row might reference, deactivated
            // ones included — unlike the create-form options below, which only
            // offer what is still selectable.
            'warehouseCodes' => Warehouse::pluck('code', 'id'),
            // Subquery, not a pluck: the id list never has to travel to PHP and back.
            'workOrders' => WorkOrder::whereIn(
                'id',
                StockDocument::query()->whereNotNull('work_order_id')->select('work_order_id'),
            )
                ->get(['id', 'order_no'])
                ->keyBy('id')
                ->map
                ->order_no,
            // Feeds the list's "New Document" modal, which renders the same form
            // as the create page.
            ...$this->createFormOptions(),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/stock-documents/Create', $this->createFormOptions());
    }

    /**
     * Options the create form needs, wherever it is rendered — the standalone
     * create page and the list's modal must offer exactly the same choices.
     */
    private function createFormOptions(): array
    {
        return [
            'warehouses' => Warehouse::active()->orderBy('code')->get(['id', 'code', 'name', 'kind', 'is_default']),
            'materials' => Material::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'unit_of_measure']),
            'productTypes' => ProductType::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'unit_of_measure']),
            'types' => StockDocument::TYPES,
        ];
    }

    public function store(StoreStockDocumentRequest $request)
    {
        try {
            $document = $this->documents->createDraft($request->validated(), $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        // The list's New-Document modal posts `stay` so the user keeps their page
        // (the new draft lands there through the synced collection).
        if ($request->boolean('stay')) {
            return back()->with('success', __('Stock document :no created.', ['no' => $document->document_no]));
        }

        return redirect()->route('admin.stock-documents.show', $document)
            ->with('success', __('Stock document created successfully.'));
    }

    public function show(StockDocument $stockDocument)
    {
        $stockDocument->load([
            'lines.material:id,code,name',
            'lines.productType:id,code,name',
            'lines.materialLot:id,lot_number',
            'warehouse:id,code,name,kind',
            'workOrder:id,order_no',
            'postedBy:id,name',
            'createdBy:id,name',
        ]);

        return Inertia::render('admin/stock-documents/Show', [
            'document' => [
                ...$stockDocument->only(
                    'id', 'document_no', 'type', 'status', 'notes', 'erp_reference'
                ),
                'direction' => $stockDocument->direction() > 0 ? 'in' : 'out',
                'warehouse' => $stockDocument->warehouse?->only('id', 'code', 'name', 'kind'),
                'work_order_no' => $stockDocument->workOrder?->order_no,
                // ISO strings: the page formats them with formatDateTime(), which
                // applies the configured plant timezone. A pre-formatted string
                // would print raw UTC and disagree with the list.
                'posted_at' => $stockDocument->posted_at?->toIso8601String(),
                'posted_by' => $stockDocument->postedBy?->name,
                'created_by' => $stockDocument->createdBy?->name,
                'erp_synced_at' => $stockDocument->erp_synced_at?->toIso8601String(),
                'created_at' => $stockDocument->created_at?->toIso8601String(),
                'lines' => $stockDocument->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'item_code' => $line->material?->code ?? $line->productType?->code,
                    'item_name' => $line->material?->name ?? $line->productType?->name,
                    'lot_number' => $line->effectiveLotNumber(),
                    'quantity' => (float) $line->quantity,
                    'unit_of_measure' => $line->unit_of_measure,
                    'notes' => $line->notes,
                ]),
            ],
        ]);
    }

    /** Apply the document to stock. */
    public function post(StockDocument $stockDocument)
    {
        try {
            $this->documents->post($stockDocument, request()->user());
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', __('Stock document posted successfully.'));
    }

    /** Reverse a posted document (or void a draft). */
    public function cancel(StockDocument $stockDocument)
    {
        try {
            $this->documents->cancel($stockDocument, request()->user());
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', __('Stock document cancelled successfully.'));
    }

    /**
     * Delete a document. Only a draft or a cancelled one — a posted document is
     * the record of a real stock movement, so it must be cancelled (which
     * reverses the balances) before it can go.
     */
    public function destroy(StockDocument $stockDocument)
    {
        if ($stockDocument->isPosted()) {
            return redirect()->route('admin.stock-documents.index')
                ->with('error', __('Cancel the document before deleting it.'));
        }

        $stockDocument->delete();

        return redirect()->route('admin.stock-documents.index')
            ->with('success', __('Stock document deleted successfully.'));
    }
}
