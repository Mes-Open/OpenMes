<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The batch step an operator acted on was replaced: the order had not started,
 * its process template was edited, and its steps were regenerated
 * (ProcessTemplateChangePropagator). The screen the operator acted from is
 * stale — tell them to reload rather than show a bare 404.
 */
class StepRegeneratedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The process of this order was updated. Reload the page.');
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        $message = __('The process of this order was updated. Reload the page.');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 409);
        }

        return back()->with('error', $message);
    }
}
