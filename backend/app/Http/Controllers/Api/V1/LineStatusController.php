<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\LineStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LineStatusController extends Controller
{
    public function index(Line $line): JsonResponse
    {
        return response()->json([
            'data' => $line->lineStatuses()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request, Line $line): JsonResponse
    {
        $this->authorize('create', LineStatus::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:30'],
            'sort_order' => ['nullable', 'integer'],
            'is_default' => ['nullable', 'boolean'],
            'is_done_status' => ['nullable', 'boolean'],
        ]);
        $data['line_id'] = $line->id;
        if (!isset($data['sort_order'])) {
            $data['sort_order'] = ($line->lineStatuses()->max('sort_order') ?? 0) + 1;
        }

        $status = DB::transaction(function () use ($line, $data) {
            // Only one default per line
            if (!empty($data['is_default'])) {
                $line->lineStatuses()->update(['is_default' => false]);
            }
            return LineStatus::create($data);
        });

        return response()->json(['message' => 'Line status created', 'data' => $status], 201);
    }

    public function update(Request $request, LineStatus $lineStatus): JsonResponse
    {
        $this->authorize('update', $lineStatus);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'color' => ['sometimes', 'nullable', 'string', 'max:30'],
            'sort_order' => ['sometimes', 'integer'],
            'is_default' => ['sometimes', 'boolean'],
            'is_done_status' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($lineStatus, $data) {
            if (array_key_exists('is_default', $data) && $data['is_default']) {
                LineStatus::where('line_id', $lineStatus->line_id)
                    ->where('id', '!=', $lineStatus->id)
                    ->update(['is_default' => false]);
            }
            $lineStatus->update($data);
        });

        return response()->json(['message' => 'Line status updated', 'data' => $lineStatus->fresh()]);
    }

    public function destroy(LineStatus $lineStatus): JsonResponse
    {
        $this->authorize('delete', $lineStatus);
        $lineStatus->delete();
        return response()->json(['message' => 'Line status deleted']);
    }

    public function reorder(Request $request, Line $line): JsonResponse
    {
        $this->authorize('create', LineStatus::class);
        $data = $request->validate([
            'status_ids' => ['required', 'array', 'min:1'],
            'status_ids.*' => ['integer', 'exists:line_statuses,id'],
        ]);

        $count = $line->lineStatuses()->whereIn('id', $data['status_ids'])->count();
        if ($count !== count($data['status_ids'])) {
            return response()->json([
                'message' => 'All status IDs must belong to this line',
            ], 422);
        }

        DB::transaction(function () use ($data) {
            foreach ($data['status_ids'] as $i => $id) {
                LineStatus::where('id', $id)->update(['sort_order' => 10000 + $i]);
            }
            foreach ($data['status_ids'] as $i => $id) {
                LineStatus::where('id', $id)->update(['sort_order' => $i + 1]);
            }
        });

        return response()->json([
            'message' => 'Reordered',
            'data' => $line->lineStatuses()->orderBy('sort_order')->get(),
        ]);
    }
}
