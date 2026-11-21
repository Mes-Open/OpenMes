<?php

namespace App\Sync\Shapes;

use App\Models\User;
use App\Sync\Shape;

/**
 * The dock's scan log (#400), recent window only — the log is append-only and
 * grows by one row per scan. The full history stays readable per shipment
 * through the API.
 */
class ShipmentScansRecentShape extends Shape
{
    public const WINDOW_DAYS = 30;

    public function table(): string
    {
        return 'shipment_scans';
    }

    public function columns(): array
    {
        return [
            'id', 'shipment_id', 'scanned_code', 'unit_type', 'unit_id', 'shipment_item_id',
            'result', 'message', 'user_id', 'workstation_id', 'scanned_at', 'created_at', 'updated_at',
        ];
    }

    public function where(User $user): ?string
    {
        $since = now()->subDays(self::WINDOW_DAYS)->toDateString();

        return "scanned_at >= '{$since}'";
    }

    public function tabs(): array
    {
        return ['packaging'];
    }

    public function roles(): array
    {
        return ['Operator'];
    }
}
