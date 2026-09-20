<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Internal durable source state. Access must be scoped through its connection. */
class MachineCounter extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_raw' => 'decimal:2', 'last_read_at' => 'datetime', 'configured_at' => 'datetime', 'is_simulated' => 'boolean', 'reset_required' => 'boolean'];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MachineConnection::class, 'machine_connection_id');
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(MachineTag::class, 'machine_tag_id');
    }

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(TopicMapping::class, 'topic_mapping_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(BatchStep::class, 'batch_step_id');
    }

    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(MachineCounterReading::class);
    }
}
