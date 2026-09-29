<?php

namespace App\Console\Commands;

use App\Jobs\SendTelemetryJob;
use App\Services\Telemetry\TelemetrySnapshot;
use App\Support\TelemetryIdentity;
use App\Support\TelemetrySettings;
use Illuminate\Console\Command;

/**
 * Decides whether today is this installation's turn to report, and dispatches.
 *
 * Runs hourly but sends at most once a day, at an hour derived from the
 * installation's own id. Without that spread every OpenMES in the world would
 * knock on getopenmes.com within the same minute — which would look exactly
 * like an attack on our own server, and would make the first thousand installs
 * the last ones we hear from.
 *
 * --dry-run prints the report instead of sending it. That is the honest answer
 * to "what are you actually collecting", and it works without a queue worker,
 * a network, or a login.
 */
class SendTelemetryCommand extends Command
{
    protected $signature = 'telemetry:send
                            {--dry-run : Print the report that would be sent and exit}
                            {--force : Ignore the schedule and send now}';

    protected $description = 'Report this installation’s software profile to getopenmes.com';

    public function handle(): int
    {
        if ($this->option('dry-run')) {
            // Deliberately bypasses the switches: an admin asking what would be
            // sent deserves an answer even when sending is off.
            $this->line(json_encode((new TelemetrySnapshot)->build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        if (! TelemetrySettings::enabled()) {
            $this->components->info('Telemetry is switched off.');

            return self::SUCCESS;
        }

        if (TelemetryIdentity::installId() === null) {
            // Unwritable storage: an installation that cannot keep its own name
            // would look like a brand new one every restart.
            $this->components->warn('No installation id could be established; not sending.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->due()) {
            return self::SUCCESS;
        }

        SendTelemetryJob::dispatch();

        $this->components->info('Telemetry queued.');

        return self::SUCCESS;
    }

    /**
     * Whether this installation should report in this hour.
     *
     * Three gates: the hour assigned to this install, the once-a-day rule, and
     * any deferral the receiver asked for or that repeated failures imposed.
     */
    private function due(): bool
    {
        $deferredUntil = TelemetrySettings::setting('telemetry_retry_after');
        if (is_string($deferredUntil) && now()->lessThan($deferredUntil)) {
            return false;
        }

        $lastSent = TelemetrySettings::setting('telemetry_last_sent_at');
        if (is_string($lastSent) && now()->diffInHours($lastSent, true) < 20) {
            return false;
        }

        $id = TelemetryIdentity::installId() ?? '';

        return (int) (crc32($id) % 24) === (int) now()->hour;
    }
}
