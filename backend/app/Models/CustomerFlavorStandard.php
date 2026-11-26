<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerFlavorStandard extends Model
{
    use Auditable, HasFactory, HasTenant;

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_RETIRED = 'RETIRED';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_RETIRED];

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'version',
        'status',
        'customer_id',
        'product_type_id',
        'effective_from',
        'effective_to',
        'moisture_min',
        'moisture_max',
        'sensory_aroma',
        'sensory_taste',
        'sensory_liquor',
        'blend_tolerance',
        'foreign_matter_required',
        'requires_external_lab',
        'required_lab_items',
        'net_weight_min',
        'net_weight_max',
        'packaging_requirement',
        'notes',
        'created_by_id',
        'approved_by_id',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'moisture_min' => 'decimal:3',
            'moisture_max' => 'decimal:3',
            'blend_tolerance' => 'decimal:3',
            'foreign_matter_required' => 'boolean',
            'requires_external_lab' => 'boolean',
            'required_lab_items' => 'array',
            'net_weight_min' => 'decimal:3',
            'net_weight_max' => 'decimal:3',
            'approved_at' => 'datetime',
        ];
    }

    /** 客户要求外检报告至少覆盖的类别/项目（例如 农残、重金属、微生物）。 */
    public function requiredLabItems(): array
    {
        return collect($this->required_lab_items ?? [])
            ->map(fn ($item) => is_string($item) ? trim($item) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * 把客户标准展开成 COA / 检验模板共用的参数结构。
     *
     * 返回项的键与 QualityCheckTemplate::parameters 对齐（name/type/min/max/unit），
     * 这样 COA 快照、详情页和 PDF 不需要分支渲染。
     */
    public function parameters(): array
    {
        $parameters = [];

        if ($this->moisture_min !== null || $this->moisture_max !== null) {
            $parameters[] = [
                'name' => '水分',
                'type' => 'measurement',
                'min' => $this->moisture_min !== null ? (float) $this->moisture_min : null,
                'max' => $this->moisture_max !== null ? (float) $this->moisture_max : null,
                'unit' => '%',
            ];
        }

        foreach ([
            'sensory_aroma' => ['感官-香气', $this->sensory_aroma],
            'sensory_taste' => ['感官-滋味', $this->sensory_taste],
            'sensory_liquor' => ['感官-汤色', $this->sensory_liquor],
        ] as $key => [$label, $description]) {
            if (filled($description)) {
                $parameters[] = [
                    'name' => $label,
                    'type' => 'boolean',
                    'min' => null,
                    'max' => null,
                    'unit' => null,
                    'standard' => $description,
                ];
            }
        }

        if ($this->blend_tolerance !== null) {
            $tolerance = abs((float) $this->blend_tolerance);
            $parameters[] = [
                'name' => '拼配比例偏差',
                'type' => 'measurement',
                'min' => -$tolerance,
                'max' => $tolerance,
                'unit' => '%',
            ];
        }

        if ($this->foreign_matter_required) {
            $parameters[] = [
                'name' => '异物',
                'type' => 'boolean',
                'min' => null,
                'max' => null,
                'unit' => null,
                'standard' => '不得检出',
            ];
        }

        if ($this->net_weight_min !== null || $this->net_weight_max !== null) {
            $parameters[] = [
                'name' => '净含量',
                'type' => 'measurement',
                'min' => $this->net_weight_min !== null ? (float) $this->net_weight_min : null,
                'max' => $this->net_weight_max !== null ? (float) $this->net_weight_max : null,
                'unit' => 'g',
            ];
        }

        return $parameters;
    }

    /**
     * 供 COA 详情/PDF 显示的客户标准摘要。
     */
    public function sensorySummary(): string
    {
        return collect([
            filled($this->sensory_aroma) ? '香气：'.$this->sensory_aroma : null,
            filled($this->sensory_taste) ? '滋味：'.$this->sensory_taste : null,
            filled($this->sensory_liquor) ? '汤色：'.$this->sensory_liquor : null,
        ])->filter()->implode('；');
    }
}
