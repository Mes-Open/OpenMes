<?php

namespace Tests\Feature\Middleware;

use App\Models\RequestLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LogRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Register ad-hoc routes that go through the same middleware stack as
        // the rest of the web app (including LogRequest, appended in bootstrap).
        Route::middleware('web')->group(function () {
            Route::get('/_test/ping', fn () => 'pong')->name('test.ping');
            Route::post('/_test/ping', fn () => 'pong')->name('test.ping.post');
            Route::put('/_test/ping', fn () => 'pong');
            Route::patch('/_test/ping', fn () => 'pong');
            Route::delete('/_test/ping', fn () => 'pong');

            // Skip-prefix targets.
            Route::get('/livewire/test', fn () => 'lw');
            Route::get('/build/foo', fn () => 'asset');
            Route::get('/admin/update/status', fn () => 'banner');
        });
    }

    public function test_anonymous_request_is_not_logged(): void
    {
        $this->get('/_test/ping')->assertOk();

        $this->assertSame(0, RequestLog::count());
    }

    public function test_authenticated_get_is_sampled(): void
    {
        $user = User::factory()->create();

        // Hit the GET endpoint 30 times; expect a partial sample, never all 30.
        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($user)->get('/_test/ping')->assertOk();
        }

        $count = RequestLog::count();
        $this->assertLessThan(30, $count, 'GETs should be sampled, not logged 1:1');

        // Whatever rows landed must be flagged as sampled.
        if ($count > 0) {
            $this->assertSame($count, RequestLog::where('sampled', true)->count());
        }
    }

    public function test_authenticated_post_is_always_logged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/_test/ping')->assertOk();

        $this->assertSame(1, RequestLog::count());
        $row = RequestLog::first();
        $this->assertSame($user->id, $row->user_id);
        $this->assertSame('POST', $row->method);
        $this->assertFalse($row->sampled);
    }

    public function test_authenticated_put_patch_delete_are_always_logged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/_test/ping')->assertOk();
        $this->actingAs($user)->patch('/_test/ping')->assertOk();
        $this->actingAs($user)->delete('/_test/ping')->assertOk();

        $this->assertSame(3, RequestLog::count());
        $this->assertSame(3, RequestLog::where('sampled', false)->count());
    }

    public function test_skip_prefixes_not_logged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/livewire/test')->assertOk();
        $this->actingAs($user)->get('/build/foo')->assertOk();
        $this->actingAs($user)->get('/admin/update/status')->assertOk();

        $this->assertSame(0, RequestLog::count());
    }

    public function test_row_contains_meta_only(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.7', 'HTTP_USER_AGENT' => 'OpenMesTestUA/1.0'])
            ->post('/_test/ping?secret=should-not-be-stored', ['password' => 'super-secret'])
            ->assertOk();

        $row = RequestLog::first();
        $this->assertNotNull($row);

        // Whitelisted meta columns only.
        $expected = [
            'user_id', 'method', 'path', 'route_name', 'status',
            'duration_ms', 'ip_address', 'user_agent', 'sampled',
            'id', 'created_at',
        ];
        $actual = array_keys($row->getAttributes());
        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual);

        // No body / params leaked anywhere.
        $serialized = json_encode($row->toArray());
        $this->assertStringNotContainsString('super-secret', $serialized);
        $this->assertStringNotContainsString('should-not-be-stored', $serialized);

        // Sanity-check key fields.
        $this->assertSame($user->id, $row->user_id);
        $this->assertSame('POST', $row->method);
        $this->assertSame('/_test/ping', $row->path);
        $this->assertSame('test.ping.post', $row->route_name);
        $this->assertSame(200, $row->status);
        $this->assertSame('203.0.113.7', $row->ip_address);
        $this->assertSame('OpenMesTestUA/1.0', $row->user_agent);
        $this->assertIsInt($row->duration_ms);
        $this->assertGreaterThanOrEqual(0, $row->duration_ms);
        $this->assertFalse($row->sampled);
    }

    public function test_request_log_is_immutable(): void
    {
        $log = RequestLog::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Request logs are immutable.');

        $log->update(['status' => 500]);
    }

    public function test_request_log_cannot_be_deleted(): void
    {
        $log = RequestLog::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Request logs are immutable');

        $log->delete();
    }

    public function test_middleware_failure_does_not_break_request(): void
    {
        $user = User::factory()->create();

        // Simulate a DB outage by making RequestLog::create() throw on `creating`.
        // The middleware MUST catch the exception, warn, and let the response pass.
        RequestLog::creating(function () {
            throw new \RuntimeException('Simulated DB outage');
        });

        try {
            Log::shouldReceive('warning')
                ->once()
                ->with(\Mockery::pattern('/RequestLog write failed/'));

            // Forward the other log calls so Laravel internals don't blow up.
            Log::shouldReceive('debug')->andReturnNull();
            Log::shouldReceive('info')->andReturnNull();
            Log::shouldReceive('error')->andReturnNull();

            $response = $this->actingAs($user)->post('/_test/ping');

            $response->assertOk();
            $this->assertSame(0, RequestLog::count());
        } finally {
            // Forget the listener so it doesn't bleed into other tests.
            RequestLog::flushEventListeners();
            RequestLog::boot();
        }
    }
}
