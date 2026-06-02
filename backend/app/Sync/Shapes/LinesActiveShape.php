<?php

namespace App\Sync\Shapes;

use App\Models\User;
use App\Sync\Shape;

/**
 * Active production lines — used as a lookup table by other shapes (joining
 * work_order.line_id → line.name on the client) and by the dashboard's line
 * filter dropdown.
 */
class LinesActiveShape extends Shape
{
    public function table(): string
    {
        return 'lines';
    }

    public function columns(): array
    {
        return ['id', 'code', 'name', 'is_active', 'area_id', 'division_id'];
    }

    public function where(User $user): ?string
    {
        return 'is_active = true';
    }
}
