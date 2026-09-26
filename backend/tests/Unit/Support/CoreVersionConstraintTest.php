<?php

namespace Tests\Unit\Support;

use App\Support\CoreVersionConstraint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CoreVersionConstraintTest extends TestCase
{
    /** @return array<string, array{0: ?string, 1: string, 2: bool}> */
    public static function constraints(): array
    {
        return [
            // A module that declares nothing runs anywhere. Most do.
            'no constraint' => [null, '0.25.0', true],
            'empty constraint' => ['', '0.25.0', true],

            'at least, older core' => ['>=0.25.0', '0.24.3', false],
            'at least, exact core' => ['>=0.25.0', '0.25.0', true],
            'at least, newer core' => ['>=0.25.0', '0.26.1', true],

            // The version this project prints carries a v; manifests do not.
            'v prefix on the core version' => ['>=0.25.0', 'v0.25.0', true],
            'v prefix in the constraint' => ['>=v0.25.0', '0.25.0', true],

            // A bare version reads as "or newer" — an exact match would strand
            // every module the day core is patched.
            'bare version, newer core' => ['0.25.0', '0.25.4', true],
            'bare version, older core' => ['0.25.0', '0.24.9', false],

            'below' => ['<0.25.0', '0.24.3', true],
            'below, equal' => ['<0.25.0', '0.25.0', false],
            'exactly' => ['=0.25.0', '0.25.0', true],
            'exactly, patched' => ['=0.25.0', '0.25.1', false],
            'not' => ['!=0.25.0', '0.25.1', true],

            // Ordering is numeric, not lexical: 0.9 is older than 0.25.
            'two digit minor beats single digit' => ['>=0.25.0', '0.9.0', false],

            'whitespace' => ['  >= 0.25.0  ', '0.25.0', true],
            'short version' => ['>=0.25', '0.25.0', true],
        ];
    }

    #[DataProvider('constraints')]
    public function test_it_reads_a_constraint(?string $constraint, string $core, bool $expected): void
    {
        $this->assertSame($expected, CoreVersionConstraint::isSatisfied($constraint, $core));
    }

    /** @return array<string, array{0: string}> */
    public static function nonsense(): array
    {
        return [
            'words' => ['latest'],
            'operator with no version' => ['>='],
            'two versions' => ['>=0.25.0 <0.26.0'],
            'caret is not supported' => ['^0.25.0'],
            'tilde is not supported' => ['~0.25.0'],
        ];
    }

    #[DataProvider('nonsense')]
    public function test_an_unreadable_constraint_is_refused_rather_than_guessed_at(string $constraint): void
    {
        // Silently treating a typo as "any version" is the exact failure this
        // check exists to prevent, so it throws and the caller refuses.
        $this->expectException(\InvalidArgumentException::class);

        CoreVersionConstraint::isSatisfied($constraint, '0.25.0');
    }
}
