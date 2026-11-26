<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A bearer token for the public COA verification page. Only the SHA-256 hash is
 * stored for lookup; the raw token is encrypted at rest so the admin/PDF can
 * still render a link after the document is issued.
 */
class CoaVerificationToken extends Model
{
    use HasFactory, HasTenant;

    public const TOKEN_BYTES = 32;

    protected $fillable = [
        'coa_document_id',
        'token_hash',
        'token_encrypted',
        'issued_at',
        'revoked_at',
        'revoke_reason',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
            'token_encrypted' => 'encrypted',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(CoaDocument::class, 'coa_document_id');
    }

    public static function issueFor(CoaDocument $document, ?Carbon $issuedAt = null): self
    {
        $raw = bin2hex(random_bytes(self::TOKEN_BYTES));

        return static::create([
            'coa_document_id' => $document->id,
            'token_hash' => hash('sha256', $raw),
            'token_encrypted' => $raw,
            'issued_at' => $issuedAt ?? now(),
        ]);
    }

    /**
     * Reuse the active link for an already-issued document; create one only when
     * no active token exists (e.g. records issued before this model shipped).
     */
    public static function ensureFor(CoaDocument $document): ?self
    {
        if (! $document->isIssued()) {
            return null;
        }

        return static::query()
            ->where('coa_document_id', $document->id)
            ->whereNull('revoked_at')
            ->latest('issued_at')
            ->first() ?? self::issueFor($document, $document->issued_at);
    }

    public static function findByRawToken(string $raw): ?self
    {
        return static::query()
            ->where('token_hash', hash('sha256', $raw))
            ->first();
    }

    public static function revokeForDocument(CoaDocument $document, string $reason): int
    {
        return static::query()
            ->where('coa_document_id', $document->id)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'revoke_reason' => $reason,
            ]);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    public function publicUrl(): ?string
    {
        $raw = $this->token_encrypted;

        return is_string($raw) && $raw !== ''
            ? route('coa.verify', ['token' => $raw])
            : null;
    }

    public function shortHash(): string
    {
        return substr((string) $this->token_hash, 0, 12);
    }
}
