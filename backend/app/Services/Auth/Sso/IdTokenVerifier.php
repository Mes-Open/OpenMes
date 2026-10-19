<?php

namespace App\Services\Auth\Sso;

/**
 * Verifies an OpenID Connect ID token (a signed JWT) without a JWT library:
 * RS256/384/512 against the provider's published JWKS, or HS256/384/512
 * against the client secret. The token's claims are only trusted after the
 * signature, issuer, audience, expiry and nonce all check out.
 */
class IdTokenVerifier
{
    /** Clock skew tolerated between OpenMES and the identity provider. */
    private const LEEWAY_SECONDS = 120;

    private const RSA_ALGORITHMS = [
        'RS256' => OPENSSL_ALGO_SHA256,
        'RS384' => OPENSSL_ALGO_SHA384,
        'RS512' => OPENSSL_ALGO_SHA512,
    ];

    private const HMAC_ALGORITHMS = [
        'HS256' => 'sha256',
        'HS384' => 'sha384',
        'HS512' => 'sha512',
    ];

    /**
     * @param  callable(bool): array  $jwks  returns the provider's JWKS; called
     *                                       again with `true` to refresh when a key id is unknown
     * @return array<string, mixed> the verified claims
     */
    public function verify(
        string $jwt,
        callable $jwks,
        string $issuer,
        string $clientId,
        ?string $clientSecret,
        string $nonce,
    ): array {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw $this->invalid('ID token is not a JWT');
        }
        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $header = json_decode(self::base64UrlDecode($encodedHeader), true);
        $claims = json_decode(self::base64UrlDecode($encodedPayload), true);
        $signature = self::base64UrlDecode($encodedSignature);
        if (! is_array($header) || ! is_array($claims)) {
            throw $this->invalid('ID token header or payload is not JSON');
        }

        $alg = (string) ($header['alg'] ?? '');
        $signed = $encodedHeader.'.'.$encodedPayload;

        if (isset(self::RSA_ALGORITHMS[$alg])) {
            $kid = isset($header['kid']) ? (string) $header['kid'] : null;
            $pem = $this->findRsaKey($jwks(false), $kid) ?? $this->findRsaKey($jwks(true), $kid);
            if ($pem === null) {
                throw $this->invalid('no matching RSA key in the provider JWKS');
            }
            if (openssl_verify($signed, $signature, $pem, self::RSA_ALGORITHMS[$alg]) !== 1) {
                throw $this->invalid('RSA signature does not verify');
            }
        } elseif (isset(self::HMAC_ALGORITHMS[$alg])) {
            if ($clientSecret === null || $clientSecret === '') {
                throw $this->invalid("{$alg} token but no client secret is configured");
            }
            $expected = hash_hmac(self::HMAC_ALGORITHMS[$alg], $signed, $clientSecret, true);
            if (! hash_equals($expected, $signature)) {
                throw $this->invalid('HMAC signature does not verify');
            }
        } else {
            // Includes "none": an unsigned token is never accepted.
            throw $this->invalid("unsupported signing algorithm \"{$alg}\"");
        }

        if (rtrim((string) ($claims['iss'] ?? ''), '/') !== rtrim($issuer, '/')) {
            throw $this->invalid('issuer mismatch');
        }

        $audiences = (array) ($claims['aud'] ?? []);
        if (! in_array($clientId, $audiences, true)) {
            throw $this->invalid('audience does not contain the client id');
        }
        if (count($audiences) > 1 && isset($claims['azp']) && $claims['azp'] !== $clientId) {
            throw $this->invalid('authorized party mismatch');
        }

        $now = time();
        if (! is_numeric($claims['exp'] ?? null) || (int) $claims['exp'] < $now - self::LEEWAY_SECONDS) {
            throw $this->invalid('token expired');
        }
        if (is_numeric($claims['nbf'] ?? null) && (int) $claims['nbf'] > $now + self::LEEWAY_SECONDS) {
            throw $this->invalid('token not valid yet');
        }

        if (! is_string($claims['nonce'] ?? null) || ! hash_equals($nonce, $claims['nonce'])) {
            throw $this->invalid('nonce mismatch');
        }

        if (! is_string($claims['sub'] ?? null) || $claims['sub'] === '') {
            throw $this->invalid('missing subject');
        }

        return $claims;
    }

    /** PEM public key for the JWKS entry matching `$kid` (or the only RSA signing key). */
    private function findRsaKey(array $jwks, ?string $kid): ?string
    {
        $candidates = collect($jwks['keys'] ?? [])
            ->filter(fn ($key) => is_array($key)
                && ($key['kty'] ?? null) === 'RSA'
                && ($key['use'] ?? 'sig') === 'sig'
                && isset($key['n'], $key['e']));

        $key = $kid !== null
            ? $candidates->first(fn ($k) => ($k['kid'] ?? null) === $kid)
            : ($candidates->count() === 1 ? $candidates->first() : null);

        return $key ? self::rsaPublicKeyPem((string) $key['n'], (string) $key['e']) : null;
    }

    /** DER-encode an RSA modulus/exponent as a SubjectPublicKeyInfo PEM. */
    public static function rsaPublicKeyPem(string $n, string $e): string
    {
        $rsaPublicKey = self::derSequence(
            self::derInteger(self::base64UrlDecode($n)).self::derInteger(self::base64UrlDecode($e))
        );
        // AlgorithmIdentifier: rsaEncryption OID 1.2.840.113549.1.1.1 + NULL.
        $algorithm = hex2bin('300d06092a864886f70d0101010500');
        $spki = self::derSequence($algorithm.self::derBitString($rsaPublicKey));

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($spki), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }

    public static function base64UrlDecode(string $value): string
    {
        $padded = strtr($value, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);

        return (string) base64_decode($padded, true);
    }

    private static function derInteger(string $bytes): string
    {
        $bytes = ltrim($bytes, "\0");
        if ($bytes === '' || ord($bytes[0]) > 0x7F) {
            $bytes = "\0".$bytes; // keep it positive
        }

        return "\x02".self::derLength(strlen($bytes)).$bytes;
    }

    private static function derSequence(string $content): string
    {
        return "\x30".self::derLength(strlen($content)).$content;
    }

    private static function derBitString(string $content): string
    {
        return "\x03".self::derLength(strlen($content) + 1)."\0".$content;
    }

    private static function derLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }
        $bytes = ltrim(pack('N', $length), "\0");

        return chr(0x80 | strlen($bytes)).$bytes;
    }

    private function invalid(string $detail): SsoException
    {
        return new SsoException(__('The identity provider response could not be verified.'), 'ID token: '.$detail);
    }
}
