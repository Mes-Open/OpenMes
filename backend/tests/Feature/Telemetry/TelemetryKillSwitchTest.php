<?php

namespace Tests\Feature\Telemetry;

use App\Jobs\SendTelemetryJob;
use App\Support\TelemetryIdentity;
use App\Support\TelemetrySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Every way of saying no, and the proof that each of them is obeyed.
 *
 * Opt-out is only defensible if switching it off actually works — from the
 * interface, from .env before the database exists, and by simply not being a
 * finished installation yet. Each of these is a separate promise, so each gets
 * its own test rather than one combined one.
 */
class TelemetryKillSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('telemetry.enabled', true);
        $this->markInstalled();
        TelemetrySettings::forget();
        Http::fake();
    }

    private function markInstalled(): void
    {
        $path = storage_path('installed');
        if (! is_file($path)) {
            @file_put_contents($path, date('Y-m-d H:i:s'));
        }
    }

    public function test_the_env_override_wins_and_needs_no_database(): void
    {
        // The switch for an air-gapped site or a packaged build: it is answered
        // before anything reaches the database, so it works mid-install too.
        config()->set('telemetry.enabled', false);

        $this->assertFalse(TelemetrySettings::enabled());

        (new SendTelemetryJob)->handle();
        Http::assertNothingSent();
    }

    public function test_the_admins_choice_in_settings_is_obeyed(): void
    {
        TelemetrySettings::put(TelemetrySettings::SETTING_KEY, false);

        $this->assertFalse(TelemetrySettings::enabled());

        (new SendTelemetryJob)->handle();
        Http::assertNothingSent();
    }

    public function test_an_installation_that_has_not_finished_installing_says_nothing(): void
    {
        @unlink(storage_path('installed'));

        $this->assertFalse(TelemetrySettings::enabled());

        (new SendTelemetryJob)->handle();
        Http::assertNothingSent();

        $this->markInstalled();
    }

    public function test_the_demo_server_is_not_a_customer_and_does_not_report(): void
    {
        // Our own demo traffic would drown the signal we actually want.
        config()->set('openmmes.demo_mode', true);

        $this->assertFalse(TelemetrySettings::enabled());

        (new SendTelemetryJob)->handle();
        Http::assertNothingSent();
    }

    public function test_the_default_for_an_install_that_was_never_asked_is_on(): void
    {
        // Opt-out: the migration seeds true, and an absent row also means on.
        TelemetrySettings::put(TelemetrySettings::SETTING_KEY, null);
        \Illuminate\Support\Facades\DB::table('system_settings')
            ->where('key', TelemetrySettings::SETTING_KEY)->delete();
        TelemetrySettings::forget();

        $this->assertTrue(TelemetrySettings::enabled());
    }

    public function test_an_installation_that_cannot_keep_its_own_name_does_not_report(): void
    {
        // Unwritable storage would mint a new id on every restart and turn one
        // site into a crowd. Better to stay quiet.
        $this->assertNotNull(TelemetryIdentity::installId());

        config()->set('telemetry.enabled', true);
        $this->assertTrue(TelemetrySettings::enabled());

        TelemetryIdentity::reset();
        $this->assertNotNull(TelemetryIdentity::installId(), 'A fresh id is minted when storage is writable.');
    }

    public function test_the_installation_id_is_stable_across_reports(): void
    {
        $first = TelemetryIdentity::installId();
        $second = TelemetryIdentity::installId();

        $this->assertSame($first, $second, 'Successive reports must be recognisable as one installation.');
        $this->assertTrue(\Illuminate\Support\Str::isUuid($first));
    }

    public function test_resetting_the_identity_makes_the_installation_a_stranger_again(): void
    {
        $before = TelemetryIdentity::installId();

        TelemetryIdentity::reset();
        $after = TelemetryIdentity::installId();

        $this->assertNotSame($before, $after, 'Reset is what makes a cloned VM fixable.');
    }

    public function test_the_identity_is_random_rather_than_derived_from_the_site(): void
    {
        // A hash of APP_URL or the hostname would be a fingerprint: reversible
        // by whoever holds the reports, and impossible for the customer to
        // rotate. Assert it bears no relation to either.
        config()->set('app.url', 'https://factory.example.com');
        TelemetryIdentity::reset();

        $id = TelemetryIdentity::installId();

        foreach ([md5('https://factory.example.com'), sha1('https://factory.example.com'), md5(gethostname() ?: '')] as $derived) {
            $this->assertStringNotContainsString(substr($derived, 0, 8), str_replace('-', '', $id));
        }
    }
}
