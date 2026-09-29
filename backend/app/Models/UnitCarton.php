<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A box of serialised units: opened at packing, filled by scanning units in,
 * closed with a label that lists them, then put on a pallet. Never deleted.
 */
class UnitCarton extends Model
{
    use HasTenant;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'carton_no',
        'work_order_id',
        'pallet_id',
        'status',
        'qty',
        'closed_at',
        'closed_by_id',
        'created_by_id',
        'tenant_id',
        'active_by_id',
        'active_workstation_id',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'qty' => 'integer',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $carton): void {
            if (empty($carton->carton_no)) {
                $carton->carton_no = self::nextCartonNo();
            }
        });
    }

    /**
     * CTN-000001, CTN-000002… from the highest number in use. Cartons are
     * opened by one packing station at a time, so a max+1 under the creating
     * transaction is enough; the unique index catches the rest.
     */
    /**
     * The next carton number. Several benches open cartons at once, so on
     * Postgres it comes from a sequence (like pallet numbers) rather than
     * MAX+1, which two stations can compute identically; other drivers (sqlite
     * in tests) derive it from the table.
     */
    public static function nextCartonNo(): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $next = (int) DB::selectOne("SELECT nextval('unit_cartons_carton_no_seq') AS n")->n;
        } else {
            $max = static::withoutGlobalScopes()
                ->where('carton_no', 'like', 'CTN-%')
                ->selectRaw('MAX(CAST(SUBSTR(carton_no, 5) AS INTEGER)) AS n')
                ->value('n');
            $next = ((int) $max) + 1;
        }

        return 'CTN-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function units(): HasMany
    {
        return $this->hasMany(SerialUnit::class, 'carton_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function pallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_id');
    }

    /** The operator and bench currently filling this one - shared state, so every packing screen agrees. */
    public function activeBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'active_by_id');
    }

    public function activeWorkstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class, 'active_workstation_id');
    }
}
