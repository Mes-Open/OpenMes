<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * For a controller whose list page creates and edits in a drawer.
 *
 * A drawer posts from the list it is sitting on. Answering with the usual
 * redirect to that same list is not a no-op: Inertia treats it as a visit, the
 * page component remounts, and the user loses their search, their column
 * filters, the page they were on and their scroll position — all of it client
 * state in `DataTable`, none of it in the URL. So the drawer sends `stay` and
 * the controller answers `back()` instead, leaving the page alone while the new
 * or changed row live-syncs in on its own.
 *
 * `back()` is still a visit, which is what keeps the flash working: the message
 * is read by `FlashMessages` in `AppLayout` exactly as it is after a redirect.
 * Don't reach for `useToast()` here — a toast does not survive an Inertia visit
 * (the provider remounts with the layout), which is the whole reason the two
 * mechanisms are separate.
 *
 * The standalone `/create` and `/edit` pages post without `stay`, so they keep
 * redirecting to the list the way they always have.
 */
trait StaysOnList
{
    /**
     * Finish a write: back to the list page if the caller asked to stay put,
     * otherwise wherever the standalone form would have gone.
     *
     *     return $this->saved($request, redirect()->route('admin.areas.index'),
     *         __('Area created successfully.'));
     *
     * `$onward` is a built response rather than a route name so this fits every
     * shape already in use — `route()`, `to()`, and the `sectionRoute()` URLs a
     * controller serving both sections has to build for itself.
     */
    protected function saved(Request $request, RedirectResponse $onward, string $message): RedirectResponse
    {
        if ($request->boolean('stay')) {
            return back()->with('success', $message);
        }

        return $onward->with('success', $message);
    }
}
