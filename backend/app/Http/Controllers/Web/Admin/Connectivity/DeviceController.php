<?php

namespace App\Http\Controllers\Web\Admin\Connectivity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\GeneratePairingCodeRequest;
use App\Models\DevicePairingCode;
use App\Models\Line;
use App\Models\MachineConnection;
use App\Models\Workstation;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Admin management of self-enrolled HTTP sensors: generate one-time pairing
 * codes, see the enrolled devices and their token status, and revoke either.
 * Deleting a device here is the ONLY way to cut off its token. Admin-only via
 * the route's role middleware; every query is tenant-scoped by the session.
 */
class DeviceController extends Controller
{
    public function index(Request $request)
    {
        $devices = MachineConnection::where('protocol', MachineConnection::PROTOCOL_REST)
            ->with(['deviceToken', 'line'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (MachineConnection $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'mac_address' => $c->mac_address,
                'line' => $c->line?->name,
                'is_active' => $c->is_active,
                'status' => $c->status,
                'status_color' => $c->statusColor(),
                'messages_received' => $c->messages_received,
                'last_connected_at' => $c->last_connected_at?->diffForHumans(),
                'token' => $c->deviceToken ? [
                    'prefix' => $c->deviceToken->token_prefix,
                    'is_active' => $c->deviceToken->is_active,
                    'last_used_at' => $c->deviceToken->last_used_at?->diffForHumans(),
                ] : null,
            ]);

        $pendingCodes = DevicePairingCode::whereNull('used_at')
            ->where('is_active', true)
            ->with(['line', 'workstation'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (DevicePairingCode $code) => [
                'id' => $code->id,
                'name' => $code->name,
                'prefix' => $code->code_prefix,
                'line' => $code->line?->name,
                'workstation' => $code->workstation?->name,
                'expires_at' => $code->expires_at?->toIso8601String(),
                'is_expired' => $code->expires_at !== null && $code->expires_at->isPast(),
            ]);

        return Inertia::render('admin/connectivity/devices/Index', [
            'devices' => $devices,
            'pendingCodes' => $pendingCodes,
            'lines' => Line::orderBy('name')->get(['id', 'name']),
            'workstations' => Workstation::where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'line_id'])
                ->map(fn ($w) => ['id' => $w->id, 'name' => $w->name, 'line_id' => $w->line_id])
                ->values(),
            // The freshly generated plaintext, surfaced once right after generation.
            'generatedCode' => $request->session()->get('generated_code'),
        ]);
    }

    public function generatePairingCode(GeneratePairingCodeRequest $request)
    {
        $data = $request->validated();

        [$code, $plaintext] = DevicePairingCode::issue([
            'name' => $data['name'],
            'line_id' => $data['line_id'] ?? null,
            'workstation_id' => $data['workstation_id'] ?? null,
        ]);

        return redirect()
            ->route('admin.connectivity.devices.index')
            ->with('generated_code', [
                'plaintext' => $plaintext,
                'name' => $code->name,
                'expires_at' => $code->expires_at?->toIso8601String(),
            ])
            ->with('success', __('Pairing code generated.'));
    }

    public function revokePairingCode(DevicePairingCode $pairingCode)
    {
        $pairingCode->delete();

        return redirect()
            ->route('admin.connectivity.devices.index')
            ->with('success', __('Pairing code revoked.'));
    }

    public function destroy(MachineConnection $device)
    {
        abort_unless($device->protocol === MachineConnection::PROTOCOL_REST, 404);

        // Soft delete cascades to the device token (softDeleteCascades), so the
        // sensor's next pulse is rejected — this is the sole revoke path.
        $device->delete();

        return redirect()
            ->route('admin.connectivity.devices.index')
            ->with('success', __('Device removed.'));
    }
}
