<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Every <InlineAlert> must name a severity.
 *
 * The component does `severities[severity].bg`, so a missing or misspelled
 * severity is not a styling slip — it throws TypeError and takes the whole
 * page down with it. That is a white screen, and only on the branch where the
 * alert renders, so it survives a build, a passing suite and a casual click-
 * through: Settings → Data crashed only once sample data had been loaded,
 * because the alert lives in the "already loaded" branch.
 */
class InlineAlertUsageTest extends TestCase
{
    /** The severities the component actually knows. */
    private const SEVERITIES = ['success', 'info', 'warning', 'error'];

    public function test_every_inline_alert_names_a_severity_it_understands(): void
    {
        $bad = [];

        foreach ($this->jsxFiles() as $file) {
            $source = file_get_contents($file);

            // Opening tags only; matches across newlines for multi-line props.
            preg_match_all('/<InlineAlert\b([^>]*)>/s', $source, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[1] as [$props, $offset]) {
                $line = substr_count(substr($source, 0, $offset), "\n") + 1;
                $short = str_replace(base_path().'/', '', $file);

                if (! preg_match('/\bseverity\s*=\s*(?:"([^"]+)"|\{)/', $props, $m)) {
                    $bad[] = "{$short}:{$line} — no severity";

                    continue;
                }

                // A computed severity ({expr}) cannot be checked here; only
                // literals are.
                if (isset($m[1]) && $m[1] !== '' && ! in_array($m[1], self::SEVERITIES, true)) {
                    $bad[] = "{$short}:{$line} — unknown severity \"{$m[1]}\"";
                }
            }
        }

        $this->assertSame([], $bad, "InlineAlert throws without a valid severity:\n".implode("\n", $bad));
    }

    /** @return array<int, string> */
    private function jsxFiles(): array
    {
        $files = [];

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('js'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.jsx')) {
                $files[] = $file->getPathname();
            }
        }

        $this->assertNotEmpty($files, 'Found no JSX to scan.');

        return $files;
    }
}
