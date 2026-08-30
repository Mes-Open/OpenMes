<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Models\Concerns\SoftDeletesWithAudit;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The long-lived credential of a self-enrolled HTTP sensor, bound to its
 * MachineConnection. Not user-bound — the token is the credential. Has no
 * expiry by design; the only revoke path is a soft delete from the admin panel.
 * Only the SHA-256 hash is stored; the plaintext is returned once at enrollment.
 */
class DeviceToken extends Model
{
    use HasFactory, HasTenant;
    use SoftDeletesWithAudit;

    /** Plaintext prefix identifying OpenMES device tokens. */
    public const PREFIX = 'omd_';

    protected $fillable = [
        'machine_connection_id',
        'token_prefix',
        'token_hash',
        'is_active',
        'last_used_at',
        'tenant_id',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function machineConnection(): BelongsTo
    {
        return $this->belongsTo(MachineConnection::class);
    }

    /**
     * Issue a token for a connection. Returns the model and the one-time
     * plaintext secret the caller must return to the device immediately.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(array $attributes): array
    {
        $plaintext = self::PREFIX.Str::random(40);

        $token = static::create(array_merge($attributes, [
            'token_prefix' => substr($plaintext, 0, 12),
            'token_hash' => self::hashSecret($plaintext),
        ]));

        return [$token, $plaintext];
    }

    /**
     * Resolve a live token from its plaintext. Runs without the tenant scope
     * because authentication precedes any tenant — the token carries the tenant.
     */
    public static function findByPlaintext(string $plaintext): ?self
    {
        if (! str_starts_with($plaintext, self::PREFIX)) {
            return null;
        }

        return static::withoutGlobalScope(TenantScope::class)
            ->where('token_hash', self::hashSecret($plaintext))
            ->first();
    }

    public static function hashSecret(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }

    /** Active — i.e. accepted by the device auth middleware (no expiry). */
    public function isUsable(): bool
    {
        return (bool) $this->is_active;
    }

    public function markUsed(): void
    {
        // Timestamp-only write; skip model events and updated_at churn.
        $this->forceFill(['last_used_at' => Carbon::now()])->saveQuietly();
    }
}
