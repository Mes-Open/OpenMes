<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'days_of_week',
        'line_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'days_of_week' => 'array',
            'is_active'    => 'boolean',
        ];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    /**
     * Returns the shift active right now, optionally filtered by line.
     */
    public static function current(?int $lineId = null): ?self
    {
        $now      = now();
        $dayOfWeek = (int) $now->format('N'); // 1=Mon … 7=Sun
        $time      = $now->format('H:i:s');

        return static::where('is_active', true)
            ->when($lineId, fn($q) => $q->where(fn($q2) =>
                $q2->where('line_id', $lineId)->orWhereNull('line_id')
            ))
            ->get()
            ->first(function (self $shift) use ($dayOfWeek, $time) {
                if (!in_array($dayOfWeek, $shift->days_of_week)) {
                    return false;
                }
                // Handle overnight shifts (e.g. 22:00 – 06:00)
                if ($shift->start_time <= $shift->end_time) {
                    return $time >= $shift->start_time && $time < $shift->end_time;
                }
                return $time >= $shift->start_time || $time < $shift->end_time;
            });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function dayName(int $day): string
    {
        return ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][$day] ?? '?';
    }
}
