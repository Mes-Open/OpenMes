<?php

namespace App\Services\Auth\Sso;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The OpenID Connect authorization-code flow (with PKCE) against any standard
 * provider — Keycloak, Microsoft Entra ID / ADFS, Google Workspace, Authentik,
 * Okta, … Everything is discovered from the issuer's
 * `/.well-known/openid-configuration`, so the admin only enters the issuer
 * URL, the client id and (for confidential clients) the secret.
 *
 * The issuer is configured by an administrator only and is expected to live
 * on the company network — that is the point of the feature — so private
 * addresses are deliberately allowed.
 */
class OidcClient
{
    private const TIMEOUT_SECONDS = 10;

    private const CACHE_SECONDS = 3600;

    public function __construct(private readonly IdTokenVerifier $verifier) {}

    /**
     * The provider metadata. Cached; `$fresh` bypasses the cache (the settings
     * form uses it to prove the issuer answers).
     *
     * @return array<string, mixed>
     */
    public function discover(string $issuer, bool $fresh = false): array
    {
        $issuer = rtrim($issuer, '/');
        $cacheKey = 'sso.discovery.'.sha1($issuer);
        if (! $fresh && is_array($cached = Cache::get($cacheKey))) {
            return $cached;
        }

        $url = $issuer.'/.well-known/openid-configuration';
        $metadata = $this->getJson($url, 'discovery document');

        foreach (['issuer', 'authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $required) {
            if (! is_string($metadata[$required] ?? null) || $metadata[$required] === '') {
                throw new SsoException(
                    __('Single sign-on is not available right now. Please try again later or sign in with your password.'),
                    "discovery document has no {$required}",
                );
            }
        }

        Cache::put($cacheKey, $metadata, self::CACHE_SECONDS);

        return $metadata;
    }

    /**
     * Where to send the browser, plus the one-time values the callback must
     * check. The caller keeps `state`, `nonce` and `code_verifier` in the session.
     *
     * @return array{url: string, state: string, nonce: string, code_verifier: string}
     */
    public function authorizationRequest(array $metadata, string $clientId, string $scopes, string $redirectUri): array
    {
        $state = Str::random(40);
        $nonce = Str::random(40);
        $verifier = Str::random(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => $scopes,
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);

        $endpoint = $metadata['authorization_endpoint'];
        $separator = str_contains($endpoint, '?') ? '&' : '?';

        return [
            'url' => $endpoint.$separator.$query,
            'state' => $state,
            'nonce' => $nonce,
            'code_verifier' => $verifier,
        ];
    }

    /**
     * Exchange the authorization code and return the verified identity claims
     * (ID token, enriched by the userinfo endpoint when the provider has one).
     *
     * @return array<string, mixed>
     */
    public function completeLogin(
        array $metadata,
        string $clientId,
        ?string $clientSecret,
        string $code,
        string $codeVerifier,
        string $redirectUri,
        string $nonce,
    ): array {
        $form = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'code_verifier' => $codeVerifier,
        ];
        if ($clientSecret !== null && $clientSecret !== '') {
            $form['client_secret'] = $clientSecret;
        }

        try {
            $response = Http::asForm()->acceptJson()->timeout(self::TIMEOUT_SECONDS)
                ->post($metadata['token_endpoint'], $form);
        } catch (\Throwable $e) {
            throw $this->unavailable('token endpoint unreachable: '.$e->getMessage());
        }

        $tokens = $response->json();
        if (! $response->successful() || ! is_array($tokens) || ! is_string($tokens['id_token'] ?? null)) {
            // The provider's error code (never the response body: it may echo secrets).
            $error = is_array($tokens) ? (string) ($tokens['error'] ?? '') : '';
            throw new SsoException(
                __('Sign-in with the identity provider failed. Please try again.'),
                "token endpoint returned HTTP {$response->status()} {$error}",
            );
        }

        $claims = $this->verifier->verify(
            $tokens['id_token'],
            fn (bool $refresh) => $this->jwks($metadata['jwks_uri'], $refresh),
            (string) $metadata['issuer'],
            $clientId,
            $clientSecret,
            $nonce,
        );

        // Providers such as Entra ID or Keycloak (by default) keep email and
        // profile out of the ID token; the userinfo endpoint fills them in.
        $endpoint = $metadata['userinfo_endpoint'] ?? null;
        if (is_string($endpoint) && $endpoint !== '' && is_string($tokens['access_token'] ?? null)) {
            $userinfo = $this->userinfo($endpoint, $tokens['access_token']);
            // Only for the same subject; the signed ID token wins on any overlap.
            if (($userinfo['sub'] ?? null) === $claims['sub']) {
                $claims = array_merge($userinfo, $claims);
            }
        }

        return $claims;
    }

    /** @return array<string, mixed> */
    private function jwks(string $uri, bool $refresh): array
    {
        $cacheKey = 'sso.jwks.'.sha1($uri);
        if (! $refresh && is_array($cached = Cache::get($cacheKey))) {
            return $cached;
        }

        $jwks = $this->getJson($uri, 'JWKS');
        Cache::put($cacheKey, $jwks, self::CACHE_SECONDS);

        return $jwks;
    }

    /** @return array<string, mixed> */
    private function userinfo(string $endpoint, string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)->acceptJson()->timeout(self::TIMEOUT_SECONDS)->get($endpoint);
        } catch (\Throwable) {
            return []; // the ID token alone is still a verified identity
        }

        $data = $response->successful() ? $response->json() : null;

        return is_array($data) ? $data : [];
    }

    /** @return array<string, mixed> */
    private function getJson(string $url, string $what): array
    {
        try {
            /** @var Response $response */
            $response = Http::acceptJson()->timeout(self::TIMEOUT_SECONDS)->get($url);
        } catch (\Throwable $e) {
            throw $this->unavailable("{$what} unreachable at {$url}: ".$e->getMessage());
        }

        $data = $response->json();
        if (! $response->successful() || ! is_array($data)) {
            throw $this->unavailable("{$what} at {$url} returned HTTP {$response->status()}");
        }

        return $data;
    }

    private function unavailable(string $detail): SsoException
    {
        return new SsoException(
            __('Single sign-on is not available right now. Please try again later or sign in with your password.'),
            $detail,
        );
    }
}
