<?php

namespace App\Sync\Shapes;

use App\Models\SerialUnit;
use App\Models\User;
use App\Sync\Shape;

/**
 * Serialised units for the traceability console's browse tab: the two
 * identifiers, what they belong to, and where the unit stands.
 *
 * A window, not the table: a line adds a row per product made, for ever. Units
 * still on the floor always sync; shipped and scrapped ones only while recent.
 * Older ones are found by the console's search, which asks the server.
 *
 * The date is a literal computed per request (a shape's WHERE cannot call
 * now()), like the pallet-movements window.
 */
class SerialUnitsRecentShape extends Shape
{
    /** How long a finished (shipped or scrapped) unit stays in the live list, in days. */
    public const WINDOW_DAYS = 30;

    public function table(): string
    {
        return 'serial_units';
    }

    public function columns(): array
    {
        return ['id', 'serial_no', 'psn', 'work_order_id', 'batch_id', 'carton_id', 'pallet_id', 'material_id', 'status', 'produced_at', 'packed_at', 'shipped_at', 'created_at', 'updated_at'];
    }

    public function where(User $user): ?string
    {
        $since = now()->subDays(self::WINDOW_DAYS)->toDateString();
        $finished = "'".SerialUnit::STATUS_SHIPPED."', '".SerialUnit::STATUS_SCRAPPED."'";

        return "(status NOT IN ({$finished}) OR updated_at >= '{$since}')";
    }
}
