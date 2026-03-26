<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

trait StandardApiResponse
{
    /**
     * Return a success JSON response.
     */
    protected function success(mixed $data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'locale' => app()->getLocale(),
            'correlation_id' => request()->header('X-Correlation-ID'),
        ], $code);
    }

    /**
     * Return an error JSON response.
     */
    protected function error(string $message = 'Error', int $code = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
            'locale' => app()->getLocale(),
            'correlation_id' => request()->header('X-Correlation-ID'),
        ], $code);
    }

    /**
     * Return a paginated JSON response.
     */
    protected function paginated(LengthAwarePaginator $paginator, string $resourceClass = null): JsonResponse
    {
        $data = $resourceClass
            ? $resourceClass::collection($paginator->items())
            : $paginator->items();

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'locale' => app()->getLocale(),
            'correlation_id' => request()->header('X-Correlation-ID'),
        ]);
    }
}
