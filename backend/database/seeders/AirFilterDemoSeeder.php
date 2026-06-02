<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Demo data for an air-filter manufacturing plant. Mirrors the tablet
 * "Schedule & dispatch" design fixture set so screenshots and demos line up:
 *
 *  - Lines L-01 .. L-04
 *  - Product types HEPA-13 Std/Slim, Pre-filter G4, Carbon X2, HVAC cassette
 *  - 7-step HEPA-13 process template
 *  - 6 work orders (WO-186-001..005 due today/tomorrow + WO-185-088 done)
 *  - A running batch on WO-186-001 with steps 1-2 DONE, step 3 IN_PROGRESS
 *
 * Run with: `php artisan db:seed --class=AirFilterDemoSeeder`
 *
 * The seeder is upsert-safe (uses `updateOrCreate` / `updateOrInsert`) so it
 * can be re-run on top of itself without producing duplicates.
 */
class AirFilterDemoSeeder extends Seeder
{
    public function run(): void
    {
        $lines = $this->seedLines();
        $workstations = $this->seedWorkstations($lines);
        $productTypes = $this->seedProductTypes();
        $template = $this->seedHepaProcessTemplate($productTypes['HEPA13_STD'], $workstations);
        $users = $this->seedUsers($lines);
        $workOrders = $this->seedWorkOrders($lines, $productTypes, $template);
        $this->seedActiveBatch($workOrders['WO-186-001'], $template, $users['operator-mk']);
    }

    private function seedLines(): array
    {
        $defs = [
            ['code' => 'L-01', 'name' => 'Air Filter', 'description' => 'HEPA pleat assembly line'],
            ['code' => 'L-02', 'name' => 'Housing', 'description' => 'Frame and housing fabrication'],
            ['code' => 'L-03', 'name' => 'Sub-assy', 'description' => 'Sub-assembly and integration'],
            ['code' => 'L-04', 'name' => 'Pack & Ship', 'description' => 'Final packing and dispatch'],
        ];

        $result = [];
        foreach ($defs as $def) {
            $line = Line::updateOrCreate(['code' => $def['code']], array_merge($def, ['is_active' => true]));
            $result[$def['code']] = $line;
        }

        return $result;
    }

    private function seedWorkstations(array $lines): array
    {
        $defs = [
            // L-01 — process steps shown in the design's right detail panel.
            ['line' => 'L-01', 'code' => 'WS-MK-01',  'name' => 'Material Kit Picking',  'workstation_type' => 'picking'],
            ['line' => 'L-01', 'code' => 'WS-FR-01',  'name' => 'Frame Stamping Press',   'workstation_type' => 'press'],
            ['line' => 'L-01', 'code' => 'WS-PA-01',  'name' => 'Pleat Assembly Table',   'workstation_type' => 'assembly'],
            ['line' => 'L-01', 'code' => 'WS-AB-01',  'name' => 'Adhesive Bond Booth',    'workstation_type' => 'bonding'],
            ['line' => 'L-01', 'code' => 'WS-QC-01',  'name' => 'QC Visual Bench',        'workstation_type' => 'qc'],
            ['line' => 'L-01', 'code' => 'WS-PK-01',  'name' => 'Packaging Line 10/box',  'workstation_type' => 'packing'],
            ['line' => 'L-01', 'code' => 'WS-PH-01',  'name' => 'Pallet Handoff',         'workstation_type' => 'shipping'],
            // L-02 — housing line workstations.
            ['line' => 'L-02', 'code' => 'WS-HM-01',  'name' => 'Housing Mould Press',    'workstation_type' => 'press'],
            ['line' => 'L-02', 'code' => 'WS-HQC-01', 'name' => 'Housing QC',             'workstation_type' => 'qc'],
            // L-03 — sub-assembly.
            ['line' => 'L-03', 'code' => 'WS-SA-01',  'name' => 'Sub-assembly Bench A',   'workstation_type' => 'assembly'],
            ['line' => 'L-03', 'code' => 'WS-SA-02',  'name' => 'Sub-assembly Bench B',   'workstation_type' => 'assembly'],
            // L-04 — pack & ship.
            ['line' => 'L-04', 'code' => 'WS-PK-04',  'name' => 'Pack Station',           'workstation_type' => 'packing'],
            ['line' => 'L-04', 'code' => 'WS-SHIP-1', 'name' => 'Pallet Wrap & Label',    'workstation_type' => 'shipping'],
        ];

        $result = [];
        foreach ($defs as $def) {
            $ws = Workstation::updateOrCreate(
                ['code' => $def['code']],
                [
                    'line_id' => $lines[$def['line']]->id,
                    'name' => $def['name'],
                    'workstation_type' => $def['workstation_type'],
                    'is_active' => true,
                ]
            );
            $result[$def['code']] = $ws;
        }

        return $result;
    }

