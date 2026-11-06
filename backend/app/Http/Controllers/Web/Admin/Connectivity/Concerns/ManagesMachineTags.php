<?php

namespace App\Http\Controllers\Web\Admin\Connectivity\Concerns;

use App\Models\MachineConnection;
use App\Models\MachineTag;
use App\Models\Workstation;
use Illuminate\Support\Collection;

/**
 * Shared tag → signal management for protocol connection controllers
 * (Modbus, OPC UA). Tags use the protocol-agnostic MachineTag model; the only
 * protocol-specific bit is register_type (Modbus-only), which the caller passes
 * in. The matching frontend lives in resources/js/Pages/admin/connectivity/TagManager.jsx.
 */
trait ManagesMachineTags
{
    /** Workstation dropdown options for tag assignment. */
    protected function workstationOptions(): Collection
    {
        return Workstation::with('line:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn ($w) => [
                'id'   => $w->id,
                'name' => $w->name,
                'line' => $w->line?->name,
            ])
            ->values();
    }

    /** Flatten a MachineTag for the Inertia view. */
    protected function mapTag(MachineTag $t): array
    {
        return [
            'id'             => $t->id,
            'name'           => $t->name,
            'address'        => $t->address,
            'signal_type'    => $t->signal_type,
            'data_type'      => $t->data_type,
            'register_type'  => $t->register_type,
            'workstation'    => $t->workstation?->name,
            'workstation_id' => $t->workstation_id,
            'transform'      => $t->transform,
            'is_active'      => $t->is_active,
        ];
    }

    /**
     * Build the transform payload from validated tag input: a discrete value map
     * ("1=RUNNING,2=IDLE") and/or a numeric scale. Returns null when empty.
     */
    protected function buildTagTransform(array $data): ?array
    {
        $transform = [];

        if (! empty($data['value_map'])) {
            $map = [];
            foreach (explode(',', $data['value_map']) as $pair) {
                [$k, $v] = array_pad(explode('=', trim($pair), 2), 2, null);
                if ($k !== null && $v !== null) {
                    $map[trim($k)] = trim($v);
                }
            }
            if ($map) {
                $transform['value_map'] = $map;
            }
        }

        if (isset($data['scale'])) {
            $transform['scale'] = (float) $data['scale'];
        }

        return $transform ?: null;
    }

    /** Persist a tag. $registerType is Modbus-only (null for OPC UA). */
    protected function createTag(MachineConnection $connection, array $data, ?string $registerType): MachineTag
    {
        return MachineTag::create([
            'machine_connection_id' => $connection->id,
            'workstation_id'        => $data['workstation_id'] ?? null,
            'name'                  => $data['name'],
            'address'               => $data['address'],
            'signal_type'           => $data['signal_type'],
            'data_type'             => $data['data_type'],
            'register_type'         => $registerType,
            'transform'             => $this->buildTagTransform($data),
        ]);
    }

    public function destroyTag(MachineConnection $machineConnection, MachineTag $tag)
    {
        abort_unless($tag->machine_connection_id === $machineConnection->id, 404);
        $tag->delete();

        return back()->with('success', __('Tag removed.'));
    }
}
