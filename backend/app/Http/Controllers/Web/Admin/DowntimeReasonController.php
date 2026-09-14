<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\DowntimeKind;
use App\Http\Controllers\Concerns\StaysOnList;
use App\Http\Controllers\Controller;
use App\Models\DowntimeReason;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * What an operator picks from when a machine stops.
 *
 * The dictionary has existed since downtime tracking was added, and operators
 * have always chosen from it — but nothing could edit it. A plant was stuck
 * with whatever reasons were seeded unless someone went into the database, so
 * the OEE reports it feeds could never be adapted to how that shop actually
 * loses time.
 *
 * The `kind` is the load-bearing field: planned downtime reduces the operating
 * window without counting against availability, while unplanned and changeover
 * do count. Getting it wrong quietly changes every OEE figure the reason
 * touches, so the form states the consequence rather than just listing options.
 */
class DowntimeReasonController extends Controller
{
    use StaysOnList;

    /**
     * Rows live-sync through the `downtime_reasons` shape; the usage count
     * comes as a prop, because it is the one thing that makes a reason unsafe
     * to retire without thinking.
     */
    public function index()
    {
        return Inertia::render('admin/downtime-reasons/Index', [
            'counts' => DowntimeReason::withCount('downtimes')
                ->get(['id'])
                ->mapWithKeys(fn ($r) => [$r->id => $r->downtimes_count]),
            'kinds' => $this->kinds(),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/downtime-reasons/Create', [
            'kinds' => $this->kinds(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $validated['is_active'] = $request->boolean('is_active', true);

        DowntimeReason::create($validated);

        return $this->saved(
            $request,
            redirect()->route('admin.downtime-reasons.index'),
            __('Downtime reason created successfully.'),
        );
    }

    public function edit(DowntimeReason $downtimeReason)
    {
        return Inertia::render('admin/downtime-reasons/Edit', [
            'downtimeReason' => $downtimeReason->only('id', 'code', 'name', 'kind', 'is_active'),
            'kinds' => $this->kinds(),
        ]);
    }

    public function update(Request $request, DowntimeReason $downtimeReason)
    {
        $validated = $request->validate($this->rules($downtimeReason));
        $validated['is_active'] = $request->boolean('is_active', true);

        $downtimeReason->update($validated);

        return $this->saved(
            $request,
            redirect()->route('admin.downtime-reasons.index'),
            __('Downtime reason updated successfully.'),
        );
    }

    /**
     * Retire a reason.
     *
     * Soft-deleted, never removed: every downtime ever recorded against it
     * still points here, and hard-deleting would rewrite past OEE figures.
     */
    public function destroy(DowntimeReason $downtimeReason)
    {
        $downtimeReason->delete();

        return redirect()->route('admin.downtime-reasons.index')
            ->with('success', __('Downtime reason deleted successfully.'));
    }

    /** Take a reason out of circulation without retiring it. */
    public function toggleActive(DowntimeReason $downtimeReason)
    {
        $downtimeReason->update(['is_active' => ! $downtimeReason->is_active]);

        return redirect()->route('admin.downtime-reasons.index')->with(
            'success',
            $downtimeReason->is_active
                ? __('Downtime reason activated.')
                : __('Downtime reason deactivated.'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?DowntimeReason $existing = null): array
    {
        return [
            // Unique among live rows only — the index is partial, so a code
            // freed by a retired reason can be used again.
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('downtime_reasons', 'code')
                    ->ignore($existing?->id)
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::enum(DowntimeKind::class)],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * The kinds, each carrying what it does to availability — the form shows
     * that rather than making the admin remember it.
     *
     * @return list<array{value: string, label: string, counts_as_loss: bool}>
     */
    private function kinds(): array
    {
        return array_map(fn (DowntimeKind $k) => [
            'value' => $k->value,
            'label' => $k->label(),
            'counts_as_loss' => $k->countsAsAvailabilityLoss(),
        ], DowntimeKind::cases());
    }
}
