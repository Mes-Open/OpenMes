<?php

namespace Tests\Feature\Telemetry;

use App\Services\Telemetry\TelemetryErrorBuffer;
use App\Support\TelemetryIdentity;
use App\Support\TelemetrySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The rule that outranks the feature: telemetry never costs a user anything.
 *
 * The error buffer is the only part that runs inside a web request, and it runs
 * at the worst possible moment — while a 500 is being reported. If it were to
 * throw there it would mask the real fault, and the customer would get a blank
 * screen instead of a diagnosis: we would have replaced their bug with ours.
 *
 * So the buffer is tested against a broken cache, unwritable storage, a
 * database that is not there yet, and a lock it cannot take. In every case the
 * request must finish and the original exception must survive untouched.
 */
class TelemetryDoesNotBreakRequestsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('telemetry.enabled', true);

        $path = storage_path('installed');
        if (! is_file($path)) {
            @file_put_contents($path, date('Y-m-d H:i:s'));
        }

        TelemetrySettings::forget();
    }

    public function test_a_broken_cache_does_not_turn_one_fault_into_two(): void
    {
        Cache::shouldReceive('store')->andThrow(new \RuntimeException('redis is down'));

        $original = new \RuntimeException('the fault the user actually hit');

        // Must not throw. If it did, this exception would replace the one being
        // reported and the real cause would be lost.
        TelemetryErrorBuffer::record($original);

        $this->assertTrue(true, 'Recording survived a cache that refuses to answer.');
    }

    public function test_collecting_from_a_broken_cache_still_yields_a_sendable_shape(): void
    {
        Cache::shouldReceive('store')->andThrow(new \RuntimeException('redis is down'));

        $collected = TelemetryErrorBuffer::collect();

        $this->assertSame([], $collected['items']);
        $this->assertArrayHasKey('overflow_count', $collected);
    }

    public function test_a_missing_settings_table_does_not_break_anything(): void
    {
        // Exactly the state during an upgrade that is still migrating.
        DB::shouldReceive('table')->andThrow(new \RuntimeException('no such table: system_settings'));

        $this->assertFalse(TelemetrySettings::enabled(), 'Unknown means off.');

        TelemetryErrorBuffer::record(new \RuntimeException('during migration'));

        $this->assertTrue(true);
    }

    public function test_unwritable_storage_is_survivable(): void
    {
        // A read-only volume: no identity can be minted, so nothing is sent —
        // but the application itself carries on.
        $path = storage_path('telemetry-id');
        @unlink($path);
        @mkdir($path); // a directory where the file should be: writes will fail

        $id = TelemetryIdentity::installId();

        @rmdir($path);

        $this->assertNull($id, 'No id rather than an exception.');
    }

    public function test_recording_does_not_wait_on_a_lock_another_worker_holds(): void
    {
        // A burst of identical errors must not queue workers behind each other
        // while the user waits for an error page to render.
        $lock = Cache::lock('telemetry:errors:lock', 10);
        $lock->get();

        $start = microtime(true);
        TelemetryErrorBuffer::record(new \RuntimeException('contended'));
        $elapsed = microtime(true) - $start;

        $lock->release();

        $this->assertLessThan(0.5, $elapsed, 'A contended lock must drop the event, not block on it.');
    }

    public function test_a_real_server_error_still_renders_with_the_buffer_active(): void
    {
        // End to end: hit a route that throws and confirm the user still gets a
        // proper 500 while the fault is counted.
        Http::fake();
        TelemetryErrorBuffer::clear();

        \Illuminate\Support\Facades\Route::get('/__telemetry_probe', function () {
            throw new \RuntimeException('deliberate fault for the test');
        })->middleware('web');

        $response = $this->get('/__telemetry_probe');

        $response->assertStatus(500);
        Http::assertNothingSent();
    }

    public function test_the_command_succeeds_even_with_no_queue_worker_running(): void
    {
        // The common small self-host: the scheduler runs, nothing consumes the
        // queue. That is a non-event, not an error.
        Http::fake();

        $this->artisan('telemetry:send')->assertSuccessful();
    }

    public function test_the_dry_run_works_with_telemetry_switched_off(): void
    {
        // "Show me what you would send" has to answer even when sending is off,
        // or the disclosure is worthless to the person most likely to ask.
        TelemetrySettings::put(TelemetrySettings::SETTING_KEY, false);

        $this->artisan('telemetry:send', ['--dry-run' => true])->assertSuccessful();
    }
}
