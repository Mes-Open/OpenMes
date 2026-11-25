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

        return $this->coverage = is_array($coverage) && isset($coverage['key']) && array_key_exists('line_ids', $coverage)
            ? ['key' => (string) $coverage['key'], 'line_ids' => $coverage['line_ids'] === null ? null : array_map('intval', $coverage['line_ids'])]
            : $default;
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
        if ($this->line_id !== null) {
            return 2;
        }

        return $this->lineIds() === null ? 0 : 1;
    }

    /**
     * Active shifts that run on the line (all of them for a null line), the
     * narrowest first (see specificity()) and otherwise in sort order.
     *
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function runningOn(?int $lineId): \Illuminate\Support\Collection
    {
        return static::active()
            ->get()
            ->filter(fn (self $shift) => $shift->runsOnLine($lineId ?: null))
            ->sortByDesc(fn (self $shift) => $shift->specificity())
            ->values();
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