    private function seedProductTypes(): array
    {
        $defs = [
            ['code' => 'HEPA13_STD',  'name' => 'HEPA-13 Standard',  'description' => 'Standard HEPA-13 pleated air filter',     'unit_of_measure' => 'pcs'],
            ['code' => 'HEPA13_SLIM', 'name' => 'HEPA-13 Slim',      'description' => 'Slim-profile HEPA-13 filter for low-clearance housings', 'unit_of_measure' => 'pcs'],
            ['code' => 'PREFILTER',   'name' => 'Pre-filter G4',     'description' => 'Coarse pre-filter (G4 grade), upstream of HEPA',         'unit_of_measure' => 'pcs'],
            ['code' => 'CARBON',      'name' => 'Carbon X2',         'description' => 'Activated-carbon odour and VOC filter',                  'unit_of_measure' => 'pcs'],
            ['code' => 'HVAC',        'name' => 'HVAC cassette',     'description' => 'HVAC cassette filter, multi-stage media stack',          'unit_of_measure' => 'pcs'],
        ];

        $result = [];
        foreach ($defs as $def) {
            $pt = ProductType::updateOrCreate(['code' => $def['code']], array_merge($def, ['is_active' => true]));
            $result[$def['code']] = $pt;
        }

        return $result;
    }

    private function seedHepaProcessTemplate(ProductType $productType, array $ws): ProcessTemplate
    {
        $template = ProcessTemplate::updateOrCreate(
            ['product_type_id' => $productType->id, 'version' => 1],
            ['name' => 'HEPA-13 Standard — assembly v1', 'is_active' => true]
        );

        $steps = [
            [1, 'Material kit pickup',     'Pull the BOM kit (pleat sheet, frame blank, adhesive, gasket) from staging.', 5,  $ws['WS-MK-01']],
            [2, 'Frame stamping',          'Stamp the aluminium frame blank on the housing press; check perpendicularity.', 4, $ws['WS-FR-01']],
            [3, 'Pleat assembly',          'Pleat the filter media and slot into frame. Maintain pitch ±0.5 mm.', 8, $ws['WS-PA-01']],
            [4, 'Adhesive bonding',        'Apply two-part adhesive bead around perimeter; cure 6 min at 60 °C.', 10, $ws['WS-AB-01']],
            [5, 'QC visual inspection',    'Inspect for pleat collapse, adhesive squeeze-out, gasket fit. Photograph defects.', 3, $ws['WS-QC-01']],
            [6, 'Packaging (10/box)',      'Pack 10 filters per carton with desiccant; apply lot label.', 4, $ws['WS-PK-01']],
            [7, 'Pallet handoff',          'Stack cartons on pallet, wrap, hand off to dispatch with batch sheet.', 3, $ws['WS-PH-01']],
        ];

        foreach ($steps as [$stepNo, $name, $instruction, $duration, $workstation]) {
            DB::table('template_steps')->updateOrInsert(
                ['process_template_id' => $template->id, 'step_number' => $stepNo],
                [
                    'name' => $name,
                    'instruction' => $instruction,
                    'estimated_duration_minutes' => $duration,
                    'workstation_id' => $workstation?->id,
                    'created_at' => now(),
                ]
            );
        }

        return $template;
    }

