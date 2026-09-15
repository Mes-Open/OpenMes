<?php

namespace Tests\Feature;

use App\Support\TabRegistry;
use Tests\TestCase;

/**
 * The sidebar must not offer what the backend forbids.
 *
 * Access is decided twice: TabAccessMiddleware gates a URL by the tab that owns
 * its prefix, and the sidebar decides separately whether to draw the entry — an
 * entry's own `tab`, falling back to its group's `tab`, falling back to the
 * group's `key`. When the two disagree the user gets a menu item that 404s on
 * click, or a page they can reach but cannot find.
 *
 * Both failures have already shipped once. Moving OEE out of the Maintenance
 * group into Analytics dropped its gate, because inside that group the group key
 * had been doing the work — so it was listed whenever Reports was on and 404'd
 * for anyone with the maintenance module off. Separately, renaming the group
 * keys broke the fallback and hid four whole groups.
 */
class AdminNavGatingTest extends TestCase
{
    /**
     * Every leaf entry in adminNav.js, with the tab that governs it.
     *
     * The file is parsed as text rather than executed: it is an ES module the
     * PHP suite cannot import, and its formatting is uniform — one object per
     * line for leaves, `key:` / `tab:` on their own lines for groups.
     *
     * @return array<int, array{href: string, declared: ?string, group: ?string, line: int}>
     */
    private function navEntries(): array
    {
        $path = resource_path('js/layouts/adminNav.js');
        $this->assertFileExists($path);

        $entries = [];
        $blockKey = null;
        $blockTab = null;

        foreach (file($path) as $i => $line) {
            // `key:` / `tab:` on their own line belong to the enclosing block —
            // a group, or a multi-line top-level link. Both gate the same way:
            // AppLayout falls back from an entry's tab to its block's tab to
            // its block's key.
            if (preg_match("/^        key: '([^']+)'/", $line, $m)) {
                $blockKey = $m[1];
                $blockTab = null;
            }
            if (preg_match("/^        tab: '([^']+)'/", $line, $m)) {
                $blockTab = $m[1];
            }

            if (! preg_match("/href: '([^']+)'/", $line, $m)) {
                continue;
            }

            // A single-line entry carries its own gate; otherwise inherit.
            preg_match("/tab: '([^']+)'/", $line, $t);
            preg_match("/key: '([^']+)'/", $line, $k);

            $entries[] = [
                'href' => $m[1],
                'declared' => $t[1] ?? null,
                'group' => $k[1] ?? $blockTab ?? $blockKey,
                'line' => $i + 1,
            ];
        }

        $this->assertNotEmpty($entries, 'Parsed no nav entries — has the file format changed?');

        return $entries;
    }

    public function test_no_menu_entry_leads_somewhere_the_backend_will_refuse(): void
    {
        $mismatches = [];

        foreach ($this->navEntries() as $entry) {
            $governing = TabRegistry::tabForPath(parse_url($entry['href'], PHP_URL_PATH) ?? $entry['href']);

            // Not every URL is tab-governed (settings, packaging pages); those
            // are the middleware's business, not this test's.
            if ($governing === null) {
                continue;
            }

            $effective = $entry['declared'] ?? $entry['group'];

            if ($effective !== $governing) {
                $mismatches[] = sprintf(
                    'adminNav.js:%d  %s is gated by "%s" but the menu shows it under "%s"',
                    $entry['line'],
                    $entry['href'],
                    $governing,
                    $effective ?? 'no tab at all',
                );
            }
        }

        $this->assertSame([], $mismatches, "Sidebar and backend disagree about who may see these:\n".implode("\n", $mismatches));
    }

    public function test_every_declared_tab_is_a_real_one(): void
    {
        foreach ($this->navEntries() as $entry) {
            if ($entry['declared'] === null) {
                continue;
            }

            // A typo here fails open or closed silently — the entry is simply
            // compared against a tab nobody has.
            $this->assertTrue(
                TabRegistry::exists($entry['declared']),
                "adminNav.js:{$entry['line']} names tab \"{$entry['declared']}\", which does not exist.",
            );
        }
    }
}
