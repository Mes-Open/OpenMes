<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MachineTopic extends Model
{
    use HasFactory;

    const FORMAT_JSON  = 'json';
    const FORMAT_PLAIN = 'plain';
    const FORMAT_CSV   = 'csv';
    const FORMAT_HEX   = 'hex';

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
     * Check if an incoming MQTT topic matches this pattern (supports + and # wildcards).
     */
    public function matchesTopic(string $topic): bool
    {
        $pattern = $this->topic_pattern;

        if ($pattern === $topic) {
            return true;
        }

        // # matches everything at this level and below
        if (str_ends_with($pattern, '#')) {
            $prefix = rtrim(substr($pattern, 0, -1), '/');
            return $prefix === '' || str_starts_with($topic, $prefix . '/') || $topic === $prefix;
        }

        // + matches exactly one level
        $patternParts = explode('/', $pattern);
        $topicParts   = explode('/', $topic);

        if (count($patternParts) !== count($topicParts)) {
            return false;
        }

        foreach ($patternParts as $i => $part) {
            if ($part !== '+' && $part !== $topicParts[$i]) {
                return false;
            }
        }

        return true;
    }
}
