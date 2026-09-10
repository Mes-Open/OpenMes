<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Models\Concerns\SoftDeletesWithAudit;
use App\Services\Lot\LotPatternFormatter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Serial-number sequence — parallel to LotSequence (#290). Kept as its own
 * model/table rather than reusing LotSequence: a lot number identifies a
 * production run, a serial number identifies one physical piece, and the two
 * may need independent pattern/reset configuration per product type even
 * though the rendering engine (LotPatternFormatter) is shared as-is — it has
 * no lot-specific behavior, it just renders tokens.
 */
class SerialSequence extends Model
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
     * Atomically generate the next serial number. Same locking strategy as
     * LotSequence::generateNext() — SELECT FOR UPDATE to prevent race
     * conditions; resets the counter at period boundaries when configured.
     */
    public function generateNext(): string
    {
        return DB::transaction(function () {
            $seq = DB::table('serial_sequences')
                ->where('id', $this->id)
                ->lockForUpdate()
                ->first();

            $resetKey = $this->currentResetKey();
            $number = ($resetKey !== null && $seq->last_reset_key !== $resetKey)
                ? 1
                : $seq->next_number;

            DB::table('serial_sequences')
                ->where('id', $this->id)
                ->update([
                    'next_number' => $number + 1,
                    'last_reset_key' => $resetKey,
                    'updated_at' => now(),
                ]);

            return $this->formatSerial($number);
        });
    }

    /**
     * Preview the next serial number without incrementing.
     */
    public function previewNext(): string
    {
        $resetKey = $this->currentResetKey();
        $number = ($resetKey !== null && $this->last_reset_key !== $resetKey)
            ? 1
            : $this->next_number;

        return $this->formatSerial($number);
    }

    /**
     * Period key for the configured reset_period, or null when reset is off.
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
     * Format a serial number from the sequence number. Same pattern/legacy
     * split as LotSequence::formatLot().
     */
    private function formatSerial(int $number): string
    {
        if ($this->pattern) {
            return (new LotPatternFormatter)->format(
                $this->pattern,
                $number,
                $this->pad_size,
                $this->productType?->code,
                now(),
            );
        }

        $padded = str_pad((string) $number, $this->pad_size, '0', STR_PAD_LEFT);

        $parts = [$this->prefix];

        if ($this->year_prefix) {
            $parts[] = now()->format('Y');
        }

        $parts[] = $padded;

        $serial = implode('-', array_filter($parts, fn ($p) => $p !== ''));

        if ($this->suffix) {
            $serial .= '-'.$this->suffix;
        }

        return $serial;
    }
}