    private function seedUsers(array $lines): array
    {
        $supervisorRole = Role::where('name', 'Supervisor')->first();
        $operatorRole = Role::where('name', 'Operator')->first();

        $supervisor = User::updateOrCreate(
            ['username' => 'peter.wilson'],
            [
                'name' => 'Peter Wilson',
                'email' => 'peter.wilson@airfilter.local',
                'password' => Hash::make('Supervisor1!'),
                'account_type' => 'user',
                'force_password_change' => false,
            ]
        );
        if ($supervisorRole && ! $supervisor->hasRole('Supervisor')) {
            $supervisor->assignRole($supervisorRole);
        }
        $supervisor->lines()->syncWithoutDetaching(array_map(fn ($l) => $l->id, $lines));

        $operators = [
            'operator-mk' => [
                'username' => 'm.kowalski',
                'name' => 'M. Kowalski',
                'email' => 'm.kowalski@airfilter.local',
                'lines' => ['L-01'],
            ],
            'operator-an' => [
                'username' => 'a.nowak',
                'name' => 'A. Nowak',
                'email' => 'a.nowak@airfilter.local',
                'lines' => ['L-02'],
            ],
        ];

        $result = ['supervisor' => $supervisor];
        foreach ($operators as $key => $def) {
            $user = User::updateOrCreate(
                ['username' => $def['username']],
                [
                    'name' => $def['name'],
                    'email' => $def['email'],
                    'password' => Hash::make('Operator1!'),
                    'account_type' => 'user',
                    'force_password_change' => false,
                ]
            );
            if ($operatorRole && ! $user->hasRole('Operator')) {
                $user->assignRole($operatorRole);
            }
            $lineIds = array_map(fn ($code) => $lines[$code]->id, $def['lines']);
            $user->lines()->syncWithoutDetaching($lineIds);
            $result[$key] = $user;
        }

        return $result;
    }

    private function seedWorkOrders(array $lines, array $pt, ProcessTemplate $hepaTemplate): array
    {
        $today = now()->copy();
        // We pin due times so a screenshot taken at any clock-hour still sees
        // "Today" entries scheduled for later in the same day.
        $at = fn (int $h, int $m) => $today->copy()->setTime($h, $m);

        $defs = [
            [
                'order_no' => 'WO-186-001',
                'line' => 'L-01',
                'product' => 'HEPA13_STD',
                'planned_qty' => 250,
                'produced_qty' => 108,
                'status' => WorkOrder::STATUS_IN_PROGRESS,
                'priority' => 4,
                'due_date' => $at(14, 30),
                'description' => 'Standard HEPA-13, B2B order — Filtex distribution.',
                'snapshot_template' => $hepaTemplate,
            ],
            [
                'order_no' => 'WO-186-002',
                'line' => 'L-01',
                'product' => 'HEPA13_SLIM',
                'planned_qty' => 120,
                'produced_qty' => 0,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => 3,
                'due_date' => $at(17, 0),
                'description' => 'HEPA-13 Slim for HVAC retrofit.',
            ],
            [
                'order_no' => 'WO-186-003',
                'line' => 'L-02',
                'product' => 'PREFILTER',
                'planned_qty' => 400,
                'produced_qty' => 0,
                'status' => WorkOrder::STATUS_ACCEPTED,
                'priority' => 2,
                'due_date' => $at(15, 45),
                'description' => 'G4 pre-filters — bulk stock replenishment.',
            ],
            [
                'order_no' => 'WO-186-004',
                'line' => 'L-02',
                'product' => 'CARBON',
                'planned_qty' => 180,
                'produced_qty' => 112,
                'status' => WorkOrder::STATUS_PAUSED,
                'priority' => 3,
                'due_date' => $at(16, 0),
                'description' => 'Carbon X2 — paused pending raw-material delivery.',
            ],
            [
                'order_no' => 'WO-186-005',
                'line' => 'L-03',
                'product' => 'HVAC',
                'planned_qty' => 60,
                'produced_qty' => 0,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => 1,
                'due_date' => $today->copy()->addDay()->setTime(10, 0),
                'description' => 'HVAC cassettes — light run, scheduled for tomorrow.',
            ],
            [
                'order_no' => 'WO-185-088',
                'line' => 'L-01',
                'product' => 'HEPA13_STD',
                'planned_qty' => 500,
                'produced_qty' => 500,
                'status' => WorkOrder::STATUS_DONE,
                'priority' => 2,
                'due_date' => $today->copy()->subDay()->setTime(14, 0),
                'description' => 'HEPA-13 Standard — completed previous shift.',
                'completed_at' => $today->copy()->subHours(20),
            ],
        ];

        $result = [];
        foreach ($defs as $def) {
            $payload = [
                'line_id' => $lines[$def['line']]->id,
                'product_type_id' => $pt[$def['product']]->id,
                'planned_qty' => $def['planned_qty'],
                'produced_qty' => $def['produced_qty'],
                'status' => $def['status'],
                'priority' => $def['priority'],
                'due_date' => $def['due_date'],
                'description' => $def['description'],
            ];
            if (! empty($def['completed_at'])) {
                $payload['completed_at'] = $def['completed_at'];
            }
            if (! empty($def['snapshot_template'])) {
                $template = $def['snapshot_template'];
                $payload['process_snapshot'] = [
                    'template_id' => $template->id,
                    'template_name' => $template->name,
                    'template_version' => $template->version,
                    'snapshotted_at' => now()->toIso8601String(),
                ];
            }

            $wo = WorkOrder::updateOrCreate(['order_no' => $def['order_no']], $payload);
            $result[$def['order_no']] = $wo;
        }

        return $result;
    }

