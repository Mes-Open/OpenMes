<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\SsoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Sign in through a company OpenID Connect provider (#123). The provider is
 * faked at the HTTP layer: discovery, JWKS, token and userinfo endpoints,
 * with ID tokens signed by a key generated per test.
 */
class SsoLoginTest extends TestCase
{
    use RefreshDatabase;

    private const ISSUER = 'https://idp.example.test/realms/factory';

    private const CLIENT_ID = 'openmes';

    /** @var \OpenSSLAsymmetricKey */
    private $privateKey;

    /** Claims the fake token endpoint puts into the next ID token. */
    private array $idTokenClaims = [];

    private array $userinfo = [];

    private ?string $lastNonce = null;

    private ?array $tokenOverride = null;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Admin', 'Supervisor', 'Operator'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        Cache::flush();

        $this->privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $details = openssl_pkey_get_details($this->privateKey);
        $b64 = fn (string $bin) => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
        $jwks = ['keys' => [[
            'kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256', 'kid' => 'test-key',
            'n' => $b64($details['rsa']['n']), 'e' => $b64($details['rsa']['e']),
        ]]];

        Http::fake([
            self::ISSUER.'/.well-known/openid-configuration' => Http::response([
                'issuer' => self::ISSUER,
                'authorization_endpoint' => self::ISSUER.'/protocol/openid-connect/auth',
                'token_endpoint' => self::ISSUER.'/protocol/openid-connect/token',
                'userinfo_endpoint' => self::ISSUER.'/protocol/openid-connect/userinfo',
                'jwks_uri' => self::ISSUER.'/protocol/openid-connect/certs',
            ]),
            self::ISSUER.'/protocol/openid-connect/certs' => Http::response($jwks),
            self::ISSUER.'/protocol/openid-connect/token' => function (HttpRequest $request) {
                if ($this->tokenOverride !== null) {
                    return Http::response(...$this->tokenOverride);
                }

                return Http::response([
                    'access_token' => 'access-123',
                    'token_type' => 'Bearer',
                    'id_token' => $this->signedIdToken(array_merge([
                        'iss' => self::ISSUER,
                        'aud' => self::CLIENT_ID,
                        'sub' => 'subject-1',
                        'exp' => time() + 300,
                        'iat' => time(),
                        'nonce' => $this->lastNonce,
                    ], $this->idTokenClaims)),
                ]);
            },
            self::ISSUER.'/protocol/openid-connect/userinfo' => fn () => Http::response($this->userinfo),
        ]);

        SsoSettings::save([
            'enabled' => true,
            'provider_name' => 'Factory SSO',
            'issuer' => self::ISSUER,
            'client_id' => self::CLIENT_ID,
            'client_secret' => 'top-secret',
            'auto_provision' => false,
            'link_by_email' => true,
        ]);
    }

    private function signedIdToken(array $claims, string $kid = 'test-key'): string
    {
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $input = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $kid])).'.'.$b64(json_encode($claims));
        openssl_sign($input, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return $input.'.'.$b64($signature);
    }

    /** Start the flow; returns the `state` the provider would send back. */
    private function startLogin(): string
    {
        $response = $this->get('/auth/sso/redirect');
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(self::ISSUER.'/protocol/openid-connect/auth?', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->lastNonce = $query['nonce'];

        return $query['state'];
    }

    private function finishLogin(string $state)
    {
        return $this->get('/auth/sso/callback?'.http_build_query(['code' => 'auth-code', 'state' => $state]));
    }

    public function test_login_page_offers_the_sso_button_only_when_enabled(): void
    {
        $this->get('/login')->assertInertia(fn ($page) => $page->where('sso.name', 'Factory SSO'));

        SsoSettings::save(['enabled' => false]);

        $this->get('/login')->assertInertia(fn ($page) => $page->where('sso', null));
    }

    public function test_redirect_sends_the_browser_to_the_provider_with_pkce_state_and_nonce(): void
    {
        $location = $this->get('/auth/sso/redirect')->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame('code', $query['response_type']);
        $this->assertSame(self::CLIENT_ID, $query['client_id']);
        $this->assertSame(route('sso.callback'), $query['redirect_uri']);
        $this->assertSame('openid profile email', $query['scope']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertNotEmpty($query['state']);
        $this->assertNotEmpty($query['nonce']);
        $this->assertSame($query['state'], session('sso_pending.state'));
    }

    public function test_existing_account_is_linked_by_verified_email_and_signed_in(): void
    {
        $user = User::factory()->create(['email' => 'jane@company.test']);
        $user->assignRole('Supervisor');
        $this->idTokenClaims = ['email' => 'Jane@Company.test', 'email_verified' => true];

        $this->finishLogin($this->startLogin())->assertRedirect(route('supervisor.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('subject-1', $user->fresh()->sso_subject);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'login']);

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/token')
            && $r['client_secret'] === 'top-secret'
            && $r['code'] === 'auth-code'
            && ! empty($r['code_verifier']));
    }

    public function test_a_linked_account_is_found_by_subject_even_after_its_email_changed(): void
    {
        $user = User::factory()->create(['email' => 'old@company.test']);
        $user->forceFill(['sso_subject' => 'subject-1'])->save();
        $user->assignRole('Operator');
        $this->idTokenClaims = ['email' => 'new@company.test'];

        $this->finishLogin($this->startLogin())->assertRedirect(route('operator.select-line'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_email_from_the_userinfo_endpoint_is_used_when_the_id_token_has_none(): void
    {
        $user = User::factory()->create(['email' => 'jane@company.test']);
        $user->assignRole('Operator');
        $this->userinfo = ['sub' => 'subject-1', 'email' => 'jane@company.test'];

        $this->finishLogin($this->startLogin());

        $this->assertAuthenticatedAs($user);
    }

    public function test_userinfo_of_a_different_subject_is_ignored(): void
    {
        $user = User::factory()->create(['email' => 'jane@company.test']);
        $user->assignRole('Operator');
        $this->userinfo = ['sub' => 'someone-else', 'email' => 'jane@company.test'];

        $this->finishLogin($this->startLogin())->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_unknown_person_is_refused_when_auto_provisioning_is_off(): void
    {
        $this->idTokenClaims = ['email' => 'stranger@company.test'];

        $this->finishLogin($this->startLogin())
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'No OpenMES account is linked to your identity. Ask an administrator to create one for you.');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'stranger@company.test']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'login_failed']);
    }

    public function test_auto_provisioning_creates_an_account_with_the_default_role(): void
    {
        SsoSettings::save(['auto_provision' => true, 'default_role' => 'Supervisor']);
        $this->idTokenClaims = [
            'email' => 'new.person@company.test',
            'preferred_username' => 'new.person@corp.local',
            'name' => 'New Person',
        ];

        $this->finishLogin($this->startLogin())->assertRedirect(route('supervisor.dashboard'));

        $user = User::where('email', 'new.person@company.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('new.person', $user->username);
        $this->assertSame('New Person', $user->name);
        $this->assertSame('subject-1', $user->sso_subject);
        $this->assertTrue($user->hasRole('Supervisor'));
        $this->assertFalse($user->force_password_change);
    }

    public function test_auto_provisioning_picks_a_free_username(): void
    {
        SsoSettings::save(['auto_provision' => true]);
        User::factory()->create(['username' => 'jdoe']);
        $this->idTokenClaims = ['email' => 'john@company.test', 'preferred_username' => 'jdoe'];

        $this->finishLogin($this->startLogin());

        $this->assertSame('jdoe-2', User::where('email', 'john@company.test')->value('username'));
    }

    public function test_a_provider_user_named_admin_does_not_become_the_local_admin(): void
    {
        SsoSettings::save(['auto_provision' => true]);
        $admin = User::factory()->create(['username' => 'admin', 'email' => 'admin@openmes.local']);
        $admin->assignRole('Admin');
        $this->idTokenClaims = ['email' => 'someone@company.test', 'preferred_username' => 'admin'];

        $this->finishLogin($this->startLogin());

        $this->assertAuthenticated();
        $this->assertNotSame($admin->id, auth()->id());
        $this->assertFalse(auth()->user()->hasRole('Admin'));
        $this->assertSame('admin-2', auth()->user()->username);
    }

    public function test_an_explicitly_unverified_email_is_not_used_for_linking(): void
    {
        $user = User::factory()->create(['email' => 'jane@company.test']);
        $user->assignRole('Admin');
        $this->idTokenClaims = ['email' => 'jane@company.test', 'email_verified' => false];

        $this->finishLogin($this->startLogin())->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull($user->fresh()->sso_subject);
    }

    public function test_an_account_bound_to_another_identity_is_not_rebound(): void
    {
        $user = User::factory()->create(['email' => 'jane@company.test']);
        $user->forceFill(['sso_subject' => 'original-subject'])->save();
        $this->idTokenClaims = ['email' => 'jane@company.test'];

        $this->finishLogin($this->startLogin())->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame('original-subject', $user->fresh()->sso_subject);
    }

    public function test_email_domain_allow_list_is_enforced(): void
    {
        SsoSettings::save(['auto_provision' => true, 'allowed_domains' => 'company.test']);
        $this->idTokenClaims = ['email' => 'guest@elsewhere.test'];

        $this->finishLogin($this->startLogin())
            ->assertSessionHas('error', 'Your account is not allowed to sign in to OpenMES.');

        $this->assertGuest();
    }

    public function test_local_two_factor_is_still_required(): void
    {
        $user = User::factory()->create(['email' => 'jane@company.test', 'two_factor_enabled' => true]);
        $this->idTokenClaims = ['email' => 'jane@company.test'];

        $this->finishLogin($this->startLogin())->assertRedirect(route('two-factor.challenge'));

        $this->assertGuest();
        $this->assertSame($user->id, session('2fa_user_id'));
    }

    public function test_a_mismatched_state_is_rejected(): void
    {
        $this->startLogin();
        $this->idTokenClaims = ['email' => 'jane@company.test'];
        User::factory()->create(['email' => 'jane@company.test']);

        $this->finishLogin('forged-state')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Your sign-in attempt expired. Please try again.');

        $this->assertGuest();
        Http::assertNotSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/token'));
    }

    public function test_a_state_cannot_be_replayed(): void
    {
        User::factory()->create(['email' => 'jane@company.test']);
        $this->idTokenClaims = ['email' => 'jane@company.test'];
        $state = $this->startLogin();

        $this->finishLogin($state);
        $this->assertAuthenticated();
        auth()->logout();

        $this->finishLogin($state)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_tampered_id_token_signature_is_rejected(): void
    {
        User::factory()->create(['email' => 'jane@company.test']);
        $state = $this->startLogin();
        $token = $this->signedIdToken([
            'iss' => self::ISSUER, 'aud' => self::CLIENT_ID, 'sub' => 'subject-1',
            'exp' => time() + 300, 'nonce' => $this->lastNonce, 'email' => 'jane@company.test',
        ]);
        [$h, , $s] = explode('.', $token);
        $forgedPayload = rtrim(strtr(base64_encode(json_encode([
            'iss' => self::ISSUER, 'aud' => self::CLIENT_ID, 'sub' => 'attacker',
            'exp' => time() + 300, 'nonce' => $this->lastNonce, 'email' => 'jane@company.test',
        ])), '+/', '-_'), '=');
        $this->tokenOverride = [['id_token' => "{$h}.{$forgedPayload}.{$s}"]];

        $this->finishLogin($state)
            ->assertSessionHas('error', 'The identity provider response could not be verified.');

        $this->assertGuest();
    }

    public function test_an_unsigned_id_token_is_rejected(): void
    {
        User::factory()->create(['email' => 'jane@company.test']);
        $state = $this->startLogin();
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $this->tokenOverride = [['id_token' => $b64('{"alg":"none"}').'.'.$b64(json_encode([
            'iss' => self::ISSUER, 'aud' => self::CLIENT_ID, 'sub' => 's', 'exp' => time() + 300,
            'nonce' => $this->lastNonce, 'email' => 'jane@company.test',
        ])).'.']];

        $this->finishLogin($state);

        $this->assertGuest();
    }

    #[DataProvider('badClaims')]
    public function test_id_token_claims_are_checked(array $override): void
    {
        User::factory()->create(['email' => 'jane@company.test']);
        $this->idTokenClaims = array_merge(['email' => 'jane@company.test'], $override);

        $this->finishLogin($this->startLogin())
            ->assertSessionHas('error', 'The identity provider response could not be verified.');

        $this->assertGuest();
    }

    public static function badClaims(): array
    {
        return [
            'wrong issuer' => [['iss' => 'https://evil.example.test']],
            'wrong audience' => [['aud' => 'some-other-client']],
            'expired' => [['exp' => time() - 3600]],
            'wrong nonce' => [['nonce' => 'replayed-nonce']],
        ];
    }

    public function test_provider_error_is_shown_on_the_login_screen(): void
    {
        $state = $this->startLogin();

        $this->get('/auth/sso/callback?'.http_build_query(['error' => 'access_denied', 'state' => $state]))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Sign-in was cancelled or refused by the identity provider.');
    }

    public function test_token_endpoint_failure_is_handled(): void
    {
        $state = $this->startLogin();
        $this->tokenOverride = [['error' => 'invalid_grant'], 400];

        $this->finishLogin($state)
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Sign-in with the identity provider failed. Please try again.');
    }

    public function test_malformed_callback_is_answered_on_the_login_screen(): void
    {
        $this->get('/auth/sso/callback')->assertRedirect(route('login'));
        $this->get('/auth/sso/callback?state=x')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_unreachable_provider_is_handled_on_redirect(): void
    {
        SsoSettings::save(['issuer' => 'https://down.example.test']);
        Http::fake(['down.example.test/*' => Http::response('', 503)]);

        $this->get('/auth/sso/redirect')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');
    }

    public function test_sso_routes_are_closed_when_disabled(): void
    {
        SsoSettings::save(['enabled' => false]);

        $this->get('/auth/sso/redirect')->assertRedirect(route('login'));
        $this->get('/auth/sso/callback?code=x&state=y')->assertRedirect(route('login'));
        Http::assertNothingSent();
    }

    public function test_signed_in_users_cannot_start_sso_again(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/auth/sso/redirect')->assertRedirect();
        Http::assertNothingSent();
    }
}
