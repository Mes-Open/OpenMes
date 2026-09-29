<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * One lock across every operation that rewrites the sample data wholesale.
 *
 * A demo install hit `SQLSTATE[40P01] deadlock detected` on the seeder's bulk
 * delete of batches. The other side of that deadlock was holding a lock the
 * ordinary write path never takes — which is what "replace the sample data"
 * does: it empties the tables and then runs `migrate`, and migrations take DDL
 * locks. Two people, one loading and one replacing, is all it took.
 *
 * So the lock is not "one loader at a time": it is one *bulk rewrite* at a time,
 * and every entry point has to take the same one. A lock held on only some of
 * them is worse than none, because it reads as protection.
 *
 * It also settles a race that predates the deadlock. Settings read
 * `sample_data_loaded` and then decided what to do about it, minutes apart from
 * the seeding a parallel request had already started; the check now happens
 * with the lock held, so the answer is still true when it is acted on.
 */
class SampleDataLock
{
    public const KEY = 'sample_data_load_lock';

    /**
     * Long enough for the slowest dataset on a tired demo box — loading runs
     * one to three minutes — and short enough that a worker killed mid-seed
     * does not leave the button dead for the rest of the afternoon. The lock is
     * released in a `finally`, so this ceiling is the backstop, not the plan.
     */
    public const TTL = 900;

    /**
     * Run $work holding the lock, or hand back to $whenBusy without waiting.
     *
     * Non-blocking on purpose: these operations take minutes, and a second
     * request queueing behind the first would hold a PHP worker and time out
     * the browser rather than say anything useful.
     *
     * @template TValue
     *
     * @param  callable(): TValue  $work
     * @param  callable(): TValue  $whenBusy
     * @return TValue
     */
    public static function run(callable $work, callable $whenBusy): mixed
    {
        $lock = Cache::lock(self::KEY, self::TTL);

        if (! $lock->get()) {
            return $whenBusy();
        }

        try {
            return $work();
        } finally {
            // Every exit releases, including the seeder throwing. The explicit
            // release-on-each-path style used elsewhere in this codebase holds
            // only until somebody adds a return in the middle, and the paths
            // through the caller here already branch three ways.
            $lock->release();
        }
    }
}