    /**
     * Seed a single running batch on WO-186-001 with three steps already
     * touched: 1 + 2 DONE, 3 IN_PROGRESS, 4-7 PENDING. Matches the design's
     * right-rail process panel exactly.
     */
    private function seedActiveBatch(WorkOrder $wo, ProcessTemplate $template, User $operator): void
    {
        $startedAt = now()->copy()->subHours(6)->setTime(6, 42);

        $batch = Batch::updateOrCreate(
            ['work_order_id' => $wo->id, 'batch_number' => 2],
            [
                'target_qty' => 90,
                'produced_qty' => 108,
                'status' => Batch::STATUS_IN_PROGRESS,
                'started_at' => $startedAt,
                'lot_number' => 'LOT-2026-' . str_pad((string) $wo->id, 4, '0', STR_PAD_LEFT),
                // varchar(10): trigger code, not a timestamp. Values: on_start / on_release.
                'lot_assigned_at' => 'on_start',
                'scrap_qty' => 3,
            ]
        );

        $templateSteps = DB::table('template_steps')
            ->where('process_template_id', $template->id)
            ->orderBy('step_number')
            ->get();

        foreach ($templateSteps as $tStep) {
            $status = match ((int) $tStep->step_number) {
                1, 2 => BatchStep::STATUS_DONE,
                3 => BatchStep::STATUS_IN_PROGRESS,
                default => BatchStep::STATUS_PENDING,
            };

            $startedStepAt = match ((int) $tStep->step_number) {
                1 => $startedAt,
                2 => $startedAt->copy()->addMinutes(8),
                3 => $startedAt->copy()->addMinutes(20),
                default => null,
            };
            $completedStepAt = match ((int) $tStep->step_number) {
                1 => $startedAt->copy()->addMinutes(7),
                2 => $startedAt->copy()->addMinutes(15),
                default => null,
            };
            $duration = $completedStepAt && $startedStepAt
                ? $completedStepAt->diffInMinutes($startedStepAt)
                : null;

            BatchStep::updateOrCreate(
                ['batch_id' => $batch->id, 'step_number' => $tStep->step_number],
                [
                    'name' => $tStep->name,
                    'instruction' => $tStep->instruction,
                    'workstation_id' => $tStep->workstation_id,
                    'status' => $status,
                    'started_at' => $startedStepAt,
                    'completed_at' => $completedStepAt,
                    'duration_minutes' => $duration,
                    'started_by_id' => $startedStepAt ? $operator->id : null,
                    'completed_by_id' => $completedStepAt ? $operator->id : null,
                ]
            );
        }
    }
}
