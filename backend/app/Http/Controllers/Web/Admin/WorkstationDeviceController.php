<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateWorkstationDeviceRequest;
use App\Models\Line;
use App\Models\WorkstationDevice;
use Inertia\Inertia;

/**
 * MAIN-side roster of stations: operator panels (browser) and OpenMES
 * Workstation clients. Rows live-sync via the `workstation_devices` shape; the
 * page derives active/inactive from the reported status and last_seen_at.
 * Admin can rename a station and forget (soft-delete) a decommissioned one.
 */
class WorkstationDeviceController extends Controller
{
    public function index()
    {
        return Inertia::render('admin/workstation-devices/Index', [
            'onlineWindowSeconds' => WorkstationDevice::ONLINE_WINDOW_SECONDS,
            'lines' => Line::orderBy('name')->pluck('name', 'id'),
            'panelPruneDays' => (int) config('openmmes.workstation_panel_prune_days'),
        ]);
    }

    public function update(UpdateWorkstationDeviceRequest $request, WorkstationDevice $workstationDevice)
    {
        $workstationDevice->update(['name' => $request->validated('name')]);

        return redirect()->route('admin.workstation-devices.index')
            ->with('success', __('Workstation device renamed.'));
    }

    /**
     * Forget a station. Soft delete keeps history/audit; the device can
     * re-register later with the same uuid (partial-unique index).
     */
    public function destroy(WorkstationDevice $workstationDevice)
    {
        $workstationDevice->delete();

        return redirect()->route('admin.workstation-devices.index')
            ->with('success', __('Workstation device removed.'));
    }
}
