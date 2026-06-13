<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScrapReason extends Model
{
    use HasFactory;
    use SoftDeletesWithAudit;

    /** 5M defect taxonomy (Ishikawa fishbone categories). */
    const CATEGORY_MATERIAL = 'material';

    const CATEGORY_MACHINE = 'machine';

    const CATEGORY_METHOD = 'method';

    const CATEGORY_MAN = 'man';

    const CATEGORY_ENVIRONMENT = 'environment';

    const CATEGORIES = [
        self::CATEGORY_MATERIAL,
        self::CATEGORY_MACHINE,
        self::CATEGORY_METHOD,
        self::CATEGORY_MAN,
        self::CATEGORY_ENVIRONMENT,
    ];

    protected $fillable = [
        'code',
        'name',
        'description',
        'category',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the scrap entries recorded against this reason.
     */
    public function scrapEntries(): HasMany
    {
        return $this->hasMany(ScrapEntry::class);
    }

    /**
     * Scope to get only active scrap reasons.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope ordering reasons the way operators expect to see them.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
