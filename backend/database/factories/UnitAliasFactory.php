<?php

namespace Database\Factories;

use App\Models\UnitAlias;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnitAliasFactory extends Factory
{
    protected $model = UnitAlias::class;

    public function definition(): array
    {
        static $counter = 1;

        return [
            'alias' => 'alias'.str_pad((string) $counter++, 4, '0', STR_PAD_LEFT),
            'unit' => 'pcs',
        ];
    }
}
