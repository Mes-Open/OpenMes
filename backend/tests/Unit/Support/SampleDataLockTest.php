<?php

namespace Tests\Unit\Support;

use App\Support\SampleDataLock;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The lock's own contract, tested away from the controllers that use it.
 *
 * The controller-level tests cannot reach the case that matters most here: both
 * of them catch their own exceptions, so nothing ever escapes the callable and
 * a lock released on the happy path only would still pass. Whether the lock
 * survives an exception travelling through it is decided here.
 */
class SampleDataLockTest extends TestCase
{
    private function free(): bool
    {
        $lock = Cache::lock(SampleDataLock::KEY, SampleDataLock::TTL);

        if (! $lock->get()) {
            return false;
        }

        $lock->release();

        return true;
    }

    public function test_it_runs_the_work_and_gives_the_lock_back(): void
    {
        $result = SampleDataLock::run(fn () => 'done', fn () => 'busy');

        $this->assertSame('done', $result);
        $this->assertTrue($this->free());
    }

    public function test_it_hands_over_to_the_busy_branch_without_waiting(): void
    {
        $held = Cache::lock(SampleDataLock::KEY, SampleDataLock::TTL);
        $this->assertTrue($held->get());

        $ran = false;

        $result = SampleDataLock::run(
            function () use (&$ran) {
                $ran = true;

                return 'done';
            },
            fn () => 'busy',
        );

        $held->release();

        $this->assertSame('busy', $result);
        $this->assertFalse($ran, 'the work must not run while somebody else holds the lock');
    }

    public function test_an_exception_travelling_through_still_releases_the_lock(): void
    {
        // Without a finally, this is the case that leaves the lock held for its
        // full TTL and the button dead with it — and it is the one an explicit
        // release on each exit path silently misses.
        try {
            SampleDataLock::run(
                fn () => throw new \RuntimeException('the seeder fell over'),
                fn () => 'busy',
            );

            $this->fail('the exception should not have been swallowed');
        } catch (\RuntimeException $e) {
            $this->assertSame('the seeder fell over', $e->getMessage());
        }

        $this->assertTrue($this->free(), 'a lock held after a failure is worse than no lock at all');
    }
}
