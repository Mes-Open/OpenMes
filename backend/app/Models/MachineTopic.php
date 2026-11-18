<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MachineTopic extends Model
{
    use HasFactory;
    use SoftDeletesWithAudit;

    const FORMAT_JSON = 'json';

    const FORMAT_PLAIN = 'plain';

    const FORMAT_CSV = 'csv';

    const FORMAT_HEX = 'hex';

    protected $fillable = [
        'machine_connection_id',
        'topic_pattern',
        'payload_format',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function machineConnection(): BelongsTo
    {
        return $this->belongsTo(MachineConnection::class);
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(TopicMapping::class)->orderBy('priority');
    }

    public function activeMappings(): HasMany
    {
        return $this->hasMany(TopicMapping::class)
            ->where('is_active', true)
            ->orderBy('priority');
    }

    /**
     * Whether an incoming MQTT topic matches this pattern, by the MQTT rules:
     * `+` stands for exactly one level, `#` (last level only) for any number
     * of levels including none, and the two combine (`plant/+/press/#`).
     * Wildcards never match a `$SYS`-style topic at the first level.
     */
    public function matchesTopic(string $topic): bool
    {
        $pattern = explode('/', $this->topic_pattern);
        $levels = explode('/', $topic);

        if (str_starts_with($topic, '$') && in_array($pattern[0], ['+', '#'], true)) {
            return false;
        }

        foreach ($pattern as $i => $part) {
            if ($part === '#') {
                return $i === count($pattern) - 1;
            }
            if (! array_key_exists($i, $levels)) {
                return false;
            }
            if ($part !== '+' && $part !== $levels[$i]) {
                return false;
            }
        }

        return count($pattern) === count($levels);
    }

    /** Children soft-deleted/restored together with this model (mirrors DB FK cascades). */
    public function softDeleteCascades(): array
    {
        return [
            [\App\Models\TopicMapping::class, 'machine_topic_id'],
        ];
    }
}
