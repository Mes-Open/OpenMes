<?php

namespace App\Http\Controllers\Api\V1;

use App\Extension\HookRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShiftRequest;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShiftController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Shift::class);

        $query = Shift::query()->with('line');
        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }
        $shifts = $query->orderBy('start_time')->get();
        // The shifts set for this line — its own, or its group's
        // (Shift::coverage) — not those that run on every line.
        if ($lineId = $request->query('line_id')) {
            $shifts = $shifts->filter(fn (Shift $s) => $s->runsOnLine((int) $lineId, plantWide: false))->values();
        }

        return response()->json(['data' => $shifts]);
    }

    public function show(Shift $shift): JsonResponse
    {
        $this->authorize('view', $shift);
        $shift->load('line');

        return response()->json(['data' => $shift]);
    }

    public function store(ShiftRequest $request): JsonResponse
    {
        $this->authorize('create', Shift::class);
        $data = $request->validated();
        $data['is_active'] ??= true;
        $data['sort_order'] ??= (int) Shift::max('sort_order') + 1;

        $shift = $this->persist(new Shift, $data, 'store');
        if ($shift === null) {
            return $this->overlapping();
        }

        return response()->json(['message' => 'Shift created', 'data' => $shift->load('line')], 201);
    }

    public function update(ShiftRequest $request, Shift $shift): JsonResponse
    {
        $this->authorize('update', $shift);

        if ($this->persist($shift, $request->validated(), 'update') === null) {
            return $this->overlapping();
        }

        return response()->json(['message' => 'Shift updated', 'data' => $shift->fresh(['line'])]);
    }

    /**
     * The admin screens' save: the shift's own columns, then the module fields
     * to whoever contributed them (Shift::PERSIST_HOOK), then the overlap
     * check — one transaction, so a module that moves the shift to a plant is
     * checked against that plant. Null, and nothing saved, on an overlap.
     */
    private function persist(Shift $shift, array $data, string $action): ?Shift
    {
        $own = array_filter($data, fn ($key) => ! str_starts_with((string) $key, 'module_'), ARRAY_FILTER_USE_KEY);

        DB::beginTransaction();
        try {
            $shift->fill($own)->save();

            app(HookRegistry::class)->dispatch(Shift::PERSIST_HOOK, [
                'action' => $action,
                'shift' => $shift,
                'input' => $data,
            ]);

            if ($shift->refresh()->overlapsAnotherActive()) {
                DB::rollBack();

                return null;
            }

            DB::commit();

            return $shift;
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }
    }

    private function overlapping(): JsonResponse
    {
        $message = __('This shift overlaps another shift on the same lines. Adjust the times and try again.');

        return response()->json(['message' => $message, 'errors' => ['start_time' => [$message]]], 422);
    }

    public function destroy(Shift $shift): JsonResponse
    {
        $this->authorize('delete', $shift);
        $shift->delete();

        return response()->json(['message' => 'Shift deleted']);
    }
}
