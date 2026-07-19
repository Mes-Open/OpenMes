<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts a `days_of_week` JSON column to a normalized array of ISO weekday
 * integers (1=Mon .. 7=Sun) on both read and write.
 *
 * The canonical contract across the app is integer ISO days (API validation,
 * migration default, `Shift::current()`). Some data — notably the demo
 * seeder — historically stored day-name strings ("monday", "Mon", …), which
 * silently broke integer comparisons. Normalizing in the cast means every
 * consumer agrees regardless of how the row was written, and legacy string
 * rows already in the database read back as integers.
 */
class DaysOfWeek implements CastsAttributes
{
    private const NAME_MAP = [
        'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4,
        'fri' => 5, 'sat' => 6, 'sun' => 7,
    ];

    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        $decoded = is_array($value) ? $value : json_decode($value, true);

        return is_array($decoded) ? $this->normalize($decoded) : [];
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        $decoded = is_array($value) ? $value : json_decode($value, true);

        return [$key => json_encode(is_array($decoded) ? $this->normalize($decoded) : [])];
    }

    /**
     * @return array<int, int> sorted, unique ISO weekday integers
     */
    private function normalize(array $days): array
    {
        $out = [];
        foreach ($days as $day) {
            $n = $this->normalizeOne($day);
            if ($n !== null) {
                $out[$n] = $n;
            }
        }

        $values = array_values($out);
        sort($values);

        return $values;
    }

    private function normalizeOne(mixed $day): ?int
    {
        if (is_int($day) || is_numeric($day)) {
            $n = (int) $day;
            if ($n === 0) {
                return 7; // tolerate 0 = Sunday
            }

            return ($n >= 1 && $n <= 7) ? $n : null;
        }

        if (is_string($day)) {
            return self::NAME_MAP[strtolower(substr(trim($day), 0, 3))] ?? null;
        }

        return null;
    }
}
