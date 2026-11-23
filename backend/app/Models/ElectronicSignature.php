<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ElectronicSignature extends Model
{
    use HasFactory, HasTenant;

    public const MEANING_BATCH_RELEASE = 'BATCH_RELEASE';

    public const MEANING_COA_APPROVAL = 'COA_APPROVAL';

    public const MEANING_COA_ISSUE = 'COA_ISSUE';

    public const MEANING_COA_VOID = 'COA_VOID';

    protected $fillable = [
        'tenant_id',
        'signable_type',
        'signable_id',
        'user_id',
        'meaning',
        'signed_at',
        'record_hash',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function signable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
