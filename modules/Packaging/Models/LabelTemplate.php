<?php

namespace Modules\Packaging\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabelTemplate extends Model
{
    use HasFactory, HasTenant;

    const TYPE_WORK_ORDER = 'work_order';
    const TYPE_FINISHED_GOODS = 'finished_goods';
    const TYPE_WORKSTATION_STEP = 'workstation_step';

    const TYPES = [
        self::TYPE_WORK_ORDER => 'Work Order',
        self::TYPE_FINISHED_GOODS => 'Finished Goods',
        self::TYPE_WORKSTATION_STEP => 'Workstation Step',
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
        'product' => 'Product name',
        'quantity' => 'Quantity',
        'barcode' => 'Barcode (1D)',
        'qr' => 'QR code',
        'logo' => 'Logo',
        'lot' => 'Lot number',
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

    public static function defaultFieldsFor(string $type): array
    {
        return match ($type) {
            self::TYPE_WORK_ORDER => [
                'wo_number' => true,
                'product' => true,
                'quantity' => true,
                'barcode' => true,
                'qr' => false,
                'logo' => false,
                'lot' => false,
                'prod_date' => false,
            ],
            self::TYPE_FINISHED_GOODS => [
                'wo_number' => true,
                'product' => true,
                'quantity' => true,
                'barcode' => true,
                'qr' => true,
                'logo' => false,
                'lot' => true,
                'prod_date' => true,
            ],
            self::TYPE_WORKSTATION_STEP => [
                'wo_number' => true,
                'product' => true,
                'quantity' => false,
                'barcode' => true,
                'qr' => false,
                'logo' => false,
                'lot' => false,
                'prod_date' => false,
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
