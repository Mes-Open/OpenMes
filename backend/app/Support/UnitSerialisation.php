<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * How serialised units are identified (system settings, `unit_*` keys).
 *
 * A unit carries two identifiers: its own serial number (what the customer
 * sees on the product) and, optionally, a process serial number - the working
 * identifier the line gives it before the final label exists. Plants differ in
 * what those look like and how strict they are, so every rule lives here as a
 * setting rather than in code:
 *
 *   unit_identifier_normalize  strip whitespace and uppercase before storing or
 *                              matching (a label printed with spaced groups and
 *                              the same label scanned without them are one identifier)
 *   unit_serial_pattern        regex the unit serial must match, empty = any
 *   unit_psn_pattern           regex the process serial must match, empty = any
 *   unit_psn_required          a unit cannot be registered without a process serial
 *   unit_psn_unique            one process serial identifies one unit
 *   unit_test_fail_policy      what a failed test verdict does to the unit:
 *                              block  - hold it after unit_test_max_attempts fails (a later pass clears it)
 *                              scrap  - scrap it on the first fail
 *                              record - only keep the verdict
 *   unit_test_max_attempts     failed verdicts before a unit is blocked (policy `block`); default 1
 *   unit_test_attempts_scope   which failed verdicts count towards that limit:
 *                              unit - every failed verdict of the unit, at any tester
 *                              test - those at the same test station since its last pass
 *                                     (a retest loop per test: a pass starts it over)
 */
final class UnitSerialisation
{
    public const KEYS = [
        'unit_identifier_normalize' => true,
        'unit_serial_pattern' => '',
        'unit_psn_pattern' => '',
        'unit_psn_required' => false,
        'unit_psn_unique' => true,
        'unit_test_fail_policy' => self::FAIL_BLOCK,
        'unit_test_max_attempts' => 1,
        'unit_test_attempts_scope' => self::ATTEMPTS_UNIT,
    ];

    public const FAIL_BLOCK = 'block';

    public const FAIL_SCRAP = 'scrap';

    public const FAIL_RECORD = 'record';

    public const FAIL_POLICIES = [self::FAIL_BLOCK, self::FAIL_SCRAP, self::FAIL_RECORD];

    public const ATTEMPTS_UNIT = 'unit';

    public const ATTEMPTS_TEST = 'test';

    public const ATTEMPT_SCOPES = [self::ATTEMPTS_UNIT, self::ATTEMPTS_TEST];

    /** Settings read once per request (the container instance is request-scoped under Octane). */
    private ?array $settings = null;

    public function all(): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        $out = self::KEYS;
        try {
            $rows = DB::table('system_settings')->whereIn('key', [...array_keys(self::KEYS), 'unit_scrap_on_test_fail'])->pluck('value', 'key');
        } catch (\Throwable) {
            return $this->settings = $out; // no database yet (install)
        }

        foreach ($rows as $key => $raw) {
            $value = json_decode((string) $raw, true);
            if ($value !== null) {
                $out[$key] = $value;
            }
        }

        // An install saved before the policy existed kept a "scrap on fail" flag.
        if (! isset($rows['unit_test_fail_policy']) && json_decode((string) ($rows['unit_scrap_on_test_fail'] ?? 'false'), true)) {
            $out['unit_test_fail_policy'] = self::FAIL_SCRAP;
        }

        return $this->settings = $out;
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? self::KEYS[$key] ?? null;
    }

    public function forget(): void
    {
        $this->settings = null;
    }

    /** The stored form of a scanned identifier. */
    public function normalize(?string $identifier): ?string
    {
        if ($identifier === null) {
            return null;
        }
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        return $this->get('unit_identifier_normalize')
            ? strtoupper(preg_replace('/\s+/u', '', $identifier))
            : $identifier;
    }

    /** Laravel validation rules for a unit serial number. */
    public function serialRules(): array
    {
        return array_merge(['required', 'string', 'max:100'], $this->patternRule((string) $this->get('unit_serial_pattern')));
    }

    /** Laravel validation rules for a process serial number. */
    public function psnRules(): array
    {
        return array_merge(
            [$this->get('unit_psn_required') ? 'required' : 'nullable', 'string', 'max:100'],
            $this->patternRule((string) $this->get('unit_psn_pattern')),
        );
    }

    /** True when the regex is usable - the settings form refuses anything else. */
    public static function validPattern(string $pattern): bool
    {
        return $pattern === '' || @preg_match(self::delimit($pattern), '') !== false;
    }

    /** @return array<int, string> */
    private function patternRule(string $pattern): array
    {
        return $pattern === '' ? [] : ['regex:'.self::delimit($pattern)];
    }

    /** Patterns are stored bare ("^\d{3}-\d{2}-\d+$"); Laravel's regex rule wants delimiters. */
    private static function delimit(string $pattern): string
    {
        return '/'.str_replace('/', '\/', $pattern).'/u';
    }
}
