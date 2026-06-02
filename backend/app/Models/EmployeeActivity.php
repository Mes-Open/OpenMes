<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tachograph-style employee activity. Represents a single time block on a
 * worker's daily timeline (work / break / rest / setup / meeting / etc).
 */
class EmployeeActivity extends Model
{
    use HasFactory, HasTenant;

    public const TYPES = [
        'work', 'break', 'rest', 'travel', 'setup',
        'meeting', 'training', 'maint', 'qc', 'off', 'custom',
    ];

    /** Default color + icon per type — mirrors the design tachograph palette. */
    public const TYPE_META = [
        'work'     => ['color' => '#5fb3e0', 'short' => 'WORK', 'label' => 'Work'],
        'break'    => ['color' => '#e9c46a', 'short' => 'BRK',  'label' => 'Break'],
        'rest'     => ['color' => '#e76f51', 'short' => 'REST', 'label' => 'Rest'],
        'travel'   => ['color' => '#264653', 'short' => 'TRV',  'label' => 'Travel'],
        'setup'    => ['color' => '#f4a261', 'short' => 'SET',  'label' => 'Setup / changeover'],
        'meeting'  => ['color' => '#a78bfa', 'short' => 'MTG',  'label' => 'Meeting'],
        'training' => ['color' => '#3ecf8e', 'short' => 'TRN',  'label' => 'Training'],
        'maint'    => ['color' => '#dc2626', 'short' => 'MNT',  'label' => 'Maintenance'],
        'qc'       => ['color' => '#7c3aed', 'short' => 'QC',   'label' => 'Quality check'],
        'off'      => ['color' => '#cfccc4', 'short' => 'OFF',  'label' => 'Off shift'],
        'custom'   => ['color' => '#06b6d4', 'short' => 'CST',  'label' => 'Custom'],
    ];

    protected $fillable = [
        'worker_id', 'type', 'custom_code', 'label',
        'starts_at', 'ends_at',
        'work_order_id', 'line_id', 'step_name',
        'notes', 'created_by_id', 'tenant_id',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    public function customType(): BelongsTo
    {
        return $this->belongsTo(EmployeeActivityCustomType::class, 'custom_code', 'code');
    }

    /** Duration in minutes. */
    public function durationMinutes(): int
    {
        return max(0, $this->starts_at->diffInMinutes($this->ends_at));
    }
}
