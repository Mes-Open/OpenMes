<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabelTemplate extends Model
{
    use HasFactory, HasTenant;
    use SoftDeletesWithAudit;

    const TYPE_WORK_ORDER = 'work_order';

    const TYPE_FINISHED_GOODS = 'finished_goods';

    const TYPE_WORKSTATION_STEP = 'workstation_step';

    const TYPE_PALLET = 'pallet';

    const TYPE_SERIAL_UNIT = 'serial_unit';

    const TYPE_CARTON = 'carton';

    /** A received material lot after incoming inspection (the IQC label). */
    const TYPE_MATERIAL_LOT = 'material_lot';

    const TYPES = [
        self::TYPE_WORK_ORDER => 'Work Order',
        self::TYPE_FINISHED_GOODS => 'Finished Goods',
        self::TYPE_WORKSTATION_STEP => 'Workstation Step',
        self::TYPE_PALLET => 'Pallet',
        self::TYPE_SERIAL_UNIT => 'Serial Unit (SN)',
        self::TYPE_CARTON => 'Carton (units list)',
        self::TYPE_MATERIAL_LOT => 'Material lot (IQC)',
    ];

    const SIZES = [
        '100x50' => '100 × 50 mm (standard)',
        '80x40' => '80 × 40 mm (small)',
        '62x29' => '62 × 29 mm (Brother DK)',
        '100x100' => '100 × 100 mm (square)',
        '150x100' => '150 × 100 mm (large)',
    ];

    const BARCODE_FORMATS = [
        'code128' => 'CODE 128',
        'code39' => 'CODE 39',
        'ean13' => 'EAN-13',
    ];

    const AVAILABLE_FIELDS = [
        'wo_number' => 'Work order number',
        'pallet_no' => 'Pallet number',
        'carton_no' => 'Carton number',
        'serial_no' => 'Serial number (SN)',
        'psn' => 'Process serial (PSN)',
        'product' => 'Product name',
        'quantity' => 'Quantity',
        'barcode' => 'Barcode (1D)',
        'qr' => 'QR code',
        'lot' => 'Lot number',
        'material' => 'Material',
        'status' => 'Inspection status',
        'supplier_lot' => 'Supplier lot',
        'location' => 'Location',
        'prod_date' => 'Production date',
    ];

    protected $table = 'label_templates';

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'size',
        'fields_config',
        'barcode_format',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fields_config' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The fields a label of this type can show, in the form's order: what its
     * layout actually prints, so a template does not offer a pallet number on
     * a unit label.
     *
     * @return array<int, string>
     */
    public static function fieldsForType(string $type): array
    {
        $known = array_keys(self::defaultFieldsFor($type));

        return array_values(array_filter(array_keys(self::AVAILABLE_FIELDS), fn ($k) => in_array($k, $known, true)));
    }

    public static function defaultFieldsFor(string $type): array
    {
        return match ($type) {
            self::TYPE_WORK_ORDER => [
                'wo_number' => true,
                'product' => true,
                'quantity' => true,
                'barcode' => true,
                'qr' => false,
                'lot' => false,
                'prod_date' => false,
            ],
            self::TYPE_FINISHED_GOODS => [
                'wo_number' => true,
                'product' => true,
                'quantity' => true,
                'barcode' => true,
                'qr' => true,
                'lot' => true,
                'prod_date' => true,
            ],
            self::TYPE_WORKSTATION_STEP => [
                'wo_number' => true,
                'product' => true,
                'quantity' => false,
                'barcode' => true,
                'qr' => false,
                'lot' => false,
                'prod_date' => false,
            ],
            self::TYPE_PALLET => [
                'pallet_no' => true,
                'wo_number' => true,
                'product' => true,
                'quantity' => true,
                'barcode' => true,
                'qr' => true,
                'lot' => false,
                'location' => true,
                'prod_date' => true,
            ],
            self::TYPE_SERIAL_UNIT => [
                'serial_no' => true,
                'psn' => true,
                'wo_number' => true,
                'product' => true,
                'quantity' => false,
                'barcode' => true,
                'qr' => true,
                'lot' => false,
                'location' => false,
                'prod_date' => true,
            ],
            self::TYPE_CARTON => [
                'carton_no' => true,
                'wo_number' => true,
                'product' => true,
                'quantity' => true,
                'barcode' => true,
                'qr' => true,
                'lot' => false,
                'prod_date' => true,
            ],
            self::TYPE_MATERIAL_LOT => [
                'lot' => true,
                'material' => true,
                'quantity' => true,
                'status' => true,
                'supplier_lot' => true,
                'barcode' => true,
                'qr' => true,
                'prod_date' => true,
            ],
            default => array_fill_keys(array_keys(self::AVAILABLE_FIELDS), false),
        };
    }

    public function hasField(string $key): bool
    {
        return (bool) ($this->fields_config[$key] ?? false);
    }

    public function widthMm(): int
    {
        return (int) explode('x', $this->size)[0];
    }

    public function heightMm(): int
    {
        return (int) explode('x', $this->size)[1];
    }

    public function scopeForType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function defaultFor(string $type): ?self
    {
        return self::query()
            ->where('type', $type)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first()
            ?? self::query()->where('type', $type)->where('is_active', true)->first();
    }
}
