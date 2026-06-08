<?php

namespace Database\Seeders;

use App\Models\Crew;
use App\Models\Worker;
use Illuminate\Database\Seeder;

/**
 * Demo personnel records (Workers + Crews) — backs the HR hub screens and
 * the "Operators on shift" panel until /api/v1/system/operators-on-shift
 * ships. Run with: `php artisan db:seed --class=HrDemoSeeder`. Idempotent.
 */
class HrDemoSeeder extends Seeder
{
    public function run(): void
    {
        $crews = $this->seedCrews();
        $this->seedWorkers($crews);
    }

    /** @return array<string, Crew> */
    private function seedCrews(): array
    {
        $defs = [
            ['code' => 'CREW-A', 'name' => 'Air Filter'],
            ['code' => 'CREW-H', 'name' => 'Housing'],
            ['code' => 'CREW-S', 'name' => 'Sub-assembly'],
            ['code' => 'CREW-P', 'name' => 'Pack & Ship'],
        ];

        $result = [];
        foreach ($defs as $def) {
            $crew = Crew::updateOrCreate(
                ['code' => $def['code']],
                array_merge($def, ['is_active' => true]),
            );
            $result[$def['code']] = $crew;
        }
        return $result;
    }

    /** @param array<string, Crew> $crews */
    private function seedWorkers(array $crews): void
    {
        $defs = [
            ['code' => 'OP-0184', 'name' => 'Marcin Kowalski',     'crew' => 'CREW-A'],
            ['code' => 'OP-0207', 'name' => 'Aneta Pawlak',        'crew' => 'CREW-H'],
            ['code' => 'OP-0145', 'name' => 'Piotr Wiśniewski',    'crew' => 'CREW-S'],
            ['code' => 'OP-0091', 'name' => 'Tomek Borowski',      'crew' => 'CREW-P'],
            ['code' => 'OP-0312', 'name' => 'Kasia Dąbrowska',     'crew' => 'CREW-A'],
            ['code' => 'OP-0276', 'name' => 'Jan Nowak',           'crew' => 'CREW-H'],
            ['code' => 'OP-0118', 'name' => 'Magda Lewandowska',   'crew' => 'CREW-S'],
            ['code' => 'OP-0203', 'name' => 'Bartek Mazur',        'crew' => 'CREW-P'],
        ];

        foreach ($defs as $def) {
            $crew = $crews[$def['crew']] ?? null;
            Worker::updateOrCreate(
                ['code' => $def['code']],
                [
                    'name' => $def['name'],
                    'crew_id' => $crew?->id,
                    'is_active' => true,
                ],
            );
        }
    }
}
