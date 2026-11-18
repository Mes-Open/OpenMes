<?php

namespace Tests\Feature\Web;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Webhook;
use App\Support\WebhookEventRegistry;
use App\Support\WebhookNetworkAllowlist;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Webhooks → Endpoints → "Local network receivers" (T2a): the admin-only
 * private-network allowlist that lets a webhook reach an on-prem ERP.
 */
class WebhookAllowlistTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    private function saveList(array $entries, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin)
            ->from('/admin/webhooks')
            ->put('/admin/webhook-allowlist', ['entries' => $entries]);
    }

    public function test_admin_saves_a_normalized_allowlist_and_it_is_audited(): void
    {
        WebhookNetworkAllowlist::save(['10.9.0.0/16']);

        $this->saveList(['192.168.1.0/24', ' Odoo.LAN ', '10.0.0.5', 'fd00:1::/64'])
            ->assertRedirect('/admin/webhooks')
            ->assertSessionHas('success', 'Local network allowlist saved.');

        $stored = ['192.168.1.0/24', 'odoo.lan', '10.0.0.5/32', 'fd00:1::/64'];
        $this->assertSame($stored, WebhookNetworkAllowlist::entries());

        $log = AuditLog::where('action', 'webhook_network_allowlist_updated')->sole();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame(['entries' => ['10.9.0.0/16']], $log->before_state);
        $this->assertSame(['entries' => $stored], $log->after_state);
    }

    public function test_an_empty_list_restores_the_default(): void
    {
        WebhookNetworkAllowlist::save(['192.168.1.0/24']);

        $this->saveList([])->assertRedirect('/admin/webhooks')->assertSessionHasNoErrors();

        $this->assertSame([], WebhookNetworkAllowlist::entries());
    }

    /** @return array<string, array{0: string}> */
    public static function invalidEntries(): array
    {
        return [
            'everything v4' => ['0.0.0.0/0'],
            'everything v6' => ['::/0'],
            'prefix out of range' => ['192.168.1.0/33'],
            'too broad' => ['10.0.0.0/4'],
            'garbage' => ['not a host!'],
            'url instead of host' => ['http://x.lan'],
            'host with port' => ['odoo.lan:8069'],
            'wildcard' => ['*.lan'],
            'loopback range' => ['127.0.0.0/8'],
            'loopback v6' => ['::1'],
            'metadata ip' => ['169.254.169.254'],
            'link-local range' => ['169.254.0.0/16'],
            'localhost' => ['localhost'],
            'localhost subdomain' => ['app.localhost'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidEntries')]
    public function test_invalid_entry_is_rejected_and_nothing_changes(string $entry): void
    {
        WebhookNetworkAllowlist::save(['192.168.1.0/24']);

        $this->saveList(['10.0.0.0/8', $entry])
            ->assertRedirect('/admin/webhooks')
            ->assertSessionHasErrors('entries.1');

        $this->assertSame(['192.168.1.0/24'], WebhookNetworkAllowlist::entries());
        $this->assertSame(0, AuditLog::where('action', 'webhook_network_allowlist_updated')->count());
    }

    public function test_slash_zero_has_its_own_message(): void
    {
        $this->saveList(['0.0.0.0/0'])
            ->assertSessionHasErrors(['entries.0' => 'Allowing every address (/0) is not permitted.']);
    }

    public function test_duplicates_and_too_many_entries_are_rejected(): void
    {
        $this->saveList(['10.0.0.0/8', '10.0.0.0/8'])->assertSessionHasErrors('entries.1');

        $many = array_map(fn (int $i) => "10.0.{$i}.0/24", range(0, WebhookNetworkAllowlist::MAX_ENTRIES));
        $this->saveList($many)->assertSessionHasErrors('entries');

        $this->assertSame([], WebhookNetworkAllowlist::entries());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->put('/admin/webhook-allowlist', ['entries' => ['192.168.1.0/24']])->assertRedirect('/login');

        $this->assertSame([], WebhookNetworkAllowlist::entries());
    }

    public function test_supervisor_with_the_webhooks_tab_cannot_change_the_list(): void
    {
        Role::findByName('Supervisor', 'web')->givePermissionTo('tab:webhooks');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor');

        // The tab grants the page…
        $this->actingAs($supervisor)->get('/admin/webhooks')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canEditAllowlist', false));

        // …but not the allowlist.
        $this->saveList(['192.168.1.0/24'], $supervisor)->assertForbidden();
        $this->assertSame([], WebhookNetworkAllowlist::entries());
    }

    public function test_operator_cannot_change_the_list(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->saveList(['192.168.1.0/24'], $operator)->assertForbidden();
        $this->assertSame([], WebhookNetworkAllowlist::entries());
    }

    public function test_index_exposes_the_allowlist_to_the_admin(): void
    {
        WebhookNetworkAllowlist::save(['192.168.1.0/24']);

        $this->actingAs($this->admin)->get('/admin/webhooks')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('allowlist', ['192.168.1.0/24'])
                ->where('canEditAllowlist', true));
    }

    public function test_lan_webhook_is_refused_until_its_network_is_allowed(): void
    {
        $payload = [
            'name' => 'Odoo LAN',
            'url' => 'http://192.168.1.50:8069/openmes/hook',
            'events' => [WebhookEventRegistry::WORK_ORDER_STATUS_CHANGED],
            'is_active' => true,
        ];

        $this->actingAs($this->admin)->from('/admin/webhooks')->post('/admin/webhooks', $payload)
            ->assertSessionHasErrors('url');
        $this->assertDatabaseMissing('webhooks', ['name' => 'Odoo LAN']);

        $this->saveList(['192.168.1.0/24'])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post('/admin/webhooks', $payload)
            ->assertRedirect('/admin/webhooks')
            ->assertSessionHasNoErrors();

        $webhook = Webhook::where('name', 'Odoo LAN')->sole();
        $this->assertNull($webhook->api_key_id); // admin-owned
    }

    public function test_allowlist_is_not_exported_with_the_settings(): void
    {
        WebhookNetworkAllowlist::save(['192.168.1.0/24']);

        $response = $this->actingAs($this->admin)->get('/settings/export')->assertOk();

        $json = $response->json();
        $this->assertArrayHasKey('system_settings', $json);
        $this->assertArrayNotHasKey(WebhookNetworkAllowlist::SETTING_KEY, $json['system_settings']);
    }

    public function test_a_settings_import_cannot_set_the_allowlist(): void
    {
        app(\App\Services\Settings\ConfigurationImporter::class)->import([
            'system_settings' => [WebhookNetworkAllowlist::SETTING_KEY => json_encode(['10.0.0.0/8'])],
        ]);

        $this->assertSame([], WebhookNetworkAllowlist::entries());
    }
}
