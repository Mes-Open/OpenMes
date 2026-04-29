<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class LotSequence extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
        'name',
        'product_type_id',
        'prefix',
        'suffix',
        'next_number',
        'pad_size',
        'year_prefix',
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
     * Uses SELECT FOR UPDATE to prevent race conditions.
     */
    public function generateNext(): string
    {
        return DB::transaction(function () {
            $seq = DB::table('lot_sequences')
                ->where('id', $this->id)
                ->lockForUpdate()
                ->first();

            $number = $seq->next_number;

            DB::table('lot_sequences')
                ->where('id', $this->id)
                ->update(['next_number' => $number + 1, 'updated_at' => now()]);

            return $this->formatLot($number);
        });
    }

    /**
     * Preview the next LOT number without incrementing.
     */
    public function previewNext(): string
    {
        return $this->formatLot($this->next_number);
    }

    /**
     * Format a LOT number from sequence number.
     */
    private function formatLot(int $number): string
    {
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
