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
 * A one-time enrollment code an admin generates so a sensor can self-register.
 * The device presents it once at first contact and receives a DeviceToken; the
 * code is then spent. Only the SHA-256 hash of the code is persisted — the
 * plaintext is returned once from issue() and never stored. Mirrors ApiKey.
 */
class DevicePairingCode extends Model
{
    use HasFactory, HasTenant;
    use SoftDeletesWithAudit;

    /** Plaintext prefix identifying OpenMES device pairing codes. */
    public const PREFIX = 'omp_';

    /** Default lifetime of a freshly generated code. */
    public const DEFAULT_TTL_MINUTES = 15;

    protected $fillable = [
        'name',
        'code_prefix',
        'code_hash',
        'line_id',
        'workstation_id',
        'is_active',
        'expires_at',
        'used_at',
        'used_by_connection_id',
        'tenant_id',
    ];

    protected $hidden = [
        'code_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    public function usedByConnection(): BelongsTo
    {
        return $this->belongsTo(MachineConnection::class, 'used_by_connection_id');
    }

    /**
     * Generate a new code. Returns the persisted model and the one-time plaintext
     * the caller must show the admin immediately (it cannot be recovered).
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(array $attributes): array
    {
        $plaintext = self::PREFIX.Str::random(32);

        $code = static::create(array_merge([
            'expires_at' => Carbon::now()->addMinutes(self::DEFAULT_TTL_MINUTES),
        ], $attributes, [
            'code_prefix' => substr($plaintext, 0, 12),
            'code_hash' => self::hashSecret($plaintext),
        ]));

        return [$code, $plaintext];
    }

    /**
     * Resolve a code from its plaintext. Runs without the tenant scope because a
     * self-enrolling device has no session — the code itself carries the tenant.
     */
    public static function findByPlaintext(string $plaintext): ?self
    {
        if (! str_starts_with($plaintext, self::PREFIX)) {
            return null;
        }

        return static::withoutGlobalScope(TenantScope::class)
            ->where('code_hash', self::hashSecret($plaintext))
            ->first();
    }

    public static function hashSecret(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }

    /** Active, unused, and not expired — i.e. still redeemable at enrollment. */
    public function isRedeemable(): bool
    {
        if (! $this->is_active || $this->used_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function markRedeemed(MachineConnection $connection): void
    {
        $this->forceFill([
            'used_at' => Carbon::now(),
            'used_by_connection_id' => $connection->id,
            'is_active' => false,
        ])->saveQuietly();
    }
}
