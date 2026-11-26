<?php

namespace Database\Seeders;

use App\Models\BlendFormula;
use App\Models\SensoryEvaluation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Eight blind samples per evaluator. Admin and QA get different blind codes,
 * so a login can prove that one evaluator cannot see the other's queue or scores.
 */
class SensoryEvaluationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $qa = User::where('username', 'qa.lead')->firstOrFail();
        Auth::login($admin);

        $formulas = BlendFormula::whereIn('code', [
            'BF-JASMINE-V01',
            'BF-JASMINE-V02',
            'BF-JASMINE-V03',
            'BF-JASMINE-V04',
        ])->orderBy('version')->get()->values();

        if ($formulas->count() !== 4) {
            throw new \RuntimeException('BlendFormulaDemoSeeder must run before SensoryEvaluationDemoSeeder.');
        }

        $standardScores = [
            'appearance_score' => 9,
            'color_score' => 9,
            'aroma_score' => 9,
            'taste_score' => 8,
            'leaf_score' => 9,
        ];

        $evaluators = [
            ['user' => $admin, 'prefix' => 'BC-JSM-A'],
            ['user' => $qa, 'prefix' => 'BC-JSM-Q'],
        ];

        foreach ($evaluators as $evaluator) {
            for ($index = 0; $index < 8; $index++) {
                $formula = $formulas[$index % 4];
                $hasStandard = filled($formula->standard_sample_code);

                SensoryEvaluation::updateOrCreate(
                    ['blind_code' => $evaluator['prefix'].str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)],
                    [
                        'blend_formula_id' => $formula->id,
                        'evaluator_id' => $evaluator['user']->id,
                        'sample_sequence' => $index + 1,
                        'status' => SensoryEvaluation::STATUS_PENDING,
                        'appearance_score' => null,
                        'color_score' => null,
                        'aroma_score' => null,
                        'taste_score' => null,
                        'leaf_score' => null,
                        'comment' => null,
                        'standard_sample_code' => $hasStandard ? $formula->standard_sample_code : null,
                        'standard_scores' => $hasStandard ? $standardScores : null,
                        'submitted_at' => null,
                    ]
                );
            }
        }

        $this->command?->info('Sensory evaluation demo seeded: '.SensoryEvaluation::count().' samples (8 per evaluator)');
    }
}
