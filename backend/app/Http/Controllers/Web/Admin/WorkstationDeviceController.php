<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\WorkstationDevice;
use Inertia\Inertia;

/**
 * MAIN-side roster of registered workstation clients. Rows live-sync via the
 * `workstation_devices` shape; the page derives online/offline from
 * last_seen_at. Admin can forget (soft-delete) a decommissioned station.
 */
class WorkstationDeviceController extends Controller
{
    public function index()
    {
        return Inertia::render('admin/workstation-devices/Index', [
            'onlineWindowSeconds' => WorkstationDevice::ONLINE_WINDOW_SECONDS,
            'lines' => Line::orderBy('name')->pluck('name', 'id'),
        ]);
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
