<?php

namespace Tests\Feature\Telemetry;

use App\Jobs\SendTelemetryJob;
use App\Services\Telemetry\TelemetryErrorBuffer;
use App\Support\TelemetrySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Telemetry switched on, and nothing gets out.
 *
 * This is not an edge case. A factory network that blocks outbound traffic is
 * the normal condition for the kind of customer OpenMES is for, and most of
 * them will never touch the setting. Such an installation has to behave
 * *identically* to a connected one: no errors, no warnings in the interface, no
 * slowdown, and a log that does not fill up with a year of failed attempts.
 *
 * Every refusal a firewall can produce is covered — refused, dropped, poisoned
 * DNS, a proxy's own certificate, a captive portal answering 200 with HTML.
 */
class TelemetryOfflineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('telemetry.enabled', true);
        config()->set('telemetry.endpoint', 'https://getopenmes.com/telemetry.php');

        $path = storage_path('installed');
        if (! is_file($path)) {
            @file_put_contents($path, date('Y-m-d H:i:s'));
        }

        TelemetryErrorBuffer::clear();
        TelemetrySettings::forget();
    }

    private function runJob(): void
    {
        // Straight call rather than the queue: the point is that handle()
        // itself never lets anything escape.
        (new SendTelemetryJob)->handle();
    }

    private function assertNothingBroke(): void
    {
        $this->assertSame(0, DB::table('failed_jobs')->count(), 'Telemetry must never leave a failed job behind.');
    }

    public static function unreachableNetworks(): array
    {
        return [
            'connection refused' => [fn () => Http::fake(fn () => throw new ConnectionException('Connection refused'))],
            'dropped by firewall (timeout)' => [fn () => Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'))],
            'proxy substituted the certificate' => [fn () => Http::fake(fn () => throw new ConnectionException('cURL error 60: SSL certificate problem'))],
            'captive portal answers with HTML' => [fn () => Http::fake(fn () => Http::response('<html>Sign in to the guest network</html>', 200, ['Content-Type' => 'text/html']))],
            'proxy returns bad gateway' => [fn () => Http::fake(fn () => Http::response('', 502))],
            'receiver is having a bad day' => [fn () => Http::fake(fn () => Http::response('', 500))],
        ];
    }

    /**
     * @dataProvider unreachableNetworks
     */
    public function test_an_unreachable_network_costs_the_installation_nothing(\Closure $arrange): void
    {
        $arrange();

        $this->runJob();

        $this->assertNothingBroke();
        $this->assertSame(1, (int) TelemetrySettings::setting('telemetry_consecutive_failures', 0));
    }

    public function test_a_name_that_does_not_resolve_is_handled_by_the_guard_not_the_client(): void
    {
        // The URL guard resolves the host before any request is made, so on a
        // network with no DNS it is the guard that throws first. That path has
        // to be caught too — it is not an HTTP failure.
        config()->set('telemetry.endpoint', 'https://telemetry.invalid-host-'.uniqid().'.example/telemetry.php');
        Http::fake();

        $this->runJob();

        Http::assertNothingSent();
        $this->assertNothingBroke();
    }

    public function test_repeated_silence_makes_the_installation_try_less_often(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        for ($i = 0; $i < 3; $i++) {
            $this->runJob();
        }

        $this->assertSame(3, (int) TelemetrySettings::setting('telemetry_consecutive_failures', 0));
        $this->assertTrue(
            now()->addDays(6)->lessThan(TelemetrySettings::setting('telemetry_retry_after')),
            'After three failures the next attempt should be a week out, not tomorrow.',
        );

        for ($i = 0; $i < 7; $i++) {
            $this->runJob();
        }

        $this->assertTrue(
            now()->addDays(29)->lessThan(TelemetrySettings::setting('telemetry_retry_after')),
            'An installation with no route out should stop knocking daily.',
        );
    }

    public function test_only_the_first_failure_of_a_run_is_logged_and_only_at_debug(): void
    {
        // A year of daily failures must not become a year of log lines. On an
        // air-gapped site this would otherwise be the only thing in the log.
        Log::spy();
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->runJob();
        $this->runJob();
        $this->runJob();

        Log::shouldHaveReceived('debug')->once();
        Log::shouldNotHaveReceived('error');
        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('critical');
    }

    public function test_the_buffered_errors_survive_a_failed_send(): void
    {
        TelemetryErrorBuffer::record(new \RuntimeException('a real fault'));
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->runJob();

        $this->assertCount(
            1,
            TelemetryErrorBuffer::collect()['items'],
            'Errors are kept for the next window; they are only dropped once delivered.',
        );
    }

    public function test_a_successful_send_clears_the_slate(): void
    {
        TelemetryErrorBuffer::record(new \RuntimeException('a real fault'));
        TelemetrySettings::put('telemetry_consecutive_failures', 5);
        Http::fake(fn () => Http::response('', 204));

        $this->runJob();

        $this->assertSame([], TelemetryErrorBuffer::collect()['items']);
        $this->assertSame(0, (int) TelemetrySettings::setting('telemetry_consecutive_failures'));
        $this->assertNotNull(TelemetrySettings::setting('telemetry_last_sent_at'));
    }

    public function test_the_receiver_can_switch_this_installation_off_remotely(): void
    {
        Http::fake(fn () => Http::response('', 410));

        $this->runJob();

        $this->assertFalse(
            TelemetrySettings::setting(TelemetrySettings::SETTING_KEY),
            'A retired endpoint must be able to stop installations without a release.',
        );
    }

    public function test_a_busy_receiver_is_obeyed_and_the_errors_are_kept(): void
    {
        TelemetryErrorBuffer::record(new \RuntimeException('a real fault'));
        Http::fake(fn () => Http::response('', 429, ['Retry-After' => '3600']));

        $this->runJob();

        $this->assertNotNull(TelemetrySettings::setting('telemetry_retry_after'));
        $this->assertCount(1, TelemetryErrorBuffer::collect()['items']);
    }

    public function test_an_absurd_retry_after_cannot_silence_an_installation_for_ever(): void
    {
        Http::fake(fn () => Http::response('', 503, ['Retry-After' => (string) (86400 * 3650)]));

        $this->runJob();

        $this->assertTrue(
            now()->addDays(8)->greaterThan(TelemetrySettings::setting('telemetry_retry_after')),
            'A ten-year Retry-After is capped at a week.',
        );
    }
}
