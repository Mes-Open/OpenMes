<?php

namespace App\Http\Controllers\Web\Admin\Schedule;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\Schedule\AcknowledgePlanningInsightRequest;
use App\Http\Requests\Web\Admin\Schedule\MoveOrderForInsightRequest;
use App\Http\Requests\Web\Admin\Schedule\PlanningInsightRequest;
use App\Http\Requests\Web\Admin\Schedule\SubstituteForInsightRequest;
use App\Http\Requests\Web\Admin\Schedule\UndoPlanningInsightRequest;
use App\Models\PlanningInsightAcknowledgement;
use App\Services\Schedule\Insights\PlanningInsights;
use Illuminate\Http\JsonResponse;

/**
 * The Production Planner's insights drawer (JSON, fetched by the planner).
 * Every action answers with the refreshed drawer and staffing badges, so the
 * board updates without a reload.
 */
class PlanningInsightController extends Controller
{
    public function index(PlanningInsightRequest $request): JsonResponse
    {
        return response()->json($this->state($request));
    }

    public function acknowledge(AcknowledgePlanningInsightRequest $request): JsonResponse
    {
        return $this->act($request, fn (PlanningInsights $insights) => $insights->acknowledge($request->validated('insight_key'), $request->user()));
    }

    public function move(MoveOrderForInsightRequest $request): JsonResponse
    {
        $result = null;
        $response = $this->act($request, function (PlanningInsights $insights) use ($request, &$result) {
            $result = $insights->move(
                $request->validated('insight_key'),
                (int) $request->validated('work_order_id'),
                $request->user(),
                $request->boolean('force_conflict'),
            );

            return $result['acknowledgement'] ?? null;
        });

        if (($result['conflict'] ?? false) === true) {
            return response()->json(['success' => false, 'conflict' => true, 'message' => $result['message']], 409);
        }

        return $response;
    }

    public function substitute(SubstituteForInsightRequest $request): JsonResponse
    {
        return $this->act($request, fn (PlanningInsights $insights) => $insights->substitute(
            $request->validated('insight_key'), (int) $request->validated('user_id'), $request->user(),
        ));
    }

    public function undo(UndoPlanningInsightRequest $request, PlanningInsightAcknowledgement $acknowledgement): JsonResponse
    {
        try {
            app(PlanningInsights::class)->undo($acknowledgement);
        } catch (\DomainException $e) {
            return $this->refused($e);
        }

        return response()->json(['success' => true, 'message' => __('Undone.')] + $this->state($request));
    }

    /**
     * @param  callable(PlanningInsights): ?PlanningInsightAcknowledgement  $action
     */
    private function act(PlanningInsightRequest $request, callable $action): JsonResponse
    {
        $insights = $this->loaded($request);
        if ($insights === null) {
            return response()->json(['message' => __('Insights are shown on the weekly and daily views.')], 422);
        }

        try {
            $ack = $action($insights);
        } catch (\DomainException $e) {
            return $this->refused($e);
        }
        if ($ack === null) {
            // A conflict: the caller answers 409.
            return response()->json(['success' => false]);
        }

        return response()->json([
            'success' => true,
            'message' => $ack->summary,
            'acknowledgement_id' => $ack->id,
        ] + $this->state($request));
    }

    /** @return array{drawer: ?array<string, mixed>, staffing: ?array<string, mixed>} */
    private function state(PlanningInsightRequest $request): array
    {
        $insights = $this->loaded($request);

        return [
            'drawer' => $insights?->drawer(),
            'staffing' => $insights?->board(),
        ];
    }

    private function loaded(PlanningInsightRequest $request): ?PlanningInsights
    {
        $range = PlanningInsights::rangeFor($request->validated('view_mode'), $request->validated('start_date'));
        if ($range === null) {
            return null;
        }
        $lineId = $request->validated('line_id');

        // A fresh instance: an action changes what the next read must see.
        return app(PlanningInsights::class)->load($range[0], $range[1], $lineId !== null ? (int) $lineId : null);
    }

    private function refused(\DomainException $e): JsonResponse
    {
        return response()->json(['message' => $e->getMessage(), 'errors' => ['insight_key' => [$e->getMessage()]]], 422);
    }
}
