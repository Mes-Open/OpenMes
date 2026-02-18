<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CsvImportMapping extends Model
{
    protected $fillable = [
        'name',
        'user_id',
        'mapping_config',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'mapping_config' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The available system fields that CSV columns can be mapped to.
     */
    public static function systemFields(): array
    {
        return [
            'order_no'           => 'Order Number',
            'product_name'       => 'Product Name',
            'quantity'           => 'Quantity (Planned)',
            'line_code'          => 'Line Code',
            'product_type_code'  => 'Product Type Code',
            'priority'           => 'Priority',
            'due_date'           => 'Due Date',
            'description'        => 'Description',
        ];
    }
}
