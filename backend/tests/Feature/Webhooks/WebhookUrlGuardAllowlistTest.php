<?php

namespace Tests\Feature\Webhooks;

use App\Support\WebhookNetworkAllowlist;
use App\Support\WebhookUrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SSRF guard × private-network allowlist (T2a). Literal IPs throughout; the
 * hostname cases use the guard's resolver seam, so nothing depends on DNS.
 */
class WebhookUrlGuardAllowlistTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        WebhookUrlGuard::resolveUsing(null);
        parent::tearDown();
    }

    /** Store raw entries, bypassing the Form Request (the guard must hold on its own). */
    private function allow(array $entries): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => WebhookNetworkAllowlist::SETTING_KEY],
            ['value' => json_encode($entries), 'updated_at' => now()],
        );
    }

    public function test_private_addresses_are_unsafe_without_an_allowlist(): void
    {
        foreach (['http://192.168.1.50:8069/x', 'http://10.0.0.5/x', 'http://172.16.4.4/x', 'http://[fd00::1]/x'] as $url) {
            $this->assertFalse(WebhookUrlGuard::isSafe($url), $url);
        }

        $this->assertStringStartsWith(
            'Webhook URL resolves to a private/reserved address.',
            (string) WebhookUrlGuard::reason('http://192.168.1.50:8069/x'),
        );
        $this->assertStringContainsString(
            WebhookUrlGuard::ALLOWLIST_HINT,
            (string) WebhookUrlGuard::reason('http://192.168.1.50:8069/x'),
        );
    }

    public function test_matching_cidr_lets_a_private_address_through(): void
    {
        $this->allow(['192.168.1.0/24']);

        $this->assertTrue(WebhookUrlGuard::isSafe('http://192.168.1.50:8069/openmes/hook'));
        $this->assertFalse(WebhookUrlGuard::isSafe('http://192.168.2.5/hook'));
        $this->assertFalse(WebhookUrlGuard::isSafe('http://10.0.0.5/hook'));

        $this->assertSame(
            ['ip' => '192.168.1.50', 'host' => '192.168.1.50', 'port' => 8069],
            WebhookUrlGuard::safeTarget('http://192.168.1.50:8069/openmes/hook'),
        );
    }

    public function test_ipv6_ula_can_be_allowed(): void
    {
        $this->allow(['fd00:1::/64']);

        $this->assertTrue(WebhookUrlGuard::isSafe('http://[fd00:1::5]/hook'));
        $this->assertFalse(WebhookUrlGuard::isSafe('http://[fd00:2::5]/hook'));
    }

    public function test_metadata_loopback_and_link_local_stay_blocked_even_when_listed(): void
    {
        $this->allow([
            '127.0.0.0/8', '169.254.0.0/16', '100.64.0.0/10', '0.0.0.0/8', '::1/128', 'fe80::/10',
            '10.0.0.0/8', 'localhost',
        ]);

        foreach ([
            'http://127.0.0.1/x',
            'http://169.254.169.254/latest/meta-data',
            'http://169.254.170.2/x',
            'http://100.100.100.200/x',
            'http://[::1]/x',
            'http://[::ffff:127.0.0.1]/x',
            'http://[::ffff:169.254.169.254]/x',
            'http://[fe80::1]/x',
            'http://0.0.0.0/x',
            'http://2130706433/x',   // decimal 127.0.0.1
            'http://0x7f000001/x',   // hex 127.0.0.1
            'http://localhost/x',
        ] as $url) {
            $this->assertFalse(WebhookUrlGuard::isSafe($url), $url);
        }

        // …while a listed private range is reachable.
        $this->assertTrue(WebhookUrlGuard::isSafe('http://10.1.2.3/x'));
    }

    public function test_ipv4_mapped_private_address_follows_the_allowlist(): void
    {
        $this->assertFalse(WebhookUrlGuard::isSafe('http://[::ffff:192.168.1.50]/x'));

        $this->allow(['192.168.1.0/24']);

        $this->assertTrue(WebhookUrlGuard::isSafe('http://[::ffff:192.168.1.50]/x'));
    }

    public function test_public_address_is_safe_regardless_of_allowlist(): void
    {
        $this->assertTrue(WebhookUrlGuard::isSafe('https://93.184.216.34/hook'));

        $this->allow(['192.168.1.0/24']);

        $this->assertTrue(WebhookUrlGuard::isSafe('https://93.184.216.34/hook'));
    }

    public function test_hostname_entry_allows_what_that_name_resolves_to(): void
    {
        WebhookUrlGuard::resolveUsing(fn (string $host) => strtolower($host) === 'odoo.lan' ? ['192.168.1.50'] : []);

        $this->assertFalse(WebhookUrlGuard::isSafe('http://odoo.lan:8069/hook'));

        $this->allow(['odoo.lan']);

        $this->assertTrue(WebhookUrlGuard::isSafe('http://odoo.lan:8069/hook'));
        $this->assertTrue(WebhookUrlGuard::isSafe('http://ODOO.LAN:8069/hook'));
        $this->assertSame('192.168.1.50', WebhookUrlGuard::safeTarget('http://odoo.lan:8069/hook')['ip']);
    }

    public function test_hostname_entry_still_refuses_any_always_blocked_answer(): void
    {
        $this->allow(['odoo.lan']);
        WebhookUrlGuard::resolveUsing(fn () => ['192.168.1.50', '127.0.0.1']);

        $this->assertFalse(WebhookUrlGuard::isSafe('http://odoo.lan/hook'));

        WebhookUrlGuard::resolveUsing(fn () => ['169.254.169.254']);

        $this->assertFalse(WebhookUrlGuard::isSafe('http://odoo.lan/hook'));
    }
}
