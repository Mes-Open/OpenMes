<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LineStatus extends Model
{
    use SoftDeletesWithAudit;

    protected $fillable = ['name', 'color', 'sort_order', 'line_id', 'is_default', 'is_done_status'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_done_status' => 'boolean',
        ];
    }

    public function line(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    public function workOrders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * Return statuses available for a given line:
     * global statuses (line_id = null) + line-specific statuses.
     */
    public function scopeForLine($query, ?int $lineId)
    {
        return $query->where(function ($q) use ($lineId) {
            $q->whereNull('line_id');
            if ($lineId) {
                $q->orWhere('line_id', $lineId);
            }
        })->orderBy('sort_order')->orderBy('id');
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('line_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Put a global status at `$position` (1-based) and renumber the set 1..n.
     *
     * `sort_order` is a position on the board, so two statuses claiming the same
     * number is meaningless — the board falls back to `id` and the number stops
     * describing what you see. Rather than reject the collision (which makes
     * swapping two statuses a three-save dance: park one on a spare number, move
     * the other, bring the first back), the number is read as "put it here": the
     * status lands at that position, the rest slide over, and the whole set is
     * renumbered contiguously. Ties and gaps both stop being representable.
     *
     * `$position` is clamped, and null appends — the form always sends a number,
     * but a direct API caller need not.
     *
     * Only the global set is renumbered. A line's own statuses are a separate
     * sequence that merges with this one at render time (`scopeForLine`).
     */
    public static function placeGlobalAt(self $status, ?int $position): void
    {
        DB::transaction(function () use ($status, $position) {
            $others = static::globalsInOrder()->reject(fn ($row) => $row->id === $status->id)->values()->all();

            $index = $position === null
                ? count($others)
                : max(0, min(count($others), $position - 1));

            array_splice($others, $index, 0, [$status]);

            static::renumber($others);
        });
    }

    /**
     * Close the gap a deleted status leaves behind, so the set stays 1..n.
     *
     * Without this, deleting the middle status leaves 1, 3 — harmless to the
     * board, but the next edit would be reasoning about positions that don't
     * match what the list shows.
     */
    public static function resequenceGlobal(): void
    {
        DB::transaction(fn () => static::renumber(static::globalsInOrder()->all()));
    }

    /**
     * Renumber the global set into the order `$ids` gives, 1..n.
     *
     * Ids the caller didn't mention keep their relative order and follow the
     * ones it did — a drag from a page that was showing a subset must not
     * silently drop everything it couldn't see.
     *
     * @param  array<int, int|string>  $ids
     */
    public static function applyGlobalOrder(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $byId = static::globalsInOrder()->keyBy('id');
            $rank = array_flip(array_values(array_map('intval', $ids)));

            $ordered = $byId->sortBy(
                fn ($row, $id) => [$rank[$id] ?? PHP_INT_MAX, (int) $row->sort_order, (int) $id],
            )->values()->all();

            static::renumber($ordered);
        });
    }

    /** @return \Illuminate\Support\Collection<int, self> */
    private static function globalsInOrder(): \Illuminate\Support\Collection
    {
        return static::query()->whereNull('line_id')->orderBy('sort_order')->orderBy('id')->get();
    }

    /** @param  array<int, self>  $rows */
    private static function renumber(array $rows): void
    {
        foreach ($rows as $i => $row) {
            $next = $i + 1;

            // Only the rows that actually move are written, so a no-op save
            // doesn't broadcast a change to every subscriber's list.
            if ((int) $row->sort_order !== $next) {
                $row->sort_order = $next;
                $row->save();
            }
        }
    }
}
