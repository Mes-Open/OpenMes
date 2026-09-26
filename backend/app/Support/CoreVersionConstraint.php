<?php

namespace App\Support;

/**
 * Whether the running core satisfies a module's `requires_core`.
 *
 * A module is built against the extension points of a particular core version.
 * Installed on an older one, the seams it relies on are simply absent — its
 * fields never render, its listeners are never called, and nothing says why.
 * The manifest has carried this constraint from the start and nothing ever read
 * it, so the failure mode was a support conversation instead of an error.
 *
 * Deliberately not a Composer-grade resolver. The grammar is one optional
 * operator and one version, which is all any manifest in this project uses, and
 * an unparseable constraint is refused rather than guessed at — a typo that
 * quietly means "any version" is exactly the outcome this is meant to prevent.
 */
class CoreVersionConstraint
{
    /** Longest first: `>=` must match before `>`. */
    private const OPERATORS = ['>=', '<=', '==', '!=', '>', '<', '='];

    /**
     * @param  string|null  $constraint  e.g. `>=0.25.0`, or null when the module declares none
     * @param  string|null  $current  the running core version, `v` prefix optional
     */
    public static function isSatisfied(?string $constraint, ?string $current = null): bool
    {
        $constraint = trim((string) $constraint);

        // No constraint is the normal case — most modules declare none, and a
        // module that does not care which core it runs on is not an error.
        if ($constraint === '') {
            return true;
        }

        [$operator, $required] = self::parse($constraint);

        return version_compare(self::normalise($current ?? config('version.current')), $required, $operator);
    }

    /**
     * @return array{0: string, 1: string}
     *
     * @throws \InvalidArgumentException when the constraint is not one operator and one version
     */
    private static function parse(string $constraint): array
    {
        foreach (self::OPERATORS as $operator) {
            if (str_starts_with($constraint, $operator)) {
                $version = self::normalise(substr($constraint, strlen($operator)));

                return [$operator === '=' ? '==' : $operator, self::assertVersion($version, $constraint)];
            }
        }

        // A bare version means "this one or newer". Modules are forward
        // compatible far more often than not, and reading it as an exact match
        // would strand every module on the day core is patched.
        return ['>=', self::assertVersion(self::normalise($constraint), $constraint)];
    }

    private static function normalise(?string $version): string
    {
        return ltrim(trim((string) $version), 'vV');
    }

    private static function assertVersion(string $version, string $constraint): string
    {
        if (! preg_match('/^\d+(\.\d+)*([-+].+)?$/', $version)) {
            throw new \InvalidArgumentException("Unreadable core version requirement: \"{$constraint}\".");
        }

        return $version;
    }
}
