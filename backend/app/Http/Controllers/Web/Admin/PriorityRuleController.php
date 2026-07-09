<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\PriorityBandsRequest;
use App\Http\Requests\Web\Admin\PriorityRuleRequest;
use App\Jobs\RecalculatePriorities;
use App\Models\PriorityRule;
use App\Support\PriorityBandRegistry;
use Inertia\Inertia;

class PriorityRuleController extends Controller
{
    /**
     * Priority Settings page: the rules table live-syncs via the `priority_rules`
     * shape; the score→priority band thresholds come as a prop.
     */
    public function index()
    {
        return Inertia::render('admin/priority-rules/Index', [
            'bands' => PriorityBandRegistry::bands(),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/priority-rules/Create');
    }

    public function store(PriorityRuleRequest $request)
    {
        $data = $this->normalize($request);
        PriorityRule::create($data);
        $this->recalculate();

        return redirect()->route('admin.priority-rules.index')
            ->with('success', __('Priority rule created successfully.'));
    }

    public function edit(PriorityRule $priorityRule)
    {
        return Inertia::render('admin/priority-rules/Edit', [
            'priorityRule' => $priorityRule->only(
                'id', 'name', 'field_source', 'condition_type',
                'condition_value', 'condition_value_max', 'points', 'is_active', 'sort_order'
            ),
        ]);
    }

    public function update(PriorityRuleRequest $request, PriorityRule $priorityRule)
    {
        $priorityRule->update($this->normalize($request));
        $this->recalculate();

        return redirect()->route('admin.priority-rules.index')
            ->with('success', __('Priority rule updated successfully.'));
    }

    public function destroy(PriorityRule $priorityRule)
    {
        $priorityRule->delete();
        $this->recalculate();

        return redirect()->route('admin.priority-rules.index')
            ->with('success', __('Priority rule deleted successfully.'));
    }

    public function toggleActive(PriorityRule $priorityRule)
    {
        $priorityRule->update(['is_active' => ! $priorityRule->is_active]);
        $this->recalculate();

        $status = $priorityRule->is_active ? __('activated') : __('deactivated');

        return redirect()->route('admin.priority-rules.index')
            ->with('success', __('Priority rule :status successfully.', ['status' => $status]));
    }

    /** Save the score→priority band thresholds. */
    public function updateBands(PriorityBandsRequest $request)
    {
        PriorityBandRegistry::save($request->validated()['bands']);
        $this->recalculate();

        return redirect()->route('admin.priority-rules.index')
            ->with('success', __('Priority bands updated successfully.'));
    }

    /**
     * Coerce blank optional values to null and default is_active/sort_order.
     *
     * @return array<string, mixed>
     */
    private function normalize(PriorityRuleRequest $request): array
    {
        $data = $request->validated();
        $data['condition_value'] = ($data['condition_value'] ?? '') === '' ? null : $data['condition_value'];
        $data['condition_value_max'] = ($data['condition_value_max'] ?? '') === '' ? null : $data['condition_value_max'];
        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }

    /**
     * Re-score active work orders after the rule set or bands change. Queued so
     * a large shop's recalculation never blocks the admin's HTTP request.
     */
    private function recalculate(): void
    {
        RecalculatePriorities::dispatch();
    }
}
