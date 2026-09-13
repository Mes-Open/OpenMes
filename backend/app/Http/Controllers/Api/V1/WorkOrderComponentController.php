<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Services\WorkOrder\ComponentPlanService;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkOrderComponentController extends Controller
{
    public function preview(Request $request, WorkOrderService $orders, ComponentPlanService $planner): JsonResponse
    {
        $this->authorize('create', WorkOrder::class);
        $data = $request->validate([
            ...\App\Http\Requests\Concerns\ComponentStockRules::rules(''),
            'product_type_id' => ['required', 'integer', 'exists:product_types,id'],
            'planned_qty' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'bom_template_ids' => ['nullable', 'array'],
            'bom_template_ids.*' => ['integer', \Illuminate\Validation\Rule::exists('process_templates', 'id')->where('product_type_id', $request->input('product_type_id'))->whereNull('deleted_at')],
        ], [
            ...\App\Http\Requests\Concerns\ComponentStockRules::messages(),
            'required' => __('The :attribute field is required.'),
            'numeric' => __('The :attribute field must be a number.'),
            'integer' => __('The :attribute field must be an integer.'),
            'min' => __('The :attribute field must be at least :min.'),
            'max' => __('The :attribute field must not exceed :max.'),
            'exists' => __('The selected :attribute is invalid.'),
            'array' => __('The :attribute field must be a list.'),
        ], [
            ...\App\Http\Requests\Concerns\ComponentStockRules::attributes(),
            'product_type_id' => __('Product Type'),
            'planned_qty' => __('Planned Qty'),
            'bom_template_ids' => __('Bills of Materials'),
            'bom_template_ids.*' => __('Bills of Materials'),
        ]);
        $snapshot = $orders->buildProcessSnapshot((int) $data['product_type_id'], $data['bom_template_ids'] ?? []);
        $plan = $planner->plan($snapshot ?? [], (float) $data['planned_qty']);

        $plan = app(\App\Services\WorkOrder\ComponentStockService::class)->net($plan, $data);

        // This preview is informational; generation validates and freezes again at creation.
        return response()->json(['data' => $plan]);
    }
}
