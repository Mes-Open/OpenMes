<?php

namespace App\Services\Auth\Sso;

use App\Models\User;
use App\Support\SsoSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Turns verified identity-provider claims into an OpenMES account:
 *
 *  1. the account already bound to this provider subject (`sub`), else
 *  2. an existing account with the same, provider-verified email — bound now,
 *     when "link by email" is on, else
 *  3. a new account with the configured default role, when auto-provisioning
 *     is on.
 *
 * Never by username: a provider user called "admin" must not become the
 * local administrator.
 */
class SsoUserResolver
{
    private const USERNAME_MAX = 50;

    /** @param array<string, mixed> $claims */
    public function resolve(array $claims, array $settings): User
    {
        $subject = (string) $claims['sub'];
        $email = $this->email($claims);

        $allowed = SsoSettings::allowedDomains();
        if ($allowed !== []) {
            $domain = $email ? strtolower(Str::after($email, '@')) : null;
            if ($domain === null || ! in_array($domain, $allowed, true)) {
                throw new SsoException(
                    __('Your account is not allowed to sign in to OpenMES.'),
                    'email domain not in the allow-list',
                );
            }
        }

        $user = User::where('sso_subject', $subject)->first();
        if ($user) {
            return $user;
        }

        if ($email && ($settings['link_by_email'] ?? true)) {
            $user = User::whereRaw('LOWER(email) = ?', [strtolower($email)])->first();
            if ($user) {
                if ($user->sso_subject !== null) {
                    // Bound to another provider identity already: refuse
                    // rather than silently re-binding the account.
                    throw new SsoException(
                        __('This account is already linked to a different single sign-on identity.'),
                        "user #{$user->id} is bound to another subject",
                    );
                }
                $user->forceFill(['sso_subject' => $subject])->save();

                return $user;
            }
        }

        if (! ($settings['auto_provision'] ?? false)) {
            throw new SsoException(
                __('No OpenMES account is linked to your identity. Ask an administrator to create one for you.'),
                'no matching account and auto-provisioning is off',
            );
        }

        if (! $email) {
            throw new SsoException(
                __('Your identity provider did not share a verified email address, so no account can be created.'),
                'auto-provisioning needs a verified email claim',
            );
        }

        if (User::whereRaw('LOWER(email) = ?', [strtolower($email)])->exists()) {
            // Linking by email is off and the address belongs to a local account.
            throw new SsoException(
                __('No OpenMES account is linked to your identity. Ask an administrator to create one for you.'),
                'email already used by an unlinked local account',
            );
        }

        return DB::transaction(function () use ($claims, $settings, $subject, $email) {
            $username = $this->uniqueUsername($this->usernameCandidate($claims, $settings, $email));

            $user = User::create([
                'name' => $this->displayName($claims, $username),
                'username' => $username,
                'email' => $email,
                // Unknown to anyone: an SSO account signs in through the provider.
                'password' => Hash::make(Str::random(64)),
                'account_type' => 'user',
                'force_password_change' => false,
            ]);
            $user->forceFill(['sso_subject' => $subject, 'email_verified_at' => now()])->save();

            $role = (string) ($settings['default_role'] ?? 'Operator');
            if ($role !== 'Admin' && Role::where('name', $role)->where('guard_name', 'web')->exists()) {
                $user->assignRole($role);
            }

            return $user;
        });
    }

    /** The email, unless the provider says outright that it is unverified. */
    private function email(array $claims): ?string
    {
        $email = $claims['email'] ?? null;
        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        // Entra ID and some others omit email_verified; only an explicit
        // "false" (or "false" string) disqualifies the address.
        $verified = $claims['email_verified'] ?? null;
        if ($verified === false || $verified === 'false') {
            return null;
        }

        return mb_substr($email, 0, 255);
    }

    private function usernameCandidate(array $claims, array $settings, string $email): string
    {
        $claim = (string) ($settings['username_claim'] ?? 'preferred_username');
        $raw = $claims[$claim] ?? $claims['preferred_username'] ?? Str::before($email, '@');
        $raw = is_string($raw) ? $raw : Str::before($email, '@');

        // UPNs ("jane.doe@corp.local") make poor usernames: keep the local part.
        $raw = Str::before($raw, '@');
        $clean = preg_replace('/[^A-Za-z0-9._-]/', '', Str::ascii($raw)) ?? '';

        return $clean !== '' ? mb_substr($clean, 0, self::USERNAME_MAX) : 'sso-user';
    }

    private function uniqueUsername(string $base): string
    {
        $candidate = $base;
        $suffix = 1;
        while (User::withTrashed()->where('username', $candidate)->exists()) {
            $suffix++;
            $tail = '-'.$suffix;
            $candidate = mb_substr($base, 0, self::USERNAME_MAX - strlen($tail)).$tail;
        }

        return $candidate;
    }

    private function displayName(array $claims, string $fallback): string
    {
        $name = $claims['name'] ?? trim(($claims['given_name'] ?? '').' '.($claims['family_name'] ?? ''));
        $name = is_string($name) ? trim(strip_tags($name)) : '';

        return mb_substr($name !== '' ? $name : $fallback, 0, 255);
    }
}
