<?php

namespace App\Casts;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;

/**
 * A DB `date` column: the calendar day it names, nothing more.
 *
 *  - reads as midnight of that day in the plant zone, like Laravel's `date`
 *    cast (so `->isPast()`, `->diffInDays()` and friends keep working);
 *  - stores `Y-m-d`, so a time-bearing value never leaves a time behind (SQLite
 *    keeps whatever string it is given);
 *  - serializes as `Y-m-d`. The plain `date` cast serializes midnight in the
 *    plant zone as a UTC instant, which names the previous day east of UTC.
 *
 * `date:Y-m-d` gets the serialization right but reads through `asDateTime()`,
 * so a value assigned with a time keeps it until the model is reloaded.
 */
class CalendarDate implements CastsAttributes, SerializesCastableAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonInterface
    {
        return $value === null ? null : $this->toDate($value)->startOfDay();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null || $value === '' ? null : $this->toDate($value)->toDateString();
    }

    /**
     * By the time this runs Eloquent has already turned the Carbon value into a
     * UTC instant (serializeDate), so the day is read from the stored attribute.
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        $stored = $attributes[$key] ?? null;

        return $stored === null ? null : $this->toDate($stored)->toDateString();
    }

    private function toDate(mixed $value): CarbonInterface
    {
        return match (true) {
            $value instanceof DateTimeInterface => Date::instance($value),
            is_numeric($value) => Date::createFromTimestamp((int) $value, date_default_timezone_get()),
            default => Date::parse((string) $value),
        };
    }
}
