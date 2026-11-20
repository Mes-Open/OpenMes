<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\DB;

/**
 * An operator account on a production line (the `line_user` pivot).
 *
 * Both sides of the relation use this pivot — Line::users() and
 * User::lines() — so every path that changes an assignment (Admin → Lines →
 * Operators, the Production Planner's substitute, the REST API's line and
 * user syncs and detach, imports) fires these events. Each change touches
 * the line once the transaction commits: the pivot is not a synced
 * collection, but `lines_active` is, so planners watching staffing refresh.
 */
class LineUser extends Pivot
{
    protected $table = 'line_user';

    public $incrementing = false;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::created(fn (self $pivot) => static::touchLine($pivot));
        static::deleted(fn (self $pivot) => static::touchLine($pivot));
    }

    private static function touchLine(self $pivot): void
    {
        $lineId = $pivot->getAttribute('line_id');
        if ($lineId === null) {
            return;
        }
        DB::afterCommit(fn () => Line::find($lineId)?->touch());
    }
}
