<?php

namespace App\Models;

use App\Models\Concerns\HasCustomFields;
use App\Models\Concerns\SoftDeletesWithAudit;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tool extends Model
{
    use Auditable, HasCustomFields, HasFactory;
    use SoftDeletesWithAudit;

    const STATUS_AVAILABLE = 'available';

    const STATUS_IN_USE = 'in_use';

    const STATUS_MAINTENANCE = 'maintenance';

    const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'code',
        'name',
        'description',
        'workstation_type_id',
        'status',
        'next_service_at',
    ];

    protected function casts(): array
    {
        return [
            'next_service_at' => 'date',
        ];
    }

    /**
     * Get the workstation type this tool belongs to.
     */
    public function workstationType(): BelongsTo
    {
        return $this->belongsTo(WorkstationType::class);
    }

    /**
     * Get the maintenance events for this tool.
     */
    public function maintenanceEvents(): HasMany
    {
        return $this->hasMany(MaintenanceEvent::class);
    }
}
