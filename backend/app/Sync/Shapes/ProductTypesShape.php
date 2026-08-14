<?php

namespace App\Sync\Shapes;

use App\Models\User;
use App\Sync\Shape;

/**
 * All product types (active + inactive) for the admin CRUD list. Live-syncs so
 * create/edit/delete reflect in the list without a manual refresh.
 */
class ProductTypesShape extends Shape
{
    public function table(): string
    {
        return 'product_types';
    }

    public function columns(): array
    {
        return [
            'id',
            'code',
            'name',
            'description',
            // ERP classification + source identity (#212).
            'category',
            'external_code',
            'external_system',
            'unit_of_measure',
            'is_active',
            'custom_fields',
            'created_at',
            'updated_at',
        ];
    }

    public function where(User $user): ?string
    {
        return null; // admin sees all
    }
}
