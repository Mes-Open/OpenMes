<?php

namespace App\Jobs;

use App\Services\Telemetry\TelemetryErrorBuffer;
use App\Services\Telemetry\TelemetrySnapshot;
use App\Support\TelemetrySettings;
use App\Support\WebhookUrlGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Hands one report to getopenmes.com, or quietly does not.
 *
 * Deliberately weaker than DeliverWebhookJob, which it otherwise resembles: a
 * webhook is the customer's data going where the customer asked, so it retries
 * hard. This is our curiosity, and it must cost the customer nothing. One
 * attempt, no retry, failed() empty, every throwable caught — a day's report is
 * not worth a single row in failed_jobs, let alone a backlog.
 *
 * A plant with a closed egress firewall is the ordinary case, not a fault. It
 * must run exactly as well as a connected one: no warnings, no slowdown, and a
 * log that does not grow. Hence the silence below.
 */
class SendTelemetryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** One attempt. See the class comment. */
    public int $tries = 1;

    public int $timeout = 20;

    public function handle(): void
    {
        try {
            if (! TelemetrySnapshot::permitted()) {
                return;
            }

            $payload = (new TelemetrySnapshot)->build();
            $url = (string) config('telemetry.endpoint');

            // The endpoint is ours, but it is overridable from .env — and an
            // admin pointing it at 169.254.169.254 would be a cheap probe of
            // cloud metadata from inside our own scheduler. Same guard as
            // webhooks, same IP pinning.
            $target = WebhookUrlGuard::safeTarget($url);

            $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'OpenMES/'.(config('version.current') ?? 'unknown').' (+https://getopenmes.com)',
                'X-OpenMES-Install' => (string) $payload['install_id'],
                'X-OpenMES-Schema' => (string) $payload['schema_version'],
            ])
                ->withOptions(['curl' => [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$target['ip']}"]]])
                // connectTimeout matters more than timeout here: a firewall that
                // DROPs rather than REJECTs leaves the socket hanging, and
                // without this the worker would sit on it for minutes.
                ->connectTimeout(5)
                ->timeout(10)
                ->withBody($body, 'application/json')
                ->post($url);

            $this->interpret($response->status(), $response->header('Retry-After'), $response->body());
        } catch (\Throwable $e) {
            // Refused, timed out, DNS silence, a proxy's substituted
            // certificate — all the same thing from here: not today.
            $this->recordFailure($e->getMessage());
        }
    }

    /** Nothing lands in failed_jobs; handle() already swallowed everything. */
    public function failed(\Throwable $e): void
    {
        // Intentionally empty.
    }

    private function interpret(int $status, ?string $retryAfter, string $body): void
    {
        // A captive portal or an intercepting proxy answers 200 with its own
        // sign-in page. Treating that as delivered would clear the error buffer
        // for a report that reached nobody, so a 200 only counts when the body
        // is empty or is the JSON our receiver speaks.
        if (in_array($status, [200, 202], true) && ! $this->looksLikeOurReceiver($body)) {
            $this->recordFailure('HTTP '.$status.' but the response was not from the telemetry endpoint');

            return;
        }

        // Accepted. The buffered errors have been delivered, so forget them.
        if (in_array($status, [200, 202, 204], true)) {
            TelemetrySettings::put('telemetry_last_sent_at', now()->utc()->toIso8601String());
            TelemetrySettings::put('telemetry_last_status', $status);
            TelemetrySettings::put('telemetry_consecutive_failures', 0);
            TelemetrySettings::put('telemetry_retry_after', null);
            TelemetryErrorBuffer::clear();

            return;
        }

        // The receiver has been retired. Stop for good rather than knocking on
        // a door that is gone — this is the remote off switch, and it works
        // without shipping a new release.
        if ($status === 410) {
            TelemetrySettings::put(TelemetrySettings::SETTING_KEY, false);
            TelemetrySettings::put('telemetry_last_status', $status);

            return;
        }

        // Malformed by our own hand. Resending the same bytes tomorrow would
        // fail identically, so drop them.
        if (in_array($status, [400, 422], true)) {
            TelemetrySettings::put('telemetry_last_status', $status);
            TelemetryErrorBuffer::clear();

            return;
        }

        // Busy. Come back when told to, and keep the errors for then.
        if (in_array($status, [429, 503], true)) {
            TelemetrySettings::put('telemetry_last_status', $status);
            TelemetrySettings::put('telemetry_retry_after', $this->retryAfter($retryAfter));

            return;
        }

        $this->recordFailure('HTTP '.$status);
    }

    /**
     * Whether the thing that answered is plausibly our endpoint.
     *
     * Empty is fine (a bare 200 from telemetry.php) and so is JSON. Anything
     * else — an HTML login page most of all — means something in the middle
     * answered on our behalf.
     */
    private function looksLikeOurReceiver(string $body): bool
    {
        $body = trim($body);

        if ($body === '') {
            return true;
        }

        json_decode($body);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /** Seconds or an HTTP-date, capped so a bad header cannot silence us for ever. */
    private function retryAfter(?string $header): string
    {
        $default = now()->addDay();

        if ($header === null || $header === '') {
            return $default->utc()->toIso8601String();
        }

        $when = ctype_digit(trim($header))
            ? now()->addSeconds((int) trim($header))
            : (($ts = strtotime($header)) ? now()->setTimestamp($ts) : $default);

        $max = now()->addDays(7);

        return ($when->greaterThan($max) ? $max : $when)->utc()->toIso8601String();
    }

    /**
     * Back off as failures accumulate.
     *
     * An installation with no route to the internet would otherwise try every
     * day for ever, and after a year its log would consist of nothing else.
     * Only the first failure in a run is written, and only at debug.
     */
    private function recordFailure(string $reason): void
    {
        try {
            $failures = (int) TelemetrySettings::setting('telemetry_consecutive_failures', 0) + 1;

            TelemetrySettings::put('telemetry_consecutive_failures', $failures);
            TelemetrySettings::put('telemetry_last_status', 0);

            $next = match (true) {
                $failures >= 10 => now()->addDays(30),
                $failures >= 3 => now()->addDays(7),
                default => now()->addDay(),
            };

            TelemetrySettings::put('telemetry_retry_after', $next->utc()->toIso8601String());

            if ($failures === 1) {
                Log::debug('Telemetry not sent.', ['reason' => $reason]);
            }
        } catch (\Throwable) {
            // Even the bookkeeping is optional.
        }
    }
}
