<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved column-mapping profile: file header → importer field, per entity, so a
 * user re-importing the same ERP export does not map thirty columns again.
 */
class CsvImportMapping extends Model
{
    use SoftDeletesWithAudit;

    protected $fillable = [
        'name',
        'entity',
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
     * The header → field map this profile stores, whichever writer produced it:
     * the unified importer and the web importer store `column_mappings`; the
     * mobile/API importer stores `columns` as a list of {csv_column, system_field}.
     *
     * @return array<string, string>
     */
    public function columnMappings(): array
    {
        $config = $this->mapping_config ?? [];

        if (isset($config['column_mappings']) && is_array($config['column_mappings'])) {
            return $config['column_mappings'];
        }

        // API shape: columns => { field => {csv_column, transform?, default?} }.
        $out = [];
        foreach ($config['columns'] ?? [] as $field => $col) {
            if (is_array($col) && ! empty($col['csv_column'])) {
                $out[$col['csv_column']] = (string) $field;
            }
        }

        return $out;
    }

    /**
     * The available system fields that CSV columns can be mapped to.
     *
     * @deprecated Only the mobile/API work-order importer still reads this; the
     *             unified importer describes its fields per entity in App\Import.
     */
    public static function systemFields(): array
    {
        return [
            'order_no' => 'Order Number',
            'product_name' => 'Product Name',
            'quantity' => 'Quantity (Planned)',
            'line_code' => 'Line Code',
            'product_type_code' => 'Product Type Code',
            'priority' => 'Priority',
            'due_date' => 'Due Date',
            'description' => 'Description',
        ];
    }
}
