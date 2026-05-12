<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\DashboardWidget;
use Illuminate\Http\Request;

class DashboardWidgetController extends Controller
{
    public function index()
    {
        $widgets = DashboardWidget::orderBy('zone')->orderBy('sort_order')->get();

        return view('admin.dashboard-widgets.index', compact('widgets'));
    }

    public function toggle(DashboardWidget $widget)
    {
        $widget->update(['enabled' => ! $widget->enabled]);

        return back()->with('success', ($widget->enabled ? 'Enabled' : 'Disabled').': '.$widget->name);
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:dashboard_widgets,id',
        ]);

        foreach ($validated['order'] as $position => $id) {
            DashboardWidget::where('id', $id)->update(['sort_order' => ($position + 1) * 10]);
        }

        return response()->json(['success' => true]);
    }
}
