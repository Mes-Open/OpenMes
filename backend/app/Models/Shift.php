<?php

namespace App\Models;

use App\Models\Concerns\HasCustomFields;
use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasCustomFields, HasFactory;
    use SoftDeletesWithAudit;

    protected $fillable = [
        'name',
        'code',
        'start_time',
        'end_time',
        'days_of_week',
        'line_id',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'days_of_week' => \App\Casts\DaysOfWeek::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // A saved shift may now cover other lines; and refresh() reloads
        // attributes without saving, so it is cleared there too.
        static::saved(function (self $shift) {
            $shift->coverage = null;
        });
    }

    public function refresh()
    {
        $this->coverage = null;

        return parent::refresh();
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    /** @var array{key: string, line_ids: list<int>|null}|null */
    private ?array $coverage = null;

    /**
     * Which lines this shift runs on, and what it is set for.
     *
     * Core knows two answers: a shift with a `line_id` runs on that line, one
     * without runs on every line. An installed module may narrow a shift with
     * no line to a group of lines through the `shifts.coverage` filter — the
     * multi-site module gives each plant its own shifts that way. `key` names
     * the group: shifts are only checked for overlap against others with the
     * same key.
     *
     * Remembered per instance: shift lists are walked once per line and day.
     *
     * @return array{key: string, line_ids: list<int>|null}
     */
    public function coverage(): array
    {
        if ($this->coverage !== null) {
            return $this->coverage;
        }

        $default = $this->line_id !== null
            ? ['key' => 'line:'.$this->line_id, 'line_ids' => [(int) $this->line_id]]
            : ['key' => 'all', 'line_ids' => null];

        $coverage = app(\App\Extension\FilterRegistry::class)->filter('shifts.coverage', $default, ['shift' => $this]);

        if (! is_array($coverage) || ! isset($coverage['key']) || ! array_key_exists('line_ids', $coverage)) {
            return $this->coverage = $default;
        }

        // A module may hand back a Collection (`$site->lines->pluck('id')`);
        // anything that is not a list of ids keeps the shift's own coverage
        // rather than breaking every shift lookup in the request.
        $lines = $coverage['line_ids'];
        if ($lines instanceof \Illuminate\Contracts\Support\Arrayable) {
            $lines = $lines->toArray();
        }
        if ($lines !== null && ! is_array($lines)) {
            return $this->coverage = $default;
        }

        return $this->coverage = [
            'key' => (string) $coverage['key'],
            'line_ids' => $lines === null ? null : array_values(array_map('intval', $lines)),
        ];
    }

    /**
     * Fired after a shift is saved — from the admin screens and the API alike —
     * inside the save's transaction, before the overlap check, which may still
     * roll it back. Listeners write to the database only; anything outside it
     * belongs on ResourceChanged, fired once the save is committed.
     */
    public const PERSIST_HOOK = 'persist.admin.shifts';

    /**
     * Does another active shift with the same coverage (the same line, every
     * line, or the same group a module defined) overlap this one's times?
     * Shifts of different coverage may overlap — see effectiveOn().
     */
    public function overlapsAnotherActive(): bool
    {
        $key = $this->coverage()['key'];

        return static::where('is_active', true)->whereKeyNot($this->getKey())->get()
            ->contains(fn (self $other) => $other->coverage()['key'] === $key && $this->clockOverlaps($other));
    }

    /** The lines this shift runs on; null = every line. */
    public function lineIds(): ?array
    {
        return $this->coverage()['line_ids'];
    }

    /**
     * Does this shift run on the line? A null line asks about the plant as a
     * whole, where every shift counts. With `$plantWide` false a shift that runs
     * on every line does not count — only one set for this line (or its group).
     */
    public function runsOnLine(?int $lineId, bool $plantWide = true): bool
    {
        if ($lineId === null) {
            return true;
        }

        $lines = $this->lineIds();

        return $lines === null ? $plantWide : in_array($lineId, $lines, true);
    }

    /**
     * How narrowly this shift is set: 2 for one line, 1 for a group of lines a
     * module defined, 0 for every line. When two shifts that may overlap (they
     * have different coverage) both run on a line, the narrower one is that
     * line's shift — a plant's own morning shift wins over a global one.
     */
    public function specificity(): int
    {
        $coverage = $this->coverage();

        if ($coverage['line_ids'] === null) {
            return 0;
        }

        // Read from the coverage, not line_id: a module may widen a shift that
        // has a line to a group, and it then ranks as that group's shift.
        return str_starts_with($coverage['key'], 'line:') ? 2 : 1;
    }

    /** Does the shift run on this ISO weekday? Unset days = every day. */
    public function runsOnDay(int $isoDow): bool
    {
        $days = $this->days_of_week;

        return ! is_array($days) || in_array($isoDow, array_map('intval', $days), true);
    }

    /** Is there a weekday both shifts run on? */
    public function sharesDayWith(self $other): bool
    {
        foreach (range(1, 7) as $dow) {
            if ($this->runsOnDay($dow) && $other->runsOnDay($dow)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Do the two shifts' clock times overlap, overnight shifts included?
     * Days are not compared — see sharesDayWith().
     */
    public function clockOverlaps(self $other): bool
    {
        $toMin = fn ($t) => (int) substr((string) $t, 0, 2) * 60 + (int) substr((string) $t, 3, 2);

        $ns = $toMin($this->start_time);
        $ne = $toMin($this->end_time);
        $es = $toMin($other->start_time);
        $ee = $toMin($other->end_time);

        // Overnight (22:00–06:00) ends the next day.
        $ne += $ne < $ns ? 1440 : 0;
        $ee += $ee < $es ? 1440 : 0;

        // Compare in the original and in both ±24h positions.
        return ($ns < $ee && $ne > $es)
            || ($ns < $ee + 1440 && $ne > $es + 1440)
            || ($ns + 1440 < $ee && $ne + 1440 > $es);
    }

    /**
     * The shifts that really run on a line: those set for it (its own, its
     * group's and — with $plantWide — the ones for every line), minus any that
     * a narrower shift on the line overlaps. Shifts of different coverage may
     * overlap (Shift::coverage), and where they do the narrower is the line's
     * shift; counting both would plan the same hours twice.
     *
     * With $isoDow only shifts running that weekday are considered; without
     * it a shift gives way only to a narrower one sharing one of its days.
     *
     * @param  \Illuminate\Support\Collection<int, self>|null  $shifts  active shifts, when already loaded
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function effectiveOn(int $lineId, ?\Illuminate\Support\Collection $shifts = null, bool $plantWide = true, ?int $isoDow = null): \Illuminate\Support\Collection
    {
        $running = ($shifts ?? static::active()->get())
            ->filter(fn (self $s) => $s->runsOnLine($lineId, $plantWide) && ($isoDow === null || $s->runsOnDay($isoDow)))
            ->values();

        return $running
            ->reject(fn (self $s) => $running->contains(fn (self $o) => $o->specificity() > $s->specificity()
                && $o->clockOverlaps($s)
                && ($isoDow !== null || $o->sharesDayWith($s))))
            ->values();
    }

    /**
     * Active shifts that run on the line, the narrowest first (see
     * specificity()) and otherwise in sort order.
     *
     * A null line asks about the plant as a whole: every shift, the broadest
     * first — the plant's shift, not some line's own override, is what the
     * whole-plant screens (shift board, plant overview) mean by "now".
     *
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function runningOn(?int $lineId): \Illuminate\Support\Collection
    {
        $lineId = $lineId ?: null;
        $shifts = static::active()
            ->get()
            ->filter(fn (self $shift) => $shift->runsOnLine($lineId));

        // Stable sorts: same specificity keeps the sort_order from active().
        return ($lineId === null
            ? $shifts->sortBy(fn (self $shift) => $shift->specificity())
            : $shifts->sortByDesc(fn (self $shift) => $shift->specificity())
        )->values();
    }

    public function shiftEntries(): HasMany
    {
        return $this->hasMany(WorkOrderShiftEntry::class);
    }

    /**
     * Returns the shift active right now, optionally filtered by line.
     */
    public static function current(?int $lineId = null): ?self
    {
        $now = now();
        $dayOfWeek = (int) $now->format('N');
        $time = $now->format('H:i:s');

        return static::runningOn($lineId)
            ->first(function (self $shift) use ($dayOfWeek, $time) {
                if (is_array($shift->days_of_week) && ! in_array($dayOfWeek, $shift->days_of_week)) {
                    return false;
                }
                if ($shift->start_time <= $shift->end_time) {
                    return $time >= $shift->start_time && $time < $shift->end_time;
                }

                return $time >= $shift->start_time || $time < $shift->end_time;
            });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public static function dayName(int $day): string
    {
        return ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][$day] ?? '?';
    }

    /** Children soft-deleted/restored together with this model (mirrors DB FK cascades). */
    public function softDeleteCascades(): array
    {
        return [
            [\App\Models\WorkOrderShiftEntry::class, 'shift_id'],
        ];
    }
}
