<?php

namespace Tests\Unit\Connectivity;

use App\Services\Connectivity\WorkerPool;
use PHPUnit\Framework\TestCase;

class WorkerPoolTest extends TestCase
{
    /** @var list<string> */
    private array $lines = [];

    private WorkerPool $pool;

    /** @var array<int, string> PHP code each key's worker runs */
    private array $scripts = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->pool = new WorkerPool(
            fn (int $key) => [PHP_BINARY, '-r', $this->scripts[$key] ?? 'echo "up\n"; sleep(30);'],
            function (int $key, string $line) {
                $this->lines[] = "{$key}: {$line}";
            },
        );
    }

    protected function tearDown(): void
    {
        $this->pool->stopAll(1.0);
        parent::tearDown();
    }

    private function settle(): void
    {
        $deadline = microtime(true) + 3;
        do {
            usleep(100_000);
            $this->pool->pump(microtime(true));
        } while (microtime(true) < $deadline && count($this->lines) < count($this->pool->keys()));
    }

    public function test_each_wanted_key_gets_its_own_worker(): void
    {
        $this->pool->sync([1, 2], microtime(true));
        $this->settle();

        $this->assertSame([1, 2], $this->pool->keys());
        $this->assertEqualsCanonicalizing(['1: up', '2: up'], $this->lines);
    }

    public function test_a_worker_no_longer_wanted_is_stopped(): void
    {
        $this->pool->sync([1, 2], microtime(true));
        $this->pool->sync([2], microtime(true));

        $this->assertSame([2], $this->pool->keys());
    }

    public function test_a_worker_that_dies_young_is_restarted_only_after_a_pause(): void
    {
        $this->scripts[1] = 'exit(1);';
        $start = microtime(true);
        $this->pool->sync([1], $start);
        usleep(500_000);

        $this->pool->pump($start + 1);
        $this->assertSame([], $this->pool->keys());

        $this->pool->sync([1], $start + 2);
        $this->assertSame([], $this->pool->keys(), 'restarted during the pause');

        $this->pool->sync([1], $start + 10);
        $this->assertSame([1], $this->pool->keys());
    }
}
