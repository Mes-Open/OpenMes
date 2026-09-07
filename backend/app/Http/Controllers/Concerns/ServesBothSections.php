<?php

namespace App\Http\Controllers\Concerns;

use RuntimeException;

/**
 * For a screen mounted under both `/admin` and `/supervisor`.
 *
 * Admins and supervisors have separate route trees, so each role stays inside
 * its own section — but a screen both roles need is still one controller and
 * one React page. What differs is only the prefix, so a controller serving both
 * must never name `admin.*` routes or emit `/admin/...` paths directly: it asks
 * here instead, and the answer follows whichever route actually matched.
 *
 * The page gets `basePath` and builds its own URLs from it, the same way the
 * shift monitor does.
 *
 * The section is read from the route group's `section` default rather than
 * inferred from the route name, so mounting one of these controllers somewhere
 * new fails loudly instead of quietly handing the caller links into a tree they
 * may not be allowed to open.
 */
trait ServesBothSections
{
    /** 'admin' or 'supervisor' — whichever tree served this request. */
    protected function section(): string
    {
        $section = request()->route()?->getAction('section');

        if (! is_string($section)) {
            throw new RuntimeException(
                'This controller serves both sections, so its route group must declare '
                ."which one it is: 'section' => 'admin'|'supervisor'.",
            );
        }

        return $section;
    }

    /**
     * A route name in the current section: `sectionRoute('customers.index')`
     * resolves to `admin.customers.index` or `supervisor.customers.index`.
     */
    protected function sectionRoute(string $name, mixed $parameters = []): string
    {
        return route($this->section().'.'.$name, $parameters);
    }

    /** The URL prefix the page should build its own links and posts from. */
    protected function basePath(string $suffix): string
    {
        return '/'.$this->section().$suffix;
    }
}
