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
                'name' => 'Material Defect',
                'severity' => 'HIGH',
                'is_blocking' => true,
                'is_active' => true,
            ],
            [
                'code' => 'MATERIAL_SHORTAGE',
                'name' => 'Material Shortage',
                'severity' => 'CRITICAL',
                'is_blocking' => true,
                'is_active' => true,
            ],
            [
                'code' => 'TOOL_FAILURE',
                'name' => 'Tool/Equipment Failure',
                'severity' => 'HIGH',
                'is_blocking' => true,
                'is_active' => true,
            ],
            [
                'code' => 'QUALITY_ISSUE',
                'name' => 'Quality Issue',
                'severity' => 'MEDIUM',
                'is_blocking' => false,
                'is_active' => true,
            ],
            [
                'code' => 'PROCESS_CLARIFICATION',
                'name' => 'Process Clarification Needed',
                'severity' => 'MEDIUM',
                'is_blocking' => true,
                'is_active' => true,
            ],
            [
                'code' => 'SAFETY_CONCERN',
                'name' => 'Safety Concern',
                'severity' => 'CRITICAL',
                'is_blocking' => true,
                'is_active' => true,
            ],
            [
                'code' => 'MEASUREMENT_ERROR',
                'name' => 'Measurement/Dimension Error',
                'severity' => 'MEDIUM',
                'is_blocking' => false,
                'is_active' => true,
            ],
            [
                'code' => 'OPERATOR_ASSISTANCE',
                'name' => 'Operator Assistance Required',
                'severity' => 'LOW',
                'is_blocking' => false,
                'is_active' => true,
            ],
            [
                'code' => 'OTHER',
                'name' => 'Other Issue',
                'severity' => 'MEDIUM',
                'is_blocking' => false,
                'is_active' => true,
            ],
        ];

        foreach ($issueTypes as $issueType) {
            DB::table('issue_types')->insert($issueType);
        }
    }
}
