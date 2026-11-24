<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\CalibrationRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Equipment calibration register for P1. Read-only for now: the page is the
 * gate that makes a locked scale visible before someone tries to weigh with it.
 */
class CalibrationController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['q', 'status']);

        $query = CalibrationRecord::query()->with('responsibleUser');

        if (! empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $query->where(function (Builder $builder) use ($term) {
                $builder->where('equipment_code', 'like', "%{$term}%")
                    ->orWhere('equipment_name', 'like', "%{$term}%")
                    ->orWhere('equipment_kind', 'like', "%{$term}%")
                    ->orWhere('location', 'like', "%{$term}%");
            });
        }

        if (($filters['status'] ?? '') === 'locked') {
            $query->locked();
        } elseif (($filters['status'] ?? '') === 'due_soon') {
            $query->dueSoon();
        } elseif (($filters['status'] ?? '') === 'active') {
            $query->active();
        }

        $rows = $query
            ->orderByRaw("CASE
                WHEN status = 'LOCKED' THEN 0
                WHEN next_due_at IS NOT NULL AND next_due_at <= ? THEN 0
                WHEN next_due_at IS NOT NULL AND next_due_at <= ? THEN 1
                ELSE 2 END", [now()->toDateTimeString(), now()->addDays(CalibrationRecord::DUE_SOON_DAYS)->toDateTimeString()])
            ->orderBy('next_due_at')
            ->orderBy('equipment_code')
            ->paginate(20)
            ->withQueryString();

        $rows->through(fn (CalibrationRecord $record) => $this->row($record));

        $all = CalibrationRecord::query()->get();
        $summary = [
            'total' => $all->count(),
            'locked' => $all->filter(fn (CalibrationRecord $record) => $record->isLocked())->count(),
            'overdue' => $all->filter(fn (CalibrationRecord $record) => $record->displayStatus() === CalibrationRecord::DERIVED_OVERDUE)->count(),
            'due_soon' => $all->filter(fn (CalibrationRecord $record) => $record->displayStatus() === CalibrationRecord::DERIVED_DUE_SOON)->count(),
            'active' => $all->filter(fn (CalibrationRecord $record) => $record->displayStatus() === CalibrationRecord::DERIVED_ACTIVE)->count(),
        ];

        return Inertia::render('admin/calibrations/Index', [
            'calibrations' => $rows,
            'filters' => $filters,
            'summary' => $summary,
            'kinds' => CalibrationRecord::query()->whereNotNull('equipment_kind')->distinct()->orderBy('equipment_kind')->pluck('equipment_kind'),
        ]);
    }

    private function row(CalibrationRecord $record): array
    {
        return [
            'id' => $record->id,
            'equipment_code' => $record->equipment_code,
            'equipment_name' => $record->equipment_name,
            'equipment_kind' => $record->equipment_kind,
            'location' => $record->location,
            'last_calibrated_at' => $record->last_calibrated_at?->toIso8601String(),
            'next_due_at' => $record->next_due_at?->toIso8601String(),
            'certificate_no' => $record->certificate_no,
            'last_result' => $record->last_result,
            'status' => $record->displayStatus(),
            'status_label' => $record->statusLabel(),
            'status_icon' => $record->statusIcon(),
            'is_locked' => $record->isLocked(),
            'days_left' => $record->daysLeft(),
            'lock_reason' => $record->lock_reason,
            'unlock_condition' => $record->unlockText(),
            'responsible_name' => $record->responsibleUser?->name,
            'notes' => $record->notes,
        ];
    }
}
