<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IssueTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $issueTypes = [
            [
                'code' => 'MATERIAL_DEFECT',
                'name' => 'Wada materiału',
                'severity' => 'HIGH',
                'is_blocking' => true,
                'is_active' => true,
            ],
            [
                'code' => 'MATERIAL_SHORTAGE',
                'name' => 'Brak materiału',
                'severity' => 'CRITICAL',
                'is_blocking' => true,
                'is_active' => true,
            ],
            [
                'code' => 'TOOL_FAILURE',
                'name' => 'Awaria narzędzia / urządzenia',
                'severity' => 'HIGH',
                'is_blocking' => true,
                'is_active' => true,
            ],
            [
                'code' => 'QUALITY_ISSUE',
                'name' => 'Problem jakościowy',
                'severity' => 'MEDIUM',
                'is_blocking' => false,
                'is_active' => true,
            ],
            [
                'code' => 'PROCESS_CLARIFICATION',
                'name' => 'Wymagane wyjaśnienie procesu',
                'severity' => 'MEDIUM',
                'is_blocking' => true,
                'is_active' => true,
            ],
            [
                'code' => 'SAFETY_CONCERN',
                'name' => 'Zagrożenie bezpieczeństwa',
                'severity' => 'CRITICAL',
                'is_blocking' => true,
                'is_active' => true,
            ],
            [
                'code' => 'MEASUREMENT_ERROR',
                'name' => 'Błąd pomiaru / wymiarów',
                'severity' => 'MEDIUM',
                'is_blocking' => false,
                'is_active' => true,
            ],
            [
                'code' => 'OPERATOR_ASSISTANCE',
                'name' => 'Wymagana pomoc operatora',
                'severity' => 'LOW',
                'is_blocking' => false,
                'is_active' => true,
            ],
            [
                'code' => 'OTHER',
                'name' => 'Inny problem',
                'severity' => 'MEDIUM',
                'is_blocking' => false,
                'is_active' => true,
            ],
        ];

        foreach ($issueTypes as $issueType) {
            DB::table('issue_types')->updateOrInsert(
                ['code' => $issueType['code']],
                $issueType
            );
        }
    }
}
