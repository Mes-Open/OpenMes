<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * An inspection was asked to complete when it cannot.
 *
 * Refusing is correct — an inspection with nothing recorded has no pass or fail
 * to compute, and one already completed must not silently reopen. What was wrong
 * was the delivery: both guards threw a bare exception that no caller handled, so
 * a rule the inspector needs to read arrived as a 500.
 *
 * Rendering itself fixes every call site at once — the web controller in the
 * Enterprise module and the core API both go through here without either needing
 * to catch. 422 rather than 400: the request is well-formed, it is the state of
 * the inspection that forbids it.
 *
 * Extends RuntimeException so existing callers and tests that expect one still
 * see what they expected.
 */
class InspectionNotCompletableException extends RuntimeException
{
    public static function withoutCriteria(): self
    {
        return new self(__('Record at least one measurement before completing this inspection.'));
    }

    public static function alreadyCompleted(string $status): self
    {
        return new self(__('This inspection has already been completed (:status).', ['status' => $status]));
    }

    public function render(Request $request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->with('error', $this->getMessage());
    }
}
