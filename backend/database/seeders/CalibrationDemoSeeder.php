<?php

namespace Database\Seeders;

use App\Models\CalibrationRecord;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Eight instruments with a deliberate mix: two locked, two inside the 7-day
 * warning window, and four comfortably active. The overdue scale is left with
 * status ACTIVE so the page proves that overdue is derived, not hand-set.
 */
class CalibrationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('username', 'admin')->first();
        $qa = User::where('username', 'qa.lead')->first();

        $records = [
            [
                'equipment_code' => 'NT-SCALE-01',
                'equipment_name' => '原料称重台秤',
                'equipment_kind' => '台秤',
                'location' => '原料库 · 收料口',
                'last_calibrated_at' => now()->subDays(25),
                'next_due_at' => now()->addDays(50),
                'certificate_no' => 'CAL-NT-SC-01-2026',
                'last_result' => 'PASS',
                'status' => CalibrationRecord::STATUS_ACTIVE,
                'lock_reason' => null,
                'unlock_condition' => null,
                'responsible_user_id' => $admin?->id,
                'notes' => '量程 0–100 kg，分度值 50 g；每批投料前先归零。',
            ],
            [
                'equipment_code' => 'NT-SCALE-02',
                'equipment_name' => '小料称',
                'equipment_kind' => '台秤',
                'location' => '配料间 · 小料台',
                'last_calibrated_at' => now()->subDays(86),
                'next_due_at' => now()->addDays(5),
                'certificate_no' => 'CAL-NT-SC-02-2026',
                'last_result' => 'PASS',
                'status' => CalibrationRecord::STATUS_ACTIVE,
                'lock_reason' => null,
                'unlock_condition' => null,
                'responsible_user_id' => $admin?->id,
                'notes' => '量程 0–5 kg，分度值 1 g；称糖浆、果泥小样。',
            ],
            [
                'equipment_code' => 'NT-SCALE-03',
                'equipment_name' => '成品称',
                'equipment_kind' => '台秤',
                'location' => '灌装间 · 成品复核',
                'last_calibrated_at' => now()->subDays(92),
                'next_due_at' => now()->subDays(2),
                'certificate_no' => 'CAL-NT-SC-03-2026',
                'last_result' => 'PASS',
                'status' => CalibrationRecord::STATUS_ACTIVE,
                'lock_reason' => '校准已过期 2 天，称重入口会拦截这台秤。',
                'unlock_condition' => '完成成品称校准并上传新证书，由 QA 复核后解锁。',
                'responsible_user_id' => $qa?->id,
                'notes' => '量程 0–50 kg；超期期间只允许查看历史称重，不允许新增。',
            ],
            [
                'equipment_code' => 'NT-SCALE-04',
                'equipment_name' => '包装秤',
                'equipment_kind' => '台秤',
                'location' => '包装间 · 封口线',
                'last_calibrated_at' => now()->subDays(40),
                'next_due_at' => now()->addDays(20),
                'certificate_no' => 'CAL-NT-SC-04-2026',
                'last_result' => 'FAIL',
                'status' => CalibrationRecord::STATUS_LOCKED,
                'lock_reason' => '传感器漂移，厂家校准中；现场读数不可信。',
                'unlock_condition' => '更换传感器并完成厂家校准，上传证书后由 QA 解锁。',
                'responsible_user_id' => $qa?->id,
                'notes' => '锁定与到期日无关；手动锁定优先于日期。',
            ],
            [
                'equipment_code' => 'NT-THERMO-01',
                'equipment_name' => '萃取温度计',
                'equipment_kind' => '温度计',
                'location' => '茶底萃取线 · 萃取罐',
                'last_calibrated_at' => now()->subDays(60),
                'next_due_at' => now()->addDays(35),
                'certificate_no' => 'CAL-NT-TH-01-2026',
                'last_result' => 'PASS',
                'status' => CalibrationRecord::STATUS_ACTIVE,
                'lock_reason' => null,
                'unlock_condition' => null,
                'responsible_user_id' => $admin?->id,
                'notes' => '0–120 °C；每班开班用冰水混合物点检。',
            ],
            [
                'equipment_code' => 'NT-PH-01',
                'equipment_name' => 'pH 计',
                'equipment_kind' => 'pH 计',
                'location' => '化验室 · 检验台',
                'last_calibrated_at' => now()->subDays(90),
                'next_due_at' => now()->addDays(3),
                'certificate_no' => 'CAL-NT-PH-01-2026',
                'last_result' => 'PASS',
                'status' => CalibrationRecord::STATUS_ACTIVE,
                'lock_reason' => null,
                'unlock_condition' => null,
                'responsible_user_id' => $qa?->id,
                'notes' => '每次使用前用 pH 4.00 / 6.86 缓冲液两点校准。',
            ],
            [
                'equipment_code' => 'NT-MOIST-01',
                'equipment_name' => '水分测定仪',
                'equipment_kind' => '水分仪',
                'location' => '化验室 · 茶底检验',
                'last_calibrated_at' => now()->subDays(30),
                'next_due_at' => now()->addDays(90),
                'certificate_no' => 'CAL-NT-MO-01-2026',
                'last_result' => 'PASS',
                'status' => CalibrationRecord::STATUS_ACTIVE,
                'lock_reason' => null,
                'unlock_condition' => null,
                'responsible_user_id' => $qa?->id,
                'notes' => '茶底含水率 5.0–6.5% 的判定依据。',
            ],
            [
                'equipment_code' => 'NT-LAB-BAL-01',
                'equipment_name' => '分析天平',
                'equipment_kind' => '分析天平',
                'location' => '化验室 · 天平室',
                'last_calibrated_at' => now()->subDays(180),
                'next_due_at' => now()->addDays(14),
                'certificate_no' => 'CAL-NT-LB-01-2026',
                'last_result' => 'PASS',
                'status' => CalibrationRecord::STATUS_ACTIVE,
                'lock_reason' => null,
                'unlock_condition' => null,
                'responsible_user_id' => $qa?->id,
                'notes' => '万分之一天平；称量前预热 30 分钟。',
            ],
        ];

        foreach ($records as $record) {
            CalibrationRecord::updateOrCreate(
                ['equipment_code' => $record['equipment_code']],
                $record
            );
        }

        $this->command?->info('Calibration demo seeded: '.CalibrationRecord::count().' instruments (2 locked / 2 due soon)');
    }
}
