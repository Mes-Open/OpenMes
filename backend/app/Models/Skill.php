<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Skill extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
    ];

    /**
     * Get the workers who hold this skill / certification.
     */
    public function workers(): BelongsToMany
    {
        return $this->belongsToMany(Worker::class, 'worker_skills')
            ->withPivot([
                'level',
                'cert_level',
                'certified_from',
                'certified_until',
                'certified_by_id',
                'cert_notes',
            ])
            ->withTimestamps();
    }
}
