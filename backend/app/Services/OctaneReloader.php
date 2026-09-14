<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Cycles the Octane workers so a change to what the application is made of —
 * today, enabling or disabling a module — takes effect on the running server.
 *
 * Octane keeps the booted application in memory between requests, so a module's
 * provider stays registered (and its routes keep answering) until the workers
 * are replaced. Clearing caches does not touch any of that.
 *
 * It is a class rather than two lines in the controller because of how the bug
 * it fixes hid: the reload was a side effect buried in a private method, and
 * the test could only assert that a call was made, not that anything happened.
 * As a collaborator it can be swapped in a test and asserted on.
 */
class OctaneReloader
{
    /**
     * How long to wait for the reload. It signals the master process and
     * returns; a hang means something is wrong with the server, and waiting
     * forever would be worse than reporting it.
     */
    private const TIMEOUT_SECONDS = 15;

    /**
     * Reload, or explain in the log why it could not.
     *
     * Never throws: it runs after the response has been sent, where an
     * exception would take down the worker rather than reach anybody.
     */
    public function reload(): void
    {
        $failure = $this->attempt();

        if ($failure === null) {
            return;
        }

        // The two bugs this replaces were both invisible — the module setting
        // flipped, the flash said success, and nothing changed until someone
        // restarted the server by hand. Whether it appeared to work came down
        // to whether the worker happened to be recycled for some unrelated
        // reason. A reload that cannot happen has to be findable.
        Log::warning('Could not reload the Octane workers; the change takes effect on the next restart.', [
            'error' => $failure,
        ]);
    }

    /**
     * Run the reload as a subprocess.
     *
     * Neither in-process route works from an HTTP worker, which is the only
     * place this ever runs: Octane registers its commands and binds its
     * ServerProcessInspector only when the application is running in the
     * console, so `Artisan::call('octane:reload')` throws "command does not
     * exist" and resolving the inspector throws "not instantiable". A fresh
     * `artisan` process *is* in the console, and it stays correct for whichever
     * server (RoadRunner, Swoole, FrankenPHP) is configured rather than
     * reimplementing one of them here.
     *
     * Note this is exactly what the test suite cannot reproduce: PHPUnit runs
     * in the console, where the command exists, so a test that asserts the call
     * was made passes while production throws. That is how this survived.
     *
     * @return string|null the failure to report, or null on success
     */
    protected function attempt(): ?string
    {
        try {
            $process = new Process([PHP_BINARY, 'artisan', 'octane:reload'], base_path());
            $process->setTimeout(self::TIMEOUT_SECONDS);
            $process->run();

            if ($process->isSuccessful()) {
                return null;
            }

            return trim($process->getErrorOutput() ?: $process->getOutput())
                ?: 'octane:reload exited with code '.$process->getExitCode();
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }
}
