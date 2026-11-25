<?php

namespace App\Http\Controllers\Web\Admin;

use App\Extension\FilterRegistry;
use App\Extension\HookRegistry;
use App\Http\Controllers\Concerns\StaysOnList;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\ShiftRequest;
use App\Models\Line;
use App\Models\Shift;
use App\Services\CustomFieldService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ShiftController extends Controller
{
    use StaysOnList;

    /** Where an installed module hangs its own fields on the shift form. */
    private const FIELDS_HOOK = 'display.admin.shifts.form.fields';

    /**
     * Fired after a shift is saved, inside the same transaction — before the
     * overlap check, which may still roll the save back. Listeners write to the
     * database only; anything outside it (an API call, a cache, a file) belongs
     * on ResourceChanged, which is only fired once the save is committed.
     */
    private const SAVED_HOOK = 'persist.admin.shifts';

    public function index()
    {
        return Inertia::render('admin/shifts/Index', [
            'lineNames' => Line::pluck('name', 'id'),
            // How a module names a shift set for a group of lines (e.g. a
            // plant) in the Line column — null without one.
            'coverageColumn' => app(FilterRegistry::class)->filter('admin.shifts.coverage_column', null),
            // Option lists for the list page's create/edit drawer. Optional, so the
            // queries only run once someone opens it — most visits never do.
            'lines' => Inertia::optional(fn () => Line::where('is_active', true)->orderBy('name')->get(['id', 'name'])),
            'customFields' => Inertia::optional(fn () => app(CustomFieldService::class)->clientConfig('shift')),
            'hooks' => Inertia::optional(fn () => $this->moduleFields()),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/shifts/Create', [
            'lines' => Line::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'customFields' => app(CustomFieldService::class)->clientConfig('shift'),
            'hooks' => $this->moduleFields(),
        ]);
    }

    public function store(ShiftRequest $request)
    {
        $validated = $request->validated();
        $validated['sort_order'] ??= Shift::max('sort_order') + 1;

        if ($this->persist($request, $validated) === null) {
            return back()->withInput()->with('error', __('This shift overlaps with an existing shift on this line. Adjust the times and try again.'));
        }

        return $this->saved($request, redirect()->route('admin.shifts.index'), __('Shift created.'));
    }

    public function edit(Shift $shift)
    {
        return Inertia::render('admin/shifts/Edit', [
            'shift' => $shift->only('id', 'code', 'name', 'start_time', 'end_time', 'sort_order', 'line_id', 'is_active', 'custom_fields'),
            'lines' => Line::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'customFields' => app(CustomFieldService::class)->clientConfig('shift'),
            'hooks' => $this->moduleFields($shift),
        ]);
    }

    public function update(ShiftRequest $request, Shift $shift)
    {
        $validated = $request->validated();
        // sort_order is NOT NULL DEFAULT 0; preserve the existing value if the
        // field is cleared rather than passing an explicit null.
        $validated['sort_order'] ??= $shift->sort_order;

        if ($this->persist($request, $validated, $shift) === null) {
            return back()->withInput()->with('error', __('This shift overlaps with an existing shift on this line. Adjust the times and try again.'));
        }

        return $this->saved($request, redirect()->route('admin.shifts.index'), __('Shift updated.'));
    }

    /**
     * Fields installed modules contribute to the shift form, as data that core
     * renders (a module installed from a ZIP cannot ship JSX). `{}` without any.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function moduleFields(?Shift $shift = null): array
    {
        return app(HookRegistry::class)->renderMany([self::FIELDS_HOOK], ['shift' => $shift]);
    }

    /**
     * Save the shift's own columns, hand the module fields to whoever
     * contributed them, then check for overlap — all as one unit of work, so a
     * module that moves the shift to a group of lines is checked against that
     * group. Null (and nothing saved) when the shift would overlap another.
     *
     * Custom fields go last, once the shift is known to be kept: storing them
     * writes uploaded files and deletes the ones they replace, which a database
     * rollback could not undo.
     */
    private function persist(ShiftRequest $request, array $validated, ?Shift $shift = null): ?Shift
    {
        $cf = app(CustomFieldService::class);
        unset($validated['custom_field_files'], $validated['custom_fields']);

        // A module's keys are `module_<name>_<field>` (AllowsModuleFields) — they
        // are not columns of this table and must not reach the model.
        $own = array_filter($validated, fn ($key) => ! str_starts_with((string) $key, 'module_'), ARRAY_FILTER_USE_KEY);

        DB::beginTransaction();
        try {
            $action = $shift === null ? 'store' : 'update';
            if ($shift === null) {
                $shift = Shift::create($own);
            } else {
                $shift->update($own);
            }

            app(HookRegistry::class)->dispatch(self::SAVED_HOOK, [
                'action' => $action,
                'shift' => $shift,
                'input' => $validated,
            ]);

            if ($this->overlaps($shift->refresh())) {
                DB::rollBack();

                return null;
            }

            if ($cf->touched($request)) {
                $shift->forceFill([
                    'custom_fields' => $cf->fromRequest($request, 'shift', $shift->custom_fields) ?: null,
                ])->save();
            }

            DB::commit();

            return $shift;
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }
    }

    public function destroy(Shift $shift)
    {
        if ($shift->shiftEntries()->exists()) {
            return back()->with('error', __('Cannot delete shift with production entries. Deactivate it instead.'));
        }

        $shift->delete();

        return redirect()->route('admin.shifts.index')->with('success', __('Shift deleted.'));
    }

    /**
     * Does this shift overlap another active shift set for the same
     * lines? Only shifts with the same coverage clash (Shift::coverage): the
     * same line, every line, or the same group a module defined — same-time
     * shifts on different lines are fine.
     */
    private function overlaps(Shift $shift): bool
    {
        $start = substr($shift->start_time, 0, 5);
        $end = substr($shift->end_time, 0, 5);
        $key = $shift->coverage()['key'];

        $query = Shift::where('is_active', true)->where('id', '!=', $shift->id);

        return $query->get()->filter(fn (Shift $other) => $other->coverage()['key'] === $key)->contains(function (Shift $existing) use ($start, $end) {
            $eStart = substr($existing->start_time, 0, 5);
            $eEnd = substr($existing->end_time, 0, 5);

            // Handle overnight shifts (e.g. 22:00 - 06:00)
            $newCrossesMidnight = $start > $end;
            $existCrossesMidnight = $eStart > $eEnd;

            if (! $newCrossesMidnight && ! $existCrossesMidnight) {
                // Both within same day
                return $start < $eEnd && $end > $eStart;
            }

            // At least one crosses midnight — normalize to minutes and check
            $toMin = fn ($t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
            $ns = $toMin($start);
            $ne = $toMin($end) + ($newCrossesMidnight ? 1440 : 0);
            $es = $toMin($eStart);
            $ee = $toMin($eEnd) + ($existCrossesMidnight ? 1440 : 0);

            // Check overlap in both original and +24h shifted positions
            return ($ns < $ee && $ne > $es)
                || ($ns < $ee + 1440 && $ne > $es + 1440)
                || ($ns + 1440 < $ee && $ne + 1440 > $es);
        });
    }
}
