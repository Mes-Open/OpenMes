<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\SwitchViewRequest;
use App\Support\ViewAs;
use Illuminate\Http\RedirectResponse;

/**
 * Switches the interface an administrator sees between the admin, supervisor and
 * operator views. Only the presentation changes; permissions stay the admin's.
 */
class ViewAsController extends Controller
{
    public function store(SwitchViewRequest $request): RedirectResponse
    {
        $view = $request->validated('view');

        if ($view === ViewAs::ADMIN) {
            $request->session()->forget(ViewAs::SESSION_KEY);
        } else {
            $request->session()->put(ViewAs::SESSION_KEY, $view);
        }

        return redirect()->route(ViewAs::landingRoute($view));
    }
}
