<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Rich work-instruction media (image, PDF or video) attached to a process
 * template step. The file lives on the private disk under a server-generated
 * name; it is streamed to operators through an authenticated endpoint, never a
 * public URL. Hard-deleted (with disk cleanup) like its sibling
 * ProcessTemplatePhoto - a soft-deleted media row with a removed file is
 * meaningless.
 */
class TemplateStepMedia extends Model
{
    use HasFactory;

    protected $table = 'template_step_media';

    public const TYPE_IMAGE = 'image';

    public const TYPE_PDF = 'pdf';

    public const TYPE_VIDEO = 'video';

    public const TYPES = [self::TYPE_IMAGE, self::TYPE_PDF, self::TYPE_VIDEO];

    protected $fillable = [
        'process_template_id',
        'template_step_id',
        'media_type',
        'title',
        'storage_path',
        'original_name',
        'mime_type',
        'file_size',
        'sort_order',
        'uploaded_by_id',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (self $media) {
            Storage::delete($media->storage_path);
        });
    }

    public function processTemplate(): BelongsTo
    {
        return $this->belongsTo(ProcessTemplate::class);
    }

    public function templateStep(): BelongsTo
    {
        return $this->belongsTo(TemplateStep::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }
}
