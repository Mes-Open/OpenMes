<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ApplyUpdateJob;
use App\Services\UpdateApplier;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class ApplyUpdateJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_job_calls_update_applier_run(): void
    {
        $remote = ['version' => 'v9.0.0', 'zip_url' => 'https://example.com/v9.zip'];

        $mock = Mockery::mock(UpdateApplier::class);
        $mock->shouldReceive('run')
            ->once()
            ->with('v9.0.0', $remote, 1);

        $job = new ApplyUpdateJob('v9.0.0', $remote, 1);
        $job->handle($mock);
    }

    public function test_failed_method_marks_status_as_failed(): void
    {
        $remote = ['version' => 'v9.0.0', 'zip_url' => 'https://example.com/v9.zip'];

        $job = new ApplyUpdateJob('v9.0.0', $remote, 1);
        $job->failed(new \RuntimeException('boom'));

        $status = Cache::get(UpdateApplier::STATUS_CACHE_KEY);
        $this->assertIsArray($status);
        $this->assertSame('failed', $status['state']);
        $this->assertSame('boom', $status['error']);
    }
}
