<?php

namespace App\Models;

use App\Models\Concerns\HasCustomFields;
use App\Models\Concerns\HasModuleRelations;
use App\Models\Concerns\SoftDeletesWithAudit;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Worker extends Model
{
    use Auditable, HasCustomFields, HasFactory, HasModuleRelations;
    use SoftDeletesWithAudit;

    /** Supported compensation modes for per-worker pay. */
    public const PAY_TYPES = ['hourly', 'weekly', 'piece_rate'];

    protected $fillable = [
        'personnel_class_id',
        'code',
        'name',
        'email',
        'phone',
        'crew_id',
        'wage_group_id',
        'pay_type',
        'pay_rate',
        'pay_currency',
        'workstation_id',
        'is_active',
        'is_logistics',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_logistics' => 'boolean',
            'pay_rate' => 'decimal:4',
        ];
    }

    /**
     * Get the workstation this worker is assigned to.
     */
    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    /*
     * crew(), wageGroup(), personnelClass(), skills() and absences() are NOT
     * declared here on purpose. The entities behind them ship as an optional
     * module, and a relation to a class this installation does not have would
     * fatal the moment anything touched it.
     *
     * The module adds them back at boot with Model::resolveRelationUsing(), so
     * `$worker->crew` works exactly as before wherever the module is installed
     * and simply does not exist where it is not.
     */

    /**
     * Get the user account linked to this worker.
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * Scope to get only active workers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to logistics operators / forklift drivers — the workers eligible to
     * perform physical pallet movements (#103).
     */
    public function scopeLogistics($query)
    {
        return $query->where('is_logistics', true);
    }

    /** Children soft-deleted/restored together with this model (mirrors DB FK cascades). */
    public function softDeleteCascades(): array
    {
        return [
            [\App\Models\EmployeeActivity::class, 'worker_id'],
        ];
    }
}
