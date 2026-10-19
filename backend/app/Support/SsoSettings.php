<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Single sign-on (OpenID Connect) configuration, stored as one JSON row in
 * system_settings. The client secret is kept encrypted with the app key and
 * never leaves the server: the settings form only learns whether one is set.
 *
 * Kept out of the configuration export/import on purpose — a file that can
 * point every login at a different identity provider is not a "setting" to
 * carry between installations.
 */
class SsoSettings
{
    public const SETTING_KEY = 'sso_oidc';

    public const DEFAULTS = [
        'enabled' => false,
        'provider_name' => 'Company SSO',
        'issuer' => '',
        'client_id' => '',
        'scopes' => 'openid profile email',
        'username_claim' => 'preferred_username',
        'auto_provision' => false,
        'default_role' => 'Operator',
        'link_by_email' => true,
        'allowed_domains' => '',
    ];

    /**
     * The stored configuration merged over the defaults — without the secret.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        $stored = self::stored();
        $settings = array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));
        $settings['has_client_secret'] = ! empty($stored['client_secret']);

        return $settings;
    }

    /** Whether the login screen offers SSO: switched on and minimally configured. */
    public static function enabled(): bool
    {
        $settings = self::all();

        return (bool) $settings['enabled']
            && $settings['issuer'] !== ''
            && $settings['client_id'] !== '';
    }

    public static function clientSecret(): ?string
    {
        $encrypted = self::stored()['client_secret'] ?? null;
        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            // Encrypted under a different APP_KEY (restored backup): unusable,
            // the admin has to enter it again.
            return null;
        }
    }

    /**
     * Persist the form. A blank `client_secret` keeps the stored one, so the
     * admin does not have to re-enter it on every save; `clear_client_secret`
     * removes it (public clients that rely on PKCE alone).
     *
     * @param  array<string, mixed>  $data
     */
    public static function save(array $data): void
    {
        $stored = self::stored();
        $settings = array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));

        foreach (array_keys(self::DEFAULTS) as $key) {
            if (array_key_exists($key, $data)) {
                $settings[$key] = is_string($data[$key]) ? trim($data[$key]) : $data[$key];
            }
        }
        $settings['issuer'] = rtrim((string) $settings['issuer'], '/');
        $settings['allowed_domains'] = implode(', ', self::domainsFrom((string) ($settings['allowed_domains'] ?? '')));

        $secret = $stored['client_secret'] ?? null;
        if (! empty($data['clear_client_secret'])) {
            $secret = null;
        } elseif (isset($data['client_secret']) && trim((string) $data['client_secret']) !== '') {
            $secret = Crypt::encryptString(trim((string) $data['client_secret']));
        }
        $settings['client_secret'] = $secret;

        DB::table('system_settings')->updateOrInsert(
            ['key' => self::SETTING_KEY],
            [
                'value' => json_encode($settings),
                'description' => 'Single sign-on (OpenID Connect) configuration',
                'updated_at' => now(),
            ],
        );
    }

    /**
     * The email domains allowed to sign in, lower-cased; empty means any.
     *
     * @return list<string>
     */
    public static function allowedDomains(): array
    {
        return self::domainsFrom((string) self::all()['allowed_domains']);
    }

    /** @return list<string> */
    private static function domainsFrom(string $value): array
    {
        return collect(preg_split('/[\s,;]+/', strtolower($value)) ?: [])
            ->map(fn ($d) => ltrim(trim($d), '@'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private static function stored(): array
    {
        try {
            $raw = DB::table('system_settings')->where('key', self::SETTING_KEY)->value('value');
        } catch (\Throwable) {
            return [];
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
