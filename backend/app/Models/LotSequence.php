<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Models\Concerns\SoftDeletesWithAudit;
use App\Services\Lot\LotPatternFormatter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class LotSequence extends Model
{
    use HasFactory, HasTenant;
    use SoftDeletesWithAudit;

    public const RESET_PERIODS = ['none', 'yearly', 'monthly', 'daily', 'hourly'];

    protected $fillable = [
        'name',
        'product_type_id',
        'prefix',
        'suffix',
        'pattern',
        'next_number',
        'pad_size',
        'year_prefix',
        'reset_period',
        'last_reset_key',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'next_number' => 'integer',
            'pad_size' => 'integer',
            'year_prefix' => 'boolean',
        ];
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    /**
     * Atomically generate the next LOT number.
     * Uses SELECT FOR UPDATE to prevent race conditions; when a reset period
     * is configured the counter restarts at 1 on period boundaries.
     */
    public function generateNext(): string
    {
        return DB::transaction(function () {
            $seq = DB::table('lot_sequences')
                ->where('id', $this->id)
                ->lockForUpdate()
                ->first();

            $resetKey = $this->currentResetKey();
            $number = ($resetKey !== null && $seq->last_reset_key !== $resetKey)
                ? 1
                : $seq->next_number;

            DB::table('lot_sequences')
                ->where('id', $this->id)
                ->update([
                    'next_number' => $number + 1,
                    'last_reset_key' => $resetKey,
                    'updated_at' => now(),
                ]);

            return $this->formatLot($number);
        });
    }

    /**
     * Preview the next LOT number without incrementing.
     */
    public function previewNext(): string
    {
        $resetKey = $this->currentResetKey();
        $number = ($resetKey !== null && $this->last_reset_key !== $resetKey)
            ? 1
            : $this->next_number;

        return $this->formatLot($number);
    }

    /**
     * Period key for the configured reset_period, or null when reset is off.
     * E.g. daily → "2026-06-06", hourly → "2026-06-06-14".
     */
    public function currentResetKey(): ?string
    {
        return match ($this->reset_period) {
            'yearly' => now()->format('Y'),
            'monthly' => now()->format('Y-m'),
            'daily' => now()->format('Y-m-d'),
            'hourly' => now()->format('Y-m-d-H'),
            default => null,
        };
    }

    /**
     * Format a LOT number from sequence number.
     */
    private function formatLot(int $number): string
    {
        // Pattern mode: token-based template (e.g. "test-[date]-[seq]-[hour]")
        if ($this->pattern) {
            return (new LotPatternFormatter)->format(
                $this->pattern,
                $number,
                $this->pad_size,
                $this->productType?->code,
                now(),
            );
        }

        // Legacy mode: prefix [- year] - number [- suffix]
        $padded = str_pad($number, $this->pad_size, '0', STR_PAD_LEFT);

        $parts = [$this->prefix];

        if ($this->year_prefix) {
            $parts[] = now()->format('Y');
        }

        $parts[] = $padded;

        $lot = implode('-', $parts);

        if ($this->suffix) {
            $lot .= '-'.$this->suffix;
        }

        return $lot;
    }
}
