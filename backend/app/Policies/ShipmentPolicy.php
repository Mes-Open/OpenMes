<?php

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;

/**
 * Shipments (#400): supervisors plan and manage loads; the dock (operators)
 * scans, undoes and confirms a complete load. A short load is a supervisor's
 * decision.
 */
class ShipmentPolicy
{
    private const DOCK = ['Operator', 'Supervisor', 'Admin'];

    private const MANAGERS = ['Supervisor', 'Admin'];

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(self::DOCK);
    }

    public function view(User $user, Shipment $shipment): bool
    {
        return $user->hasAnyRole(self::DOCK);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(self::MANAGERS);
    }

    public function update(User $user, Shipment $shipment): bool
    {
        return $user->hasAnyRole(self::MANAGERS);
    }

    public function delete(User $user, Shipment $shipment): bool
    {
        return $user->hasAnyRole(self::MANAGERS);
    }

    public function cancel(User $user, Shipment $shipment): bool
    {
        return $user->hasAnyRole(self::MANAGERS);
    }

    /** Scan onto the truck and undo a scan. */
    public function load(User $user, Shipment $shipment): bool
    {
        return $user->hasAnyRole(self::DOCK);
    }

    /** Confirm a complete load. */
    public function confirm(User $user, Shipment $shipment): bool
    {
        return $user->hasAnyRole(self::DOCK);
    }

    /** Confirm a load with units missing. */
    public function confirmShort(User $user, Shipment $shipment): bool
    {
        return $user->hasAnyRole(self::MANAGERS);
    }
}
