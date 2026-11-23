<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoaDocumentVersion extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
        'tenant_id',
        'coa_document_id',
        'version',
        'status',
        'snapshot',
        'change_reason',
        'prepared_by_id',
        'prepared_at',
        'approved_by_id',
        'approved_at',
        'issued_by_id',
        'issued_at',
        'voided_by_id',
        'voided_at',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'snapshot' => 'array',
            'prepared_at' => 'datetime',
            'approved_at' => 'datetime',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(CoaDocument::class, 'coa_document_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_id');
    }
}
