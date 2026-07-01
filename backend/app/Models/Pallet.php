<?php

namespace App\Models;

use App\Enums\PalletStatus;
use App\Models\Concerns\HasTenant;
use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Pallet extends Model
{
    use HasFactory, HasTenant;
    use SoftDeletesWithAudit;

    // Quality status (#106) — derived from the pallet's linked quality checks.
    const QUALITY_PENDING = 'pending'; // no quality check linked yet

    const QUALITY_PASS = 'pass';       // every linked check passed

    const QUALITY_FAIL = 'fail';       // at least one linked check failed

    public const QUALITY_STATUSES = [self::QUALITY_PENDING, self::QUALITY_PASS, self::QUALITY_FAIL];

    protected $fillable = [
        'pallet_no',
        'work_order_id',
        'batch_id',
        'qty',
        'status',
        'quality_status',
        'location',
        'erp_reference',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => PalletStatus::class,
            'qty' => 'integer',
            'shipped_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $pallet): void {
            if (empty($pallet->pallet_no)) {
                $pallet->pallet_no = self::nextPalletNo();
            }
        });

        // Stamp the shipped transition exactly once. Later edits to a shipped
        // pallet must not move it into another shift's handover window, so the
        // calculator attributes by shipped_at instead of updated_at.
        static::saving(function (self $pallet): void {
            // Quality ship-gate (#106): an existing pallet can't be shipped until
            // its quality status is "pass" (no/failed quality checks block it).
            if ($pallet->exists
                && $pallet->isDirty('status')
                && $pallet->status === PalletStatus::Shipped
                && $pallet->quality_status !== self::QUALITY_PASS) {
                throw new \DomainException(__(
                    'Cannot ship pallet :no: quality status is ":status" (must be passed).',
                    ['no' => $pallet->pallet_no, 'status' => $pallet->quality_status],
                ));
            }

            if ($pallet->status === PalletStatus::Shipped && $pallet->shipped_at === null) {
                $pallet->shipped_at = now();
            }
        });
    }

    /**
     * Recompute and persist the pallet's quality status from its linked checks.
     * Quiet save — does not re-fire the ship-gate / shipped-at hooks.
     */
    public function recomputeQualityStatus(): void
    {
        $checks = $this->qualityChecks()->get(['all_passed']);

        $status = match (true) {
            $checks->isEmpty() => self::QUALITY_PENDING,
            $checks->contains(fn ($c) => ! $c->all_passed) => self::QUALITY_FAIL,
            default => self::QUALITY_PASS,
        };

        if ($this->quality_status !== $status) {
            $this->quality_status = $status;
            $this->saveQuietly();
        }
    }

    /**
     * Draw the next value and format it as PAL-000001.
     *
     * On Postgres this uses a dedicated sequence, which guarantees uniqueness
     * across tenants and concurrent writers. On other drivers (e.g. sqlite in
     * tests) the sequence does not exist, so we derive the next number from the
     * highest existing pallet_no — sufficient for the single-threaded test path.
     */
    public static function nextPalletNo(): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $next = (int) DB::selectOne("SELECT nextval('pallets_pallet_no_seq') AS n")->n;
        } else {
            $max = static::withoutGlobalScopes()
                ->where('pallet_no', 'like', 'PAL-%')
                ->selectRaw('MAX(CAST(SUBSTR(pallet_no, 5) AS INTEGER)) AS n')
                ->value('n');
            $next = ((int) $max) + 1;
        }

        return 'PAL-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /** The batch this pallet holds (one batch per pallet; nullable for legacy pallets). */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function scanLogs(): HasMany
    {
        return $this->hasMany(PackagingScanLog::class);
    }

    public function qualityChecks(): HasMany
    {
        return $this->hasMany(QualityCheck::class);
    }

    /**
     * Stock movements booked against this pallet - the milestone backflush
     * consumption declared when the pallet was created. Linked via the
     * StockMovement source_type/source_id pair, so each consumption is auditable
     * and traceable to its pallet.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'source_id')
            ->where('source_type', StockMovement::SOURCE_PALLET);
    }

    public function isOpen(): bool
    {
        return $this->status === PalletStatus::Open;
    }
}
