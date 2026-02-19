<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'original_name',
        'storage_path',
        'mime_type',
        'file_size',
        'uploaded_by_id',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    /**
     * Get the user who uploaded this attachment.
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    /**
     * Get the owning entity model (polymorphic).
     */
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get a human-readable file size string.
     */
    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size ?? 0;

        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1_048_576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1_048_576, 1) . ' MB';
    }
}
