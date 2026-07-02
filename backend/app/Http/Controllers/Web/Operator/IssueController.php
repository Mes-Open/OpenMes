<?php

namespace App\Http\Controllers\Web\Operator;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\IssueType;
use App\Models\WorkOrder;
use App\Services\CustomFieldService;
use Illuminate\Http\Request;

class IssueController extends Controller
{
    public function store(Request $request, CustomFieldService $cf)
    {
        $validated = $request->validate(array_merge([
            'work_order_id'  => 'required|exists:work_orders,id',
            'issue_type_id'  => 'required|exists:issue_types,id',
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string|max:2000',
        ], $cf->rules('issue')), [], $cf->attributeNames('issue'));

        $workOrder = WorkOrder::findOrFail($validated['work_order_id']);

        // Verify work order belongs to selected line
        if ($workOrder->line_id != $request->session()->get('selected_line_id')) {
            return back()->with('error', __('This work order does not belong to the selected line.'));
        }

        $issueType = IssueType::findOrFail($validated['issue_type_id']);

        Issue::create([
            'work_order_id' => $workOrder->id,
            'issue_type_id' => $issueType->id,
            'title'         => $validated['title'],
            'description'   => $validated['description'] ?? null,
            'status'        => Issue::STATUS_OPEN,
            'reported_by_id' => auth()->id(),
            'reported_at'   => now(),
            'custom_fields' => $cf->touched($request) ? ($cf->fromRequest($request, 'issue') ?: null) : null,
        ]);

        // If the issue type is blocking, block the work order
        if ($issueType->is_blocking) {
            $workOrder->update(['status' => WorkOrder::STATUS_BLOCKED]);
        }

        return redirect()->back()->with('success', __('Issue reported successfully.'));
    }
}
