<?php

namespace App\Models;

use App\Enums\EngineeringDocumentLifecycle;
use App\Enums\EngineeringPackageType;
use App\Models\Concerns\SoftDeletesWithAudit;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Engineering CAD document (#179). Associated with one of a fixed set of owner
 * entities via (entity_type, entity_id) — a short allowlisted key rather than a
 * FQCN, so it stays stable and matches the REST API and avoids a global morph map
 * that would change how the existing Attachment model stores its type.
 *
 * The file lives on the private disk under a server-generated path. Released
 * documents are immutable. Soft-deletable (Admin -> Trash); the disk file is
 * removed when the row is force-deleted.
 */
class EngineeringDocument extends Model
{
    use Auditable, HasFactory, SoftDeletesWithAudit;

    /** entity_type key => owner model class. The only owners a document may attach to. */
    public const ENTITY_TYPES = [
        'material' => Material::class,
        'product_type' => ProductType::class,
        'product_revision' => ProductRevision::class,
        'subassembly' => Subassembly::class,
        'process_template' => ProcessTemplate::class,
        'template_step' => TemplateStep::class,
    ];

    protected $fillable = [
        'entity_type', 'entity_id',
        'original_filename', 'package_type', 'document_type', 'mime_type',
        'file_size', 'extracted_size', 'entry_point',
        'revision', 'checksum', 'storage_path',
        'lifecycle_status', 'effective_from', 'effective_to',
        'released_at', 'released_by_id', 'uploaded_by_id',
    ];

    protected function casts(): array
    {
        return [
            'package_type' => EngineeringPackageType::class,
            'lifecycle_status' => EngineeringDocumentLifecycle::class,
            'file_size' => 'integer',
            'extracted_size' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'released_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Remove the stored file and any extracted interactive package on hard delete.
        static::forceDeleted(function (EngineeringDocument $doc) {
            $disk = Storage::disk($doc->disk());
            if ($doc->storage_path) {
                $disk->delete($doc->storage_path);
            }
            $disk->deleteDirectory("engineering/interactive/{$doc->id}");
        });
    }

    public function disk(): string
    {
        return config('engineering.disk', 'local');
    }

    /** Resolve the owning model from the short entity_type key. */
    public function entity(): ?Model
    {
        $class = self::ENTITY_TYPES[$this->entity_type] ?? null;

        return $class ? $class::find($this->entity_id) : null;
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_id');
    }

    public function isReleased(): bool
    {
        return $this->lifecycle_status === EngineeringDocumentLifecycle::Released;
    }

    /** Released documents are immutable — no re-upload, edit or re-release. */
    public function isImmutable(): bool
    {
        return $this->lifecycle_status?->isImmutable() ?? false;
    }

    public function scopeForEntity(Builder $q, string $type, int $id): Builder
    {
        return $q->where('entity_type', $type)->where('entity_id', $id);
    }
}
