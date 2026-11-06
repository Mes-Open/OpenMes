<?php

namespace Tests\Unit\Support;

use App\Support\Units\UnitNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Resolution rules ERP imports rely on, without the database: the dictionary
 * is passed in, exactly as forCurrentTenant() would build it.
 */
class UnitNormalizerTest extends TestCase
{
    private function normalizer(): UnitNormalizer
    {
        return new UnitNormalizer(
            ['units' => 'pcs', 'szt' => 'pcs', 'kgm' => 'kg', 'pcs' => 'pcs', 'kg' => 'kg', 'g' => 'g', 'pal' => 'pallet'],
            ['pcs' => 'pcs', 'kg' => 'kg', 'g' => 'g', 'pallet' => 'pallet'],
        );
    }

    public function test_key_ignores_case_spacing_and_a_trailing_dot(): void
    {
        $this->assertSame('szt', UnitNormalizer::key(' Szt. '));
        $this->assertSame('stück', UnitNormalizer::key('Stück'));
        $this->assertSame('m 2', UnitNormalizer::key("M \t 2"));
        $this->assertSame('', UnitNormalizer::key(null));
    }

    public function test_an_alias_resolves_to_its_canonical_unit(): void
    {
        $this->assertSame(['unit' => 'pcs', 'known' => true], $this->normalizer()->resolve('Units'));
        $this->assertSame(['unit' => 'pcs', 'known' => true], $this->normalizer()->resolve('szt.'));
        $this->assertSame(['unit' => 'kg', 'known' => true], $this->normalizer()->resolve('KGM'));
    }

    public function test_a_canonical_unit_spelled_differently_is_known(): void
    {
        $this->assertSame(['unit' => 'pallet', 'known' => true], $this->normalizer()->resolve('PALLET'));
    }

    public function test_an_unknown_unit_comes_back_trimmed_and_flagged(): void
    {
        $this->assertSame(['unit' => 'Fässer', 'known' => false], $this->normalizer()->resolve('  Fässer '));
    }

    public function test_an_empty_unit_is_null_and_not_flagged(): void
    {
        $this->assertSame(['unit' => null, 'known' => true], $this->normalizer()->resolve('  '));
    }

    public function test_aliases_are_not_chained(): void
    {
        // "box" → "pal", and "pal" is itself an alias of "pallet". One hop
        // only: "box" means "pal", it never silently becomes "pallet".
        $units = new UnitNormalizer(['box' => 'pal', 'pal' => 'pallet'], ['pal' => 'pal', 'pallet' => 'pallet']);

        $this->assertSame('pal', $units->resolve('box')['unit']);
    }

    public function test_same_compares_resolved_units(): void
    {
        $units = $this->normalizer();

        $this->assertTrue($units->same('szt.', 'pcs'));
        $this->assertTrue($units->same('KGM', 'kg'));
        $this->assertFalse($units->same('g', 'kg'));
        $this->assertTrue($units->same('Fass', 'fass.'));
        $this->assertFalse($units->same('Fass', 'kg'));
    }
}
