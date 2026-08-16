<?php

namespace Tests\Feature\Web;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Every sidebar link must point at a route that exists.
 *
 * The two nav trees are hand-written JavaScript (`adminNav.js`,
 * `supervisorNav.js`) while the routes they name live in PHP, so nothing
 * connects them — renaming or removing a route leaves a menu entry that 404s,
 * and no existing test notices, because they all address routes by name and a
 * typo in a JS href string is invisible to that.
 *
 * This reads the hrefs straight out of the nav files and resolves each against
 * the router.
 */
class NavLinksResolveTest extends TestCase
{
    /** @return array<string, array<int, string>> file => hrefs */
    private function navHrefs(): array
    {
        $found = [];

        foreach (['adminNav.js', 'supervisorNav.js'] as $file) {
            $source = file_get_contents(resource_path("js/layouts/{$file}"));

            // href: '/admin/work-orders'  — single- or double-quoted literals only.
            // A templated href is a runtime value this test cannot resolve.
            preg_match_all("/href:\s*['\"](\/[^'\"]*)['\"]/", $source, $matches);

            $found[$file] = array_values(array_unique($matches[1]));
        }

        return $found;
    }

    public function test_the_nav_files_expose_hrefs_to_check(): void
    {
        // Guards the regex itself: a syntax change that silently matched nothing
        // would make every assertion below vacuous.
        $hrefs = $this->navHrefs();

        $this->assertGreaterThan(50, count($hrefs['adminNav.js']));
        $this->assertGreaterThan(8, count($hrefs['supervisorNav.js']));
    }

    public function test_every_sidebar_link_resolves_to_a_registered_route(): void
    {
        $failures = [];

        foreach ($this->navHrefs() as $file => $hrefs) {
            foreach ($hrefs as $href) {
                try {
                    Route::getRoutes()->match(Request::create($href, 'GET'));
                } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
                    // Collected rather than thrown, so one run names every dead
                    // link instead of stopping at the first.
                    $failures[] = "{$file}: {$href}";
                }
            }
        }

        $this->assertSame([], $failures, "Sidebar links with no matching route:\n".implode("\n", $failures));
    }
}
