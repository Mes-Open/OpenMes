<?php

namespace Database\Seeders;

use App\Support\Units\DefaultUnitAliases;
use Illuminate\Database\Seeder;

/**
 * The unit aliases ERPs commonly send ("Units", "szt", "KGM", …). Idempotent —
 * an alias that already exists is left alone.
 */
class UnitAliasSeeder extends Seeder
{
    public function run(): void
    {
        DefaultUnitAliases::seed();
    }
}
