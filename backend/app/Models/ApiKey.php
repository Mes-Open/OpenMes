<?php

namespace App\Models;

use App\Enums\ApiScope;
use App\Models\Concerns\HasTenant;
use App\Models\Concerns\SoftDeletesWithAudit;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A machine-to-machine API key for ERP integrations. Not user-bound: the key
 * itself is the credential. Only the SHA-256 hash of the secret is persisted;
 * the plaintext is returned once from issue() and never stored.
 *
 * @property array<int, string> $scopes
 * @property array<int, string>|null $ip_allowlist
 */
class ApiKey extends Model
{
    use HasFactory, HasTenant;
    use SoftDeletesWithAudit;

    /** Plaintext key prefix identifying OpenMES-issued keys. */
    public const PREFIX = 'omk_';

    protected $fillable = [
        'integration_config_id',
        'name',
        'key_prefix',
        'key_hash',
        'scopes',
        'ip_allowlist',
        'is_active',
        'last_used_at',
        'expires_at',
        'tenant_id',
    ];

    protected $hidden = [
        'key_hash',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'ip_allowlist' => 'array',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function integrationConfig(): BelongsTo
    {
        return $this->belongsTo(IntegrationConfig::class);
    }

    /**
     * Issue a new key. Returns the persisted model and the one-time plaintext
     * secret the caller must show the user immediately (it cannot be recovered).
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(array $attributes): array
    {
        $plaintext = self::PREFIX.Str::random(40);

        $key = static::create(array_merge($attributes, [
            'key_prefix' => substr($plaintext, 0, 12),
            'key_hash' => self::hashSecret($plaintext),
        ]));

        return [$key, $plaintext];
    }

    /**
     * Resolve a live key from its plaintext secret. Runs without the tenant
     * scope because authentication happens before any tenant is known — the
     * key itself determines the tenant.
     */
    public static function findByPlaintext(string $plaintext): ?self
    {
        if (! str_starts_with($plaintext, self::PREFIX)) {
            return null;
        }

        return static::withoutGlobalScope(TenantScope::class)
            ->where('key_hash', self::hashSecret($plaintext))
            ->first();
    }

    public static function hashSecret(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }

    /** Active, not expired — i.e. accepted by the authentication middleware. */
    public function isUsable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function hasScope(ApiScope|string $scope): bool
    {
        $value = $scope instanceof ApiScope ? $scope->value : $scope;

        return in_array($value, $this->scopes ?? [], true);
    }

    /** Whether the request's source IP is allowed (empty allowlist = any). */
    public function ipAllowed(?string $ip): bool
    {
        $allowlist = $this->ip_allowlist ?? [];

        if ($allowlist === [] || $ip === null) {
            return true;
        }

        return in_array($ip, $allowlist, true);
    }

    public function markUsed(): void
    {
        // Timestamp-only write; skip model events and updated_at churn.
        $this->forceFill(['last_used_at' => Carbon::now()])->saveQuietly();
    }
}
