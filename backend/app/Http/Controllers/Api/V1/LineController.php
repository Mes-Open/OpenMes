<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Http\Resources\LineResource;
use App\Traits\StandardApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LineController extends Controller
{
    use StandardApiResponse;

    /**
     * Get list of lines (filtered by user's assigned lines for operators).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Admin and Supervisor can see all lines
        if ($user->hasAnyRole(['Admin', 'Supervisor'])) {
            $lines = Line::active()->with('workstations')->orderBy('name')->get();
        } else {
            // Operators only see assigned lines
            $lines = $user->lines()->where('is_active', true)->with('workstations')->orderBy('name')->get();
        }

        return LineResource::collection($lines)->response();
    }

    /**
     * Get a specific line.
     *
     * @param Line $line
     * @return JsonResponse
     */
    public function show(Line $line): JsonResponse
    {
        $line->load(['workstations', 'users']);

        return (new LineResource($line))->response();
    }
}
