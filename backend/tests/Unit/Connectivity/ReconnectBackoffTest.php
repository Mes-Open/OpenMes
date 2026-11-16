<?php

namespace Tests\Unit\Connectivity;

use App\Services\Connectivity\ReconnectBackoff;
use PHPUnit\Framework\TestCase;

class ReconnectBackoffTest extends TestCase
{
    public function test_a_connection_that_never_failed_is_tried_at_once(): void
    {
        $this->assertTrue((new ReconnectBackoff)->ready(1, 1000.0));
    }

    public function test_each_failure_doubles_the_wait_up_to_the_cap(): void
    {
        $backoff = new ReconnectBackoff(5.0, 60.0);

        $waits = array_map(fn () => $backoff->failed(1, 0.0), range(1, 6));

        $this->assertSame([5.0, 10.0, 20.0, 40.0, 60.0, 60.0], $waits);
    }

    public function test_a_failed_broker_is_skipped_until_its_wait_is_over(): void
    {
        $backoff = new ReconnectBackoff(5.0, 60.0);
        $backoff->failed(1, 100.0);

        $this->assertFalse($backoff->ready(1, 104.9));
        $this->assertTrue($backoff->ready(1, 105.0));
        // Other connections are not held back by it.
        $this->assertTrue($backoff->ready(2, 100.0));
    }

    public function test_success_resets_the_wait(): void
    {
        $backoff = new ReconnectBackoff(5.0, 60.0);
        $backoff->failed(1, 0.0);
        $backoff->failed(1, 0.0);
        $backoff->succeeded(1);

        $this->assertTrue($backoff->ready(1, 0.0));
        $this->assertSame(5.0, $backoff->failed(1, 0.0));
    }
}
