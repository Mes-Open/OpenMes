<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateStep extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'process_template_id',
        'step_number',
        'name',
        'instruction',
        'estimated_duration_minutes',
        'workstation_id',
    ];

    protected function casts(): array
    {
        return [
            'step_number' => 'integer',
            'estimated_duration_minutes' => 'integer',
        ];
    }

    /**
     * Get the process template that owns this step.
     */
    public function processTemplate(): BelongsTo
    {
        return $this->belongsTo(ProcessTemplate::class);
    }

    /**
     * Get the workstation for this step.
     */
    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }
}
