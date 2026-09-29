<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * A pseudonymous name for this installation, so successive reports can be
 * recognised as coming from the same place.
 *
 * Random, never derived. A hash of the hostname or APP_URL would be a
 * fingerprint: reversible by dictionary for anyone holding the reports, and
 * impossible for the customer to rotate. A random UUID means the id says
 * nothing off-box, and deleting one file makes this installation a stranger
 * again — which is what reset() is for after cloning a VM to staging.
 *
 * The file is the only copy. Deliberately not mirrored into the database:
 * restoring a production dump onto a test box should look like a new
 * installation rather than silently double a customer's reported counts.
 */
class TelemetryIdentity
{
    private const FILENAME = 'telemetry-id';

    /**
     * The installation id, created on first use.
     *
     * Returns null when storage is unwritable — a read-only or missing volume.
     * Callers treat that as "do not report": an installation that cannot keep
     * its name would mint a new one on every restart and inflate every count.
     */
    public static function installId(): ?string
    {
        try {
            $path = storage_path(self::FILENAME);

            if (is_file($path)) {
                $existing = trim((string) file_get_contents($path));
                if (Str::isUuid($existing)) {
                    return $existing;
                }
            }

            $uuid = (string) Str::uuid();

            // LOCK_EX so two workers starting together cannot interleave a
            // half-written file; the re-read after makes them agree on whichever
            // value actually landed.
            if (@file_put_contents($path, $uuid.PHP_EOL, LOCK_EX) === false) {
                return null;
            }

            $written = trim((string) @file_get_contents($path));

            return Str::isUuid($written) ? $written : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Forget this installation's name; a new one is minted on next use. */
    public static function reset(): bool
    {
        try {
            $path = storage_path(self::FILENAME);

            return ! is_file($path) || @unlink($path);
        } catch (\Throwable) {
            return false;
        }
    }
}
