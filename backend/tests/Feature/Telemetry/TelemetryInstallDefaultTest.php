<?php

namespace Tests\Feature\Telemetry;

use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * A new installation reports by default; an existing one keeps its answer.
 *
 * The decision lives in the Docker entrypoint, which is a shell script and not
 * reachable from a normal test. So this runs the entrypoint's own usage-report
 * block in `sh`, with `php` stubbed to print what it was asked to store, in a
 * scratch directory standing in for the application root.
 */
class TelemetryInstallDefaultTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/telemetry-entrypoint-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/storage', 0777, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir.'/storage/installed');
        @rmdir($this->dir.'/storage');
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function test_fresh_install_without_env_records_on(): void
    {
        $out = $this->runBlock(freshInstall: true, env: null);

        $this->assertStringContainsString('Recording usage-report choice: true', $out);
        $this->assertStringContainsString('STORE=true', $out);
        $this->assertStringContainsString('Anonymous usage reports are ON', $out);
    }

    public function test_env_false_records_off_on_a_fresh_install(): void
    {
        $out = $this->runBlock(freshInstall: true, env: 'false');

        $this->assertStringContainsString('Recording usage-report choice: false', $out);
        $this->assertStringContainsString('STORE=false', $out);
        $this->assertStringNotContainsString('are ON', $out);
    }

    public function test_env_true_records_on(): void
    {
        $out = $this->runBlock(freshInstall: false, env: 'TRUE');

        $this->assertStringContainsString('STORE=true', $out);
    }

    public function test_existing_installation_without_env_is_left_alone(): void
    {
        // A database that already had users but lost its installed flag must
        // not be switched back on.
        $out = $this->runBlock(freshInstall: false, env: null);

        $this->assertStringNotContainsString('Recording usage-report choice', $out);
        $this->assertStringNotContainsString('STORE=', $out);
    }

    public function test_installed_application_is_never_touched(): void
    {
        file_put_contents($this->dir.'/storage/installed', 'x');

        $out = $this->runBlock(freshInstall: true, env: 'true');

        $this->assertStringNotContainsString('STORE=', $out);
    }

    public function test_web_installer_ticks_the_box_by_default(): void
    {
        $view = file_get_contents(resource_path('views/install/admin.blade.php'));

        $this->assertStringContainsString("old('telemetry_enabled', true) ? 'checked' : ''", $view);
    }

    private function runBlock(bool $freshInstall, ?string $env): string
    {
        $entrypoint = file_get_contents(base_path('docker-entrypoint.sh'));
        $start = strpos($entrypoint, '# ── Anonymous usage reports');
        $end = strpos($entrypoint, '# ── Mark as installed');
        $this->assertNotFalse($start, 'The usage-report block must exist in the entrypoint.');
        $this->assertNotFalse($end);

        $script = <<<'SH'
php() {
    case "$*" in
        *TelemetrySettings::put*) echo "STORE=${TELEMETRY_CHOICE}" ;;
    esac
}
SH;
        $script .= "\nFRESH_INSTALL=".($freshInstall ? '1' : '0')."\n";
        $script .= substr($entrypoint, $start, $end - $start);

        // Symfony Process inherits the parent environment; false removes a
        // variable, so phpunit.xml's own OPENMES_TELEMETRY cannot leak in.
        $envVars = ['OPENMES_TELEMETRY' => $env ?? false];

        $process = new Process(['sh', '-c', $script], $this->dir, $envVars);
        $process->mustRun();

        return $process->getOutput();
    }
}
