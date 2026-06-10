<?php

namespace App\Models;

use App\Enums\PalletStatus;
use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Pallet extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
        'pallet_no',
        'work_order_id',
        'qty',
        'status',
        'location',
        'erp_reference',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => PalletStatus::class,
            'qty' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $pallet): void {
            if (empty($pallet->pallet_no)) {
                $pallet->pallet_no = self::nextPalletNo();
            }
        });
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

    public function scanLogs(): HasMany
    {
        return $this->hasMany(PackagingScanLog::class);
    }

    public function isOpen(): bool
    {
        return $this->status === PalletStatus::Open;
    }
}
