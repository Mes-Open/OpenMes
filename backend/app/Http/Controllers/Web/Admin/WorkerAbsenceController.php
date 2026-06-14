<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkerAbsenceRequest;
use App\Models\Worker;
use App\Models\WorkerAbsence;
use Inertia\Inertia;

class WorkerAbsenceController extends Controller
{
    /**
     * Display a listing of absences. Rows live-sync via the `worker_absences`
     * shape; worker names come as a prop (the shape only carries worker_id).
     */
    public function index()
    {
        return Inertia::render('admin/worker-absences/Index', [
            'workerNames' => Worker::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/worker-absences/Create', [
            'workers' => Worker::active()->orderBy('name')->get(['id', 'name']),
            'types' => WorkerAbsence::TYPES,
            'statuses' => WorkerAbsence::STATUSES,
        ]);
    }

    public function store(WorkerAbsenceRequest $request)
    {
        WorkerAbsence::create($this->payload($request));

        return redirect()->route('admin.worker-absences.index')
            ->with('success', __('Absence recorded successfully.'));
    }

    public function edit(WorkerAbsence $workerAbsence)
    {
        return Inertia::render('admin/worker-absences/Edit', [
            'absence' => [
                'id' => $workerAbsence->id,
                'worker_id' => $workerAbsence->worker_id,
                'type' => $workerAbsence->type,
                'starts_on' => $workerAbsence->starts_on?->format('Y-m-d'),
                'ends_on' => $workerAbsence->ends_on?->format('Y-m-d'),
                'all_day' => (bool) $workerAbsence->all_day,
                'start_time' => $workerAbsence->start_time ? substr((string) $workerAbsence->start_time, 0, 5) : '',
                'end_time' => $workerAbsence->end_time ? substr((string) $workerAbsence->end_time, 0, 5) : '',
                'status' => $workerAbsence->status,
                'reason' => $workerAbsence->reason,
            ],
            'workers' => Worker::active()->orderBy('name')->get(['id', 'name']),
            'types' => WorkerAbsence::TYPES,
            'statuses' => WorkerAbsence::STATUSES,
        ]);
    }

    public function update(WorkerAbsenceRequest $request, WorkerAbsence $workerAbsence)
    {
        $workerAbsence->update($this->payload($request));

        return redirect()->route('admin.worker-absences.index')
            ->with('success', __('Absence updated successfully.'));
    }

    public function destroy(WorkerAbsence $workerAbsence)
    {
        $workerAbsence->delete();

        return redirect()->route('admin.worker-absences.index')
            ->with('success', __('Absence deleted successfully.'));
    }

    /**
     * Normalise the validated payload: default status/all_day and clear the
     * partial-day times when the absence is full-day.
     *
     * @return array<string, mixed>
     */
    private function payload(WorkerAbsenceRequest $request): array
    {
        $data = $request->validated();
        $data['all_day'] = $request->boolean('all_day', true);
        $data['status'] = $data['status'] ?? 'approved';
        $data['created_by_id'] = $request->user()?->id;

        if ($data['all_day']) {
            $data['start_time'] = null;
            $data['end_time'] = null;
        }

        return $data;
    }
}
