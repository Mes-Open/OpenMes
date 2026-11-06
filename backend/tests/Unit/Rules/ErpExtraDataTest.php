<?php

namespace Tests\Unit\Rules;

use App\Rules\ErpExtraData;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ErpExtraDataTest extends TestCase
{
    private function passes(mixed $value): bool
    {
        return Validator::make(['extra_data' => $value], ['extra_data' => [new ErpExtraData]])->passes();
    }

    /** @return array<string, array{mixed}> */
    public static function accepted(): array
    {
        return [
            'null' => [null],
            'empty object' => [[]],
            'scalars' => [['s' => 'text', 'i' => 3, 'f' => 1.5, 'b' => false, 'n' => null]],
            'list of scalars' => [['sizes' => ['S', 'M', 3, null, true]]],
            'numeric-looking key' => [['5' => 'x']],
            'exactly the size limit' => [['k' => str_repeat('x', ErpExtraData::MAX_BYTES - strlen('{"k":""}'))]],
            'max keys' => [array_combine(
                array_map(fn ($i) => "k{$i}", range(1, ErpExtraData::MAX_KEYS)),
                array_fill(0, ErpExtraData::MAX_KEYS, 1),
            )],
        ];
    }

    /** @return array<string, array{mixed}> */
    public static function rejected(): array
    {
        return [
            'string' => ['red'],
            'list' => [[1, 2]],
            'nested object' => [['a' => ['b' => 1]]],
            'list of lists' => [['a' => [[1]]]],
            'list of objects' => [['a' => [['b' => 1]]]],
            'empty key' => [['' => 'x']],
            'key too long' => [[str_repeat('k', ErpExtraData::MAX_KEY_LENGTH + 1) => 'x']],
            'over the size limit' => [['k' => str_repeat('x', ErpExtraData::MAX_BYTES)]],
            'too many keys' => [array_combine(
                array_map(fn ($i) => "k{$i}", range(1, ErpExtraData::MAX_KEYS + 1)),
                array_fill(0, ErpExtraData::MAX_KEYS + 1, 1),
            )],
        ];
    }

    #[DataProvider('accepted')]
    public function test_accepts_flat_or_two_level_data(mixed $value): void
    {
        $this->assertTrue($this->passes($value));
    }

    #[DataProvider('rejected')]
    public function test_rejects_other_shapes_and_oversized_data(mixed $value): void
    {
        $this->assertFalse($this->passes($value));
    }
}
