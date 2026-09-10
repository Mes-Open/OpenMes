<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\BomItem;
use App\Models\Issue;
use App\Models\IssueType;
use App\Models\Line;
use App\Models\MaintenanceEvent;
use App\Models\MaintenanceSchedule;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\MaterialType;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\Shift;
use App\Models\Tool;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderPlacement;
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
 *  - 16 work orders: WO-186-001..005 due today, WO-185-088 done, and a
 *    fortnight of scheduled work ahead so the planner's horizon is not empty
 *  - A running batch on WO-186-001 with steps 1-2 DONE, step 3 IN_PROGRESS
 *  - Operator-reported issues, one per lifecycle state (open → closed)
 *  - A multi-level BOM. Five sub-assemblies, each with its own routing, so an
 *    explosion descends rather than stopping at the first manufactured line:
 *      HEPA-13 Std → pleat pack → media
 *      Carbon X2   → cartridge → mesh cage → mesh + seal   (three levels)
 *      Carbon X2 and Pre-filter G4 both draw the same moulded shell, so
 *      netting has to sum two parents' demand before deciding to make it
 *  - Received material lots, one per lot status (released, quarantine, rejected,
 *    consumed, expired)
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
        $templates = $this->seedProcessTemplates($productTypes, $workstations);
        $users = $this->seedUsers($lines);
        $materials = $this->seedMaterials($templates);
        $this->seedBom($templates, $materials);
        $this->seedMaterialLots($materials);
        $this->seedShifts();
        $workOrders = $this->seedWorkOrders($lines, $productTypes, $templates);
        $this->seedActiveBatch($workOrders['WO-186-001'], $templates['HEPA13_STD'], $users['operator-mk']);
        $this->seedMultiLinePlacements($lines, $workOrders);
        $this->seedIssues($workOrders, $users);
        $this->seedMaintenance($lines, $workstations, $users);
    }

    /**
     * Round-the-clock shift cover. The planner lays orders out against shifts and
     * the shift monitor fills whichever one is running, so without these both
     * fall back to a synthetic window — and the monitor looks dead outside it.
     */
    private function seedShifts(): void
    {
        $defs = [
            ['code' => 'A', 'name' => 'Morning',   'start_time' => '06:00', 'end_time' => '14:00', 'sort_order' => 1],
            ['code' => 'B', 'name' => 'Afternoon', 'start_time' => '14:00', 'end_time' => '22:00', 'sort_order' => 2],
            ['code' => 'C', 'name' => 'Night',     'start_time' => '22:00', 'end_time' => '06:00', 'sort_order' => 3],
        ];

        foreach ($defs as $def) {
            Shift::updateOrCreate(
                ['code' => $def['code']],
                array_merge($def, [
                    // Every day, so a demo opened at the weekend still has a
                    // shift in progress.
                    'days_of_week' => [1, 2, 3, 4, 5, 6, 7],
                    'line_id' => null,
                    'is_active' => true,
                ])
            );
        }
    }

    /**
     * Maintenance the planner can show: tools, the recurring schedules its "Add
     * maintenance" modal offers, and real events in the current week so the
     * board has tiles on it rather than only orders.
     *
     * The corrective one is the counterpart to the acknowledged carbon-press
     * issue — the same fault, seen from the maintenance side.
     *
     * @param  array<string, Line>  $lines
     * @param  array<string, Workstation>  $ws
     * @param  array<string, User>  $users
     */
    private function seedMaintenance(array $lines, array $ws, array $users): void
    {
        $tools = [];
        $toolDefs = [
            ['code' => 'TL-DIE-01',    'name' => 'Frame stamping die',   'description' => 'Progressive die for the HEPA-13 aluminium frame.'],
            ['code' => 'TL-NOZZLE-01', 'name' => 'Adhesive nozzle set',  'description' => 'Two-part adhesive applicator nozzles, bonding booth.'],
            ['code' => 'TL-MOULD-G4',  'name' => 'Housing mould G4',     'description' => 'Injection mould for the G4 pre-filter housing.'],
        ];

        foreach ($toolDefs as $def) {
            $tools[$def['code']] = Tool::updateOrCreate(
                ['code' => $def['code']],
                array_merge($def, ['status' => 'available', 'next_service_at' => now()->addDays(21)->toDateString()])
            );
        }

        $supervisor = $users['supervisor'];

        $scheduleDefs = [
            ['name' => 'Weekly press lubrication',   'line' => 'L-01', 'ws' => 'WS-FR-01', 'tool' => 'TL-DIE-01',    'type' => MaintenanceEvent::TYPE_PLANNED,    'frequency' => 'weekly',    'interval' => 1, 'dueInDays' => 3],
            ['name' => 'Monthly extraction service', 'line' => 'L-01', 'ws' => 'WS-AB-01', 'tool' => 'TL-NOZZLE-01', 'type' => MaintenanceEvent::TYPE_INSPECTION, 'frequency' => 'monthly',   'interval' => 1, 'dueInDays' => 12],
            ['name' => 'Quarterly mould service',    'line' => 'L-02', 'ws' => null,       'tool' => 'TL-MOULD-G4',  'type' => MaintenanceEvent::TYPE_PLANNED,    'frequency' => 'quarterly', 'interval' => 1, 'dueInDays' => 30],
        ];

        foreach ($scheduleDefs as $def) {
            MaintenanceSchedule::updateOrCreate(
                ['name' => $def['name']],
                [
                    'line_id' => $lines[$def['line']]->id,
                    'workstation_id' => $def['ws'] ? $ws[$def['ws']]->id : null,
                    'tool_id' => $tools[$def['tool']]->id,
                    'event_type' => $def['type'],
                    'assigned_to_id' => $supervisor->id,
                    'frequency' => $def['frequency'],
                    'interval_value' => $def['interval'],
                    'preferred_time' => '06:00',
                    'lead_time_days' => 2,
                    'next_due_at' => now()->addDays($def['dueInDays'])->setTime(6, 0),
                    'is_active' => true,
                ]
            );
        }

        $eventDefs = [
            // Already done — yesterday's planned job, so the board shows history.
            ['title' => 'Pleat table belt change', 'line' => 'L-01', 'ws' => 'WS-PA-01', 'tool' => null,
                'type' => MaintenanceEvent::TYPE_PLANNED, 'status' => MaintenanceEvent::STATUS_COMPLETED,
                'startsInHours' => -26, 'durationMinutes' => 90,
                'description' => 'Drive belt replaced on the pleat table and re-tensioned.'],
            // Running right now — the maintenance side of the carbon press issue.
            ['title' => 'Carbon press pressure fault', 'line' => 'L-02', 'ws' => null, 'tool' => 'TL-MOULD-G4',
                'type' => MaintenanceEvent::TYPE_CORRECTIVE, 'status' => MaintenanceEvent::STATUS_IN_PROGRESS,
                'startsInHours' => -1, 'durationMinutes' => 180,
                'description' => 'Press will not hold 6 bar. Stripping the regulator — line paused.'],
            // Later today.
            ['title' => 'Bonding booth extraction check', 'line' => 'L-01', 'ws' => 'WS-AB-01', 'tool' => 'TL-NOZZLE-01',
                'type' => MaintenanceEvent::TYPE_INSPECTION, 'status' => MaintenanceEvent::STATUS_PENDING,
                'startsInHours' => 6, 'durationMinutes' => 60,
                'description' => 'Airflow and filter check on the bonding booth extraction.'],
            // Tomorrow morning.
            ['title' => 'Frame press weekly lubrication', 'line' => 'L-01', 'ws' => 'WS-FR-01', 'tool' => 'TL-DIE-01',
                'type' => MaintenanceEvent::TYPE_PLANNED, 'status' => MaintenanceEvent::STATUS_PENDING,
                'startsInHours' => 22, 'durationMinutes' => 90,
                'description' => 'Weekly lubrication and die inspection on the frame press.'],
            // Later in the week, on the sub-assembly line.
            ['title' => 'Cassette bench calibration', 'line' => 'L-03', 'ws' => 'WS-SA-01', 'tool' => null,
                'type' => MaintenanceEvent::TYPE_INSPECTION, 'status' => MaintenanceEvent::STATUS_PENDING,
                'startsInHours' => 72, 'durationMinutes' => 120,
                'description' => 'Torque and leak-test rig calibration on the cassette bench.'],
            // The rest of the fortnight, so the planner's horizon keeps showing
            // maintenance alongside the scheduled orders rather than only this week.
            ['title' => 'Housing mould clean-down', 'line' => 'L-02', 'ws' => null, 'tool' => 'TL-MOULD-G4',
                'type' => MaintenanceEvent::TYPE_PLANNED, 'status' => MaintenanceEvent::STATUS_PENDING,
                'startsInHours' => 24 * 5 + 6, 'durationMinutes' => 150,
                'description' => 'Full clean-down and vent inspection on the G4 housing mould.'],
            ['title' => 'Pleat table pitch calibration', 'line' => 'L-01', 'ws' => 'WS-PA-01', 'tool' => null,
                'type' => MaintenanceEvent::TYPE_INSPECTION, 'status' => MaintenanceEvent::STATUS_PENDING,
                'startsInHours' => 24 * 8 + 6, 'durationMinutes' => 90,
                'description' => 'Verify pleat pitch against the reference gauge after the media change.'],
            ['title' => 'Adhesive nozzle replacement', 'line' => 'L-01', 'ws' => 'WS-AB-01', 'tool' => 'TL-NOZZLE-01',
                'type' => MaintenanceEvent::TYPE_PLANNED, 'status' => MaintenanceEvent::STATUS_PENDING,
                'startsInHours' => 24 * 11 + 6, 'durationMinutes' => 120,
                'description' => 'Scheduled nozzle set replacement in the bonding booth.'],
            ['title' => 'Frame press die inspection', 'line' => 'L-01', 'ws' => 'WS-FR-01', 'tool' => 'TL-DIE-01',
                'type' => MaintenanceEvent::TYPE_INSPECTION, 'status' => MaintenanceEvent::STATUS_PENDING,
                'startsInHours' => 24 * 13 + 6, 'durationMinutes' => 180,
                'description' => 'Wear check on the progressive die; measure the first-off frame.'],
        ];

        foreach ($eventDefs as $def) {
            $start = now()->addHours($def['startsInHours'])->setSecond(0);
            $completed = $def['status'] === MaintenanceEvent::STATUS_COMPLETED;
            $running = $def['status'] === MaintenanceEvent::STATUS_IN_PROGRESS;

            MaintenanceEvent::updateOrCreate(
                ['title' => $def['title']],
                [
                    'event_type' => $def['type'],
                    'status' => $def['status'],
                    'line_id' => $lines[$def['line']]->id,
                    'workstation_id' => $def['ws'] ? $ws[$def['ws']]->id : null,
                    'tool_id' => $def['tool'] ? $tools[$def['tool']]->id : null,
                    'assigned_to_id' => $supervisor->id,
                    'scheduled_at' => $start,
                    'scheduled_end_at' => $start->copy()->addMinutes($def['durationMinutes']),
                    'started_at' => ($completed || $running) ? $start : null,
                    'completed_at' => $completed ? $start->copy()->addMinutes($def['durationMinutes']) : null,
                    'description' => $def['description'],
                ]
            );
        }
    }

    /**
     * The items the two BOMs consume: purchased parts plus the one manufactured
     * material (the pleat pack) that links the levels together.
     *
     * @return array<string, Material>
     */
    private function seedMaterials(array $templates): array
    {
        // raw_material / semi_finished / packaging / auxiliary. Idempotent, and
        // the demo must not depend on that seeder having been run separately.
        $this->call(MaterialTypesSeeder::class);

        $typeIds = MaterialType::pluck('id', 'code');

        $defs = [
            // Purchased — HEPA-13 line.
            ['code' => 'MEDIA-H13',   'name' => 'HEPA-13 filter media',      'type' => 'raw_material', 'unit_of_measure' => 'm2',  'stock_quantity' => 4200,  'unit_price' => 6.40,  'supplier_name' => 'Filtrair Media BV'],
            ['code' => 'FRAME-AL-13', 'name' => 'Aluminium frame profile',   'type' => 'raw_material', 'unit_of_measure' => 'pcs', 'stock_quantity' => 1800,  'unit_price' => 11.20, 'supplier_name' => 'AluFab Sp. z o.o.'],
            ['code' => 'FRAME-SLIM',  'name' => 'Slim frame, pre-formed',    'type' => 'raw_material', 'unit_of_measure' => 'pcs', 'stock_quantity' => 900,   'unit_price' => 12.80, 'supplier_name' => 'AluFab Sp. z o.o.'],
            ['code' => 'GASKET-PU-13', 'name' => 'PU gasket seal',           'type' => 'raw_material', 'unit_of_measure' => 'm',   'stock_quantity' => 2600,  'unit_price' => 1.85,  'supplier_name' => 'SealTech GmbH'],
            ['code' => 'ADH-2K-A',    'name' => 'Two-part adhesive (A)',     'type' => 'auxiliary',    'unit_of_measure' => 'kg',  'stock_quantity' => 180,   'unit_price' => 24.50, 'supplier_name' => 'ChemBond'],
            ['code' => 'HOTMELT-01',  'name' => 'Hot-melt edge sealant',     'type' => 'auxiliary',    'unit_of_measure' => 'kg',  'stock_quantity' => 95,    'unit_price' => 18.90, 'supplier_name' => 'ChemBond'],

            // Housing line — moulded products (pre-filter, carbon).
            ['code' => 'RESIN-ABS',   'name' => 'ABS moulding granulate',    'type' => 'raw_material', 'unit_of_measure' => 'kg',  'stock_quantity' => 1450,  'unit_price' => 4.75,  'supplier_name' => 'PolyNord AB'],
            ['code' => 'MEDIA-G4',    'name' => 'G4 coarse filter media',    'type' => 'raw_material', 'unit_of_measure' => 'm2',  'stock_quantity' => 3100,  'unit_price' => 2.30,  'supplier_name' => 'Filtrair Media BV'],
            ['code' => 'CARBON-GRAN', 'name' => 'Activated carbon granulate', 'type' => 'raw_material', 'unit_of_measure' => 'kg', 'stock_quantity' => 860,   'unit_price' => 9.60,  'supplier_name' => 'CarbonWorks Ltd'],
            ['code' => 'MESH-RET',    'name' => 'Retaining mesh disc',       'type' => 'raw_material', 'unit_of_measure' => 'pcs', 'stock_quantity' => 2400,  'unit_price' => 0.95,  'supplier_name' => 'MeshPro'],
            ['code' => 'SEAL-EPDM',   'name' => 'EPDM seal strip',           'type' => 'raw_material', 'unit_of_measure' => 'm',   'stock_quantity' => 3400,  'unit_price' => 1.40,  'supplier_name' => 'SealTech GmbH'],

            // HVAC cassette.
            ['code' => 'FRAME-CASS',  'name' => 'HVAC cassette frame',       'type' => 'raw_material', 'unit_of_measure' => 'pcs', 'stock_quantity' => 420,   'unit_price' => 18.40, 'supplier_name' => 'AluFab Sp. z o.o.'],
            ['code' => 'MEDIA-F7',    'name' => 'F7 fine filter media',      'type' => 'raw_material', 'unit_of_measure' => 'm2',  'stock_quantity' => 1900,  'unit_price' => 4.10,  'supplier_name' => 'Filtrair Media BV'],

            // Packaging.
            ['code' => 'CARTON-10',   'name' => 'Carton, 10 filters',        'type' => 'packaging',    'unit_of_measure' => 'pcs', 'stock_quantity' => 640,   'unit_price' => 3.10,  'supplier_name' => 'PackLine'],
            ['code' => 'CARTON-CASS', 'name' => 'Carton, 1 cassette',        'type' => 'packaging',    'unit_of_measure' => 'pcs', 'stock_quantity' => 380,   'unit_price' => 2.40,  'supplier_name' => 'PackLine'],
        ];

        $materials = [];
        foreach ($defs as $def) {
            $materials[$def['code']] = Material::updateOrCreate(
                ['code' => $def['code']],
                [
                    'name' => $def['name'],
                    'material_type_id' => $typeIds[$def['type']] ?? null,
                    'unit_of_measure' => $def['unit_of_measure'],
                    'tracking_type' => 'batch',
                    'is_manufactured' => false,
                    'stock_quantity' => $def['stock_quantity'],
                    'unit_price' => $def['unit_price'],
                    'supplier_name' => $def['supplier_name'],
                ]
            );
        }

        // The sub-assemblies. `is_manufactured` + the producing template is what
        // lets a BOM line for one be exploded into the level below, so each of
        // these is a place the explosion can keep descending.
        //
        // Stock is deliberately uneven: MESHCAGE is held short so a carbon
        // order has to be netted two levels down before the shortage shows up,
        // which is the case the flat explosion used to miss.
        $subAssemblies = [
            ['code' => 'PLEATPACK13', 'name' => 'Pleat pack HEPA-13',     'stock_quantity' => 260],
            ['code' => 'MOULDHOUS',   'name' => 'Moulded housing shell',  'stock_quantity' => 540],
            ['code' => 'MESHCAGE',    'name' => 'Retaining mesh cage',    'stock_quantity' => 40],
            ['code' => 'CARBONCART',  'name' => 'Carbon cartridge',       'stock_quantity' => 95],
            ['code' => 'MEDIAPACK7',  'name' => 'F7 media pack',          'stock_quantity' => 180],
        ];

        foreach ($subAssemblies as $def) {
            $template = $templates[$def['code']] ?? null;
            if (! $template) {
                continue;
            }

            $materials[$def['code']] = Material::updateOrCreate(
                ['code' => $def['code']],
                [
                    'name' => $def['name'],
                    'material_type_id' => $typeIds['semi_finished'] ?? null,
                    'unit_of_measure' => 'pcs',
                    'tracking_type' => 'batch',
                    'is_manufactured' => true,
                    'producing_process_template_id' => $template->id,
                    'stock_quantity' => $def['stock_quantity'],
                ]
            );
        }

        return $materials;
    }

    /**
     * Received material lots — the incoming side of traceability.
     *
     * One per material that is worth tracing, covering every lot status the app
     * knows: stock on hand (released), a delivery still waiting on inbound QC
     * (quarantine), one that failed it (rejected), one used up (consumed) and a
     * time-expired adhesive next to its live replacement (expired / released).
     * Chemicals carry manufacturing and expiry dates; the rest do not.
     *
     * @param  array<string, Material>  $materials
     */
    private function seedMaterialLots(array $materials): void
    {
        // [lot number, material code, status, received, available, days ago, shelf life in days or null]
        $defs = [
            ['LOT-MED-26031', 'MEDIA-H13',   MaterialLot::STATUS_RELEASED,   1200, 845,  12, null],
            // Landed this morning, inbound inspection not done yet.
            ['LOT-MED-26034', 'MEDIA-H13',   MaterialLot::STATUS_QUARANTINE, 900,  900,  0,  null],
            // Failed the inbound check — kept for the supplier claim.
            ['LOT-MED-25488', 'MEDIA-H13',   MaterialLot::STATUS_REJECTED,   600,  0,    46, null],
            ['LOT-FRM-26030', 'FRAME-AL-13', MaterialLot::STATUS_RELEASED,   900,  612,  15, null],
            ['LOT-SLM-26031', 'FRAME-SLIM',  MaterialLot::STATUS_RELEASED,   400,  355,  11, null],
            ['LOT-RES-26029', 'RESIN-ABS',   MaterialLot::STATUS_RELEASED,   750,  410,  18, null],
            ['LOT-CRB-26030', 'CARBON-GRAN', MaterialLot::STATUS_RELEASED,   500,  268,  16, null],
            ['LOT-G4-26028',  'MEDIA-G4',    MaterialLot::STATUS_RELEASED,   1400, 980,  21, null],
            ['LOT-F7-26031',  'MEDIA-F7',    MaterialLot::STATUS_RELEASED,   800,  745,  10, null],
            ['LOT-SEL-26027', 'SEAL-EPDM',   MaterialLot::STATUS_RELEASED,   1500, 1130, 24, null],
            // Drawn down to nothing — what a closed-out lot looks like in tracing.
            ['LOT-MSH-26025', 'MESH-RET',    MaterialLot::STATUS_CONSUMED,   1200, 0,    30, null],
            // Past its shelf life; the replacement below is the live one.
            ['LOT-ADH-25512', 'ADH-2K-A',    MaterialLot::STATUS_EXPIRED,    60,   18,   200, 180],
            ['LOT-ADH-26032', 'ADH-2K-A',    MaterialLot::STATUS_RELEASED,   80,   62,   9,   180],
            ['LOT-HM-26030',  'HOTMELT-01',  MaterialLot::STATUS_RELEASED,   45,   31,   14,  365],
            ['LOT-CAS-26029', 'FRAME-CASS',  MaterialLot::STATUS_RELEASED,   220,  168,  19,  null],
        ];

        foreach ($defs as [$lotNumber, $code, $status, $received, $available, $daysAgo, $shelfLife]) {
            $material = $materials[$code] ?? null;
            if (! $material) {
                continue;
            }

            $receivedAt = now()->subDays($daysAgo);

            MaterialLot::updateOrCreate(
                ['lot_number' => $lotNumber],
                [
                    'material_id' => $material->id,
                    'quantity_received' => $received,
                    'quantity_available' => $available,
                    'unit_of_measure' => $material->unit_of_measure,
                    'received_at' => $receivedAt,
                    'manufacturing_date' => $shelfLife ? $receivedAt->copy()->subDays(14)->toDateString() : null,
                    'expiry_date' => $shelfLife ? $receivedAt->copy()->addDays($shelfLife)->toDateString() : null,
                    'status' => $status,
                    'supplier_lot_no' => str_replace('LOT-', 'S/', $lotNumber),
                    'supplier_reference' => $material->supplier_name,
                ]
            );
        }
    }

    /**
     * A bill of materials for every demo routing, not just the HEPA-13.
     *
     * The HEPA-13 assembly is the two-level one: it consumes the pleat pack,
     * whose own template consumes media and sealant, so exploding the top level
     * reaches the raw media. The rest are single level.
     *
     * Lines are pinned to the step that actually consumes them, so the operator's
     * kit list matches the routing.
     *
     * @param  array<string, ProcessTemplate>  $templates  keyed by product code
     * @param  array<string, Material>  $materials
     */
    private function seedBom(array $templates, array $materials): void
    {
        // [product code => [[step number, material code, qty per unit, scrap %, consumed at], …]]
        $defs = [
            'HEPA13_STD' => [
                [2, 'FRAME-AL-13',  1,    0, 'start'],
                // The sub-assembly line — this is what explodes a level down.
                [3, 'PLEATPACK13',  1,    2, 'start'],
                [4, 'GASKET-PU-13', 1.6,  3, 'during'],
                [4, 'ADH-2K-A',     0.08, 5, 'during'],
                // 10 filters to a carton.
                [6, 'CARTON-10',    0.1,  0, 'end'],
            ],
            // Its routing pleats media directly rather than taking a finished
            // pack, so the BOM names the media, not the sub-assembly.
            'HEPA13_SLIM' => [
                [1, 'FRAME-SLIM',   1,    0, 'start'],
                [2, 'MEDIA-H13',    1.9,  5, 'start'],
                [3, 'GASKET-PU-13', 1.4,  3, 'during'],
                [3, 'ADH-2K-A',     0.07, 5, 'during'],
                [5, 'CARTON-10',    0.1,  0, 'end'],
            ],
            'PREFILTER' => [
                // Takes the moulded shell rather than the granulate: the
                // moulding is its own job, and the shell is shared with Carbon.
                [1, 'MOULDHOUS',    1,    1, 'start'],
                [2, 'MEDIA-G4',     0.8,  4, 'start'],
                // Coarse filters ship 20 to a box.
                [4, 'CARTON-10',    0.05, 0, 'end'],
            ],
            'CARBON' => [
                [1, 'MOULDHOUS',    1,    1, 'start'],
                // Two manufactured levels sit under this one line: the cartridge
                // is filled from a mesh cage, which is itself assembled.
                [2, 'CARBONCART',   1,    2, 'during'],
                [5, 'CARTON-10',    0.1,  0, 'end'],
            ],
            'HVAC' => [
                [1, 'FRAME-CASS',   1,    0, 'start'],
                [2, 'MEDIA-G4',     1.1,  4, 'start'],
                [3, 'MEDIAPACK7',   1,    2, 'during'],
                [4, 'SEAL-EPDM',    2.2,  2, 'during'],
                [6, 'CARTON-CASS',  1,    0, 'end'],
            ],
            // The level below the HEPA-13 assembly.
            'PLEATPACK13' => [
                [1, 'MEDIA-H13',    2.4,  5, 'start'],
                [2, 'HOTMELT-01',   0.03, 2, 'during'],
            ],
            // Shared between the pre-filter and the carbon product, so netting
            // has to add both parents' demand together before it decides
            // whether the shell needs making.
            'MOULDHOUS' => [
                [1, 'RESIN-ABS',    0.62, 3, 'start'],
            ],
            // Bottom manufactured level of the carbon tree.
            'MESHCAGE' => [
                [1, 'MESH-RET',     1,    1, 'start'],
                [2, 'SEAL-EPDM',    0.9,  2, 'during'],
            ],
            'CARBONCART' => [
                [1, 'MESHCAGE',     1,    1, 'start'],
                [2, 'CARBON-GRAN',  1.2,  2, 'during'],
            ],
            'MEDIAPACK7' => [
                [1, 'MEDIA-F7',     1.6,  5, 'start'],
                [2, 'HOTMELT-01',   0.02, 2, 'during'],
            ],
        ];

        foreach ($defs as $productCode => $lines) {
            $template = $templates[$productCode] ?? null;
            if (! $template) {
                continue;
            }

            $sortOrder = 0;

            foreach ($lines as [$stepNumber, $code, $qty, $scrap, $consumedAt]) {
                $material = $materials[$code] ?? null;
                if (! $material) {
                    continue;
                }

                $stepId = DB::table('template_steps')
                    ->where('process_template_id', $template->id)
                    ->where('step_number', $stepNumber)
                    ->value('id');

                BomItem::updateOrCreate(
                    ['process_template_id' => $template->id, 'material_id' => $material->id],
                    [
                        'template_step_id' => $stepId,
                        'quantity_per_unit' => $qty,
                        'scrap_percentage' => $scrap,
                        'consumed_at' => $consumedAt,
                        'sort_order' => ++$sortOrder,
                    ]
                );
            }
        }
    }

    /**
     * Orders that run on more than one line.
     *
     * The plant is laid out as a flow — sub-assembly feeds the filter line,
     * housings feed it too, and everything ends at pack & ship — so an order
     * genuinely occupies more than one line before it is finished. The planner
     * draws this as a badge plus a connector down to the extra segment, and
     * there was no example data behind that display at all.
     *
     * Segments are coarse (day + shift); the minute-level plan stays with the
     * primary placement.
     *
     * @param  array<string, Line>  $lines
     * @param  array<string, WorkOrder>  $workOrders
     */
    private function seedMultiLinePlacements(array $lines, array $workOrders): void
    {
        // from line => [[to line, shifts later], …], following the flow.
        $handoffs = [
            'L-03' => [['L-01', 1]],
            'L-02' => [['L-01', 1], ['L-04', 2]],
            'L-01' => [['L-04', 2]],
        ];

        $lineById = [];
        foreach ($lines as $code => $line) {
            $lineById[$line->id] = $code;
        }

        WorkOrderPlacement::whereIn('work_order_id', collect($workOrders)->pluck('id'))->delete();

        $n = 0;

        foreach ($workOrders as $order) {
            // These orders are planned at shift level, so due_date is what they
            // carry — planned_start_at is only set once someone pins a block to
            // the minute in the planner.
            $anchor = $order->planned_start_at ?? $order->due_date;
            if (! $anchor) {
                continue;
            }

            $fromCode = $lineById[$order->line_id] ?? null;
            if (! $fromCode || ! isset($handoffs[$fromCode])) {
                continue;
            }

            // Every third, so the board shows the case without every block
            // sprouting a connector.
            if ($n++ % 3 !== 0) {
                continue;
            }

            $start = $anchor->copy();

            foreach ($handoffs[$fromCode] as [$toCode, $shiftsLater]) {
                $target = $lines[$toCode] ?? null;
                if (! $target || $target->id === $order->line_id) {
                    continue;
                }

                // shift_number is 1..3 within a day; roll into the next day
                // rather than emitting a fourth shift no column matches.
                $shift = (int) ceil($start->hour / 8) + $shiftsLater;
                $dayOffset = intdiv($shift - 1, 3);
                $shift = (($shift - 1) % 3) + 1;

                WorkOrderPlacement::updateOrCreate(
                    ['work_order_id' => $order->id, 'line_id' => $target->id],
                    [
                        'due_date' => $start->copy()->addDays($dayOffset)->startOfDay(),
                        'shift_number' => $shift,
                    ]
                );
            }
        }
    }

    /**
     * Issues as the shop floor actually files them: an operator picks a type on
     * the work order and writes a line about what stopped them. One per status so
     * the queue, the supervisor board and the history all have something to show.
     *
     * Keyed on (work order, title) so a re-run updates in place — the seeder is
     * upsert-safe like the rest of this file.
     *
     * @param  array<string, WorkOrder>  $workOrders
     * @param  array<string, User>  $users
     */
    private function seedIssues(array $workOrders, array $users): void
    {
        $types = IssueType::pluck('id', 'code');

        // Without the issue-type reference data there is nothing to attach to;
        // IssueTypesSeeder owns those rows.
        if ($types->isEmpty()) {
            return;
        }

        $supervisor = $users['supervisor'];
        $mk = $users['operator-mk'];
        $an = $users['operator-an'];

        $defs = [
            // Blocking, still waiting on someone — what the supervisor board is for.
            [
                'wo' => 'WO-186-001',
                'type' => 'MATERIAL_DEFECT',
                'title' => 'Pleat pack delaminating on infeed',
                'description' => 'Every third pack from the current pallet separates at the glue line. Set the pallet aside and stopped feeding it.',
                'status' => Issue::STATUS_OPEN,
                'reported_by' => $mk,
                'reported_ago' => 35,
            ],
            // Non-blocking question — production keeps running.
            [
                'wo' => 'WO-186-002',
                'type' => 'OPERATOR_ASSISTANCE',
                'title' => 'Need a second pair of hands for the frame change',
                'description' => 'Slim frames need two people to load safely. Asking for support before starting the run.',
                'status' => Issue::STATUS_OPEN,
                'reported_by' => $an,
                'reported_ago' => 20,
            ],
            // Picked up by the supervisor — this is why WO-186-004 sits paused.
            [
                'wo' => 'WO-186-004',
                'type' => 'TOOL_FAILURE',
                'title' => 'Carbon press holding pressure only to 4 bar',
                'description' => 'Press will not reach the 6 bar set point. Line paused until maintenance looks at it.',
                'status' => Issue::STATUS_ACKNOWLEDGED,
                'reported_by' => $an,
                'reported_ago' => 95,
                'acknowledged_ago' => 70,
                'assigned_to' => $supervisor,
            ],
            // Fixed, kept open one more shift for verification.
            [
                'wo' => 'WO-186-001',
                'type' => 'MEASUREMENT_ERROR',
                'title' => 'Depth gauge reading 0.4 mm high',
                'description' => 'Gauge disagreed with the reference block on the first check of the shift.',
                'status' => Issue::STATUS_RESOLVED,
                'reported_by' => $mk,
                'reported_ago' => 300,
                'acknowledged_ago' => 280,
                'resolved_ago' => 240,
                'assigned_to' => $supervisor,
                'resolution_notes' => 'Gauge re-zeroed against the reference block and re-checked on ten parts.',
            ],
            // Closed out on a finished order — the history view needs one.
            [
                'wo' => 'WO-185-088',
                'type' => 'QUALITY_ISSUE',
                'title' => 'Two filters with visible frame scratches',
                'description' => 'Scratches on the short edge, most likely from the transport rack.',
                'status' => Issue::STATUS_CLOSED,
                'reported_by' => $mk,
                'reported_ago' => 1_500,
                'acknowledged_ago' => 1_480,
                'resolved_ago' => 1_400,
                'closed_ago' => 1_380,
                'assigned_to' => $supervisor,
                'resolution_notes' => 'Both units reworked and passed the visual check. Rack padding replaced.',
            ],
        ];

        foreach ($defs as $def) {
            $workOrder = $workOrders[$def['wo']] ?? null;
            $typeId = $types[$def['type']] ?? null;

            if (! $workOrder || ! $typeId) {
                continue;
            }

            Issue::updateOrCreate(
                ['work_order_id' => $workOrder->id, 'title' => $def['title']],
                [
                    'issue_type_id' => $typeId,
                    'description' => $def['description'],
                    'status' => $def['status'],
                    'source' => Issue::SOURCE_IN_PROCESS,
                    'reported_by_id' => $def['reported_by']->id,
                    'assigned_to_id' => isset($def['assigned_to']) ? $def['assigned_to']->id : null,
                    'reported_at' => now()->subMinutes($def['reported_ago']),
                    'acknowledged_at' => isset($def['acknowledged_ago']) ? now()->subMinutes($def['acknowledged_ago']) : null,
                    'resolved_at' => isset($def['resolved_ago']) ? now()->subMinutes($def['resolved_ago']) : null,
                    'closed_at' => isset($def['closed_ago']) ? now()->subMinutes($def['closed_ago']) : null,
                    'resolution_notes' => $def['resolution_notes'] ?? null,
                ]
            );
        }
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
            // Semi-finished: produced on its own template, then consumed by the
            // HEPA-13 assembly. It needs a product type because that is what a
            // process template is written against.
            ['code' => 'PLEATPACK13', 'name' => 'Pleat pack HEPA-13', 'description' => 'Folded and edge-sealed HEPA-13 media pack, ready to frame', 'unit_of_measure' => 'pcs'],
            // The rest of the sub-assembly tree. MESHCAGE feeds CARBONCART,
            // which feeds the Carbon X2 — three manufactured levels above the
            // raw material, so exploding a carbon order has somewhere to go.
            ['code' => 'MOULDHOUS',   'name' => 'Moulded housing shell', 'description' => 'Injection-moulded ABS shell, shared by the pre-filter and carbon products', 'unit_of_measure' => 'pcs'],
            ['code' => 'MESHCAGE',    'name' => 'Retaining mesh cage',   'description' => 'Mesh disc and EPDM strip assembled into a cage that holds the carbon bed', 'unit_of_measure' => 'pcs'],
            ['code' => 'CARBONCART',  'name' => 'Carbon cartridge',      'description' => 'Mesh cage filled and compacted with activated carbon, ready to drop into a shell', 'unit_of_measure' => 'pcs'],
            ['code' => 'MEDIAPACK7',  'name' => 'F7 media pack',         'description' => 'Pleated and edge-sealed F7 media stage for the HVAC cassette', 'unit_of_measure' => 'pcs'],
        ];

        $result = [];
        foreach ($defs as $def) {
            $pt = ProductType::updateOrCreate(['code' => $def['code']], array_merge($def, ['is_active' => true]));
            $result[$def['code']] = $pt;
        }

        return $result;
    }

    /**
     * A routing for every demo product, not just the HEPA-13. Without one a
     * product type shows "0 templates", its work orders carry no steps for the
     * operator to work through, and there is nowhere to hang a BOM.
     *
     * @param  array<string, ProductType>  $pt
     * @param  array<string, Workstation>  $ws
     * @return array<string, ProcessTemplate> keyed by product code
     */
    private function seedProcessTemplates(array $pt, array $ws): array
    {
        $defs = [
            'HEPA13_STD' => ['HEPA-13 Standard — assembly v1', [
                [1, 'Material kit pickup',  'Pull the BOM kit (pleat sheet, frame blank, adhesive, gasket) from staging.', 5, 'WS-MK-01'],
                [2, 'Frame stamping',       'Stamp the aluminium frame blank on the housing press; check perpendicularity.', 4, 'WS-FR-01'],
                [3, 'Pleat assembly',       'Pleat the filter media and slot into frame. Maintain pitch ±0.5 mm.', 8, 'WS-PA-01'],
                [4, 'Adhesive bonding',     'Apply two-part adhesive bead around perimeter; cure 6 min at 60 °C.', 10, 'WS-AB-01'],
                [5, 'QC visual inspection', 'Inspect for pleat collapse, adhesive squeeze-out, gasket fit. Photograph defects.', 3, 'WS-QC-01'],
                [6, 'Packaging (10/box)',   'Pack 10 filters per carton with desiccant; apply lot label.', 4, 'WS-PK-01'],
                [7, 'Pallet handoff',       'Stack cartons on pallet, wrap, hand off to dispatch with batch sheet.', 3, 'WS-PH-01'],
            ]],
            // Shares the HEPA line but skips the separate stamping pass — the slim
            // frame arrives pre-formed.
            'HEPA13_SLIM' => ['HEPA-13 Slim — assembly v1', [
                [1, 'Material kit pickup',  'Pull the slim-frame kit from staging; check the frame depth marking.', 5, 'WS-MK-01'],
                [2, 'Pleat assembly',       'Pleat to the slim pitch and seat into the pre-formed frame.', 9, 'WS-PA-01'],
                [3, 'Adhesive bonding',     'Bead the perimeter and cure. Slim frames need the lower 55 °C profile.', 10, 'WS-AB-01'],
                [4, 'QC visual inspection', 'Check pleat pitch, seal continuity and that the depth is within tolerance.', 3, 'WS-QC-01'],
                [5, 'Packaging (10/box)',   'Pack 10 per carton; slim cartons take the same lot label.', 4, 'WS-PK-01'],
            ]],
            // Coarse filter on the housing line — moulded, not pleated.
            'PREFILTER' => ['Pre-filter G4 — production v1', [
                [1, 'Housing mould',        'Mould the G4 housing frame; check for short shots before releasing.', 6, 'WS-HM-01'],
                [2, 'Media insert',         'Cut the G4 media to size and press into the housing.', 5, 'WS-HM-01'],
                [3, 'Housing QC',           'Verify the media sits flush and the frame has no flash.', 3, 'WS-HQC-01'],
                [4, 'Pack & label',         'Bag, box and label for stock replenishment.', 4, 'WS-PK-04'],
            ]],
            'CARBON' => ['Carbon X2 — production v1', [
                [1, 'Housing mould',        'Mould the Carbon X2 housing; verify the fill port is clear.', 6, 'WS-HM-01'],
                [2, 'Carbon fill',          'Fill with activated carbon to the weight target, then settle on the vibrator.', 8, 'WS-HM-01'],
                [3, 'Seal & press',         'Press the retaining mesh and seal the fill port.', 5, 'WS-HM-01'],
                [4, 'Housing QC',           'Weigh-check the fill and confirm no carbon migration past the mesh.', 4, 'WS-HQC-01'],
                [5, 'Pack & label',         'Box, label and stage for dispatch.', 4, 'WS-PK-04'],
            ]],
            'HVAC' => ['HVAC cassette — assembly v1', [
                [1, 'Cassette frame prep',  'Square up the cassette frame and fit the corner brackets.', 6, 'WS-SA-01'],
                [2, 'Stage 1 media',        'Load the coarse pre-filter stage into the first channel.', 5, 'WS-SA-01'],
                [3, 'Stage 2 media',        'Load the fine media stage; check the gasket seats on both sides.', 6, 'WS-SA-02'],
                [4, 'Cassette closing',     'Close the cassette and secure the retaining clips.', 4, 'WS-SA-02'],
                [5, 'Leak check',           'Pressure-test the cassette seal; log the reading on the batch sheet.', 5, 'WS-SA-02'],
                [6, 'Pack & label',         'Sleeve, box and label the cassette.', 4, 'WS-PK-04'],
            ]],
            // The sub-assembly's own routing — what makes PLEATPACK13 explodable.
            'PLEATPACK13' => ['Pleat pack HEPA-13 — production v1', [
                [1, 'Media pleating',       'Feed the media roll and fold to the HEPA-13 pitch. Check pleat height on the first five packs.', 6, 'WS-PA-01'],
                [2, 'Edge sealing',         'Run a hot-melt bead down both open edges and press until set.', 4, 'WS-AB-01'],
            ]],
            'MOULDHOUS' => ['Moulded housing shell — production v1', [
                [1, 'Moulding',             'Dry the granulate, run the shot and let the shell cool in the fixture.', 5, 'WS-FR-01'],
                [2, 'Deflash & inspect',    'Trim the parting line and check the shell for sink marks before it goes to stock.', 3, 'WS-AB-01'],
            ]],
            'MESHCAGE' => ['Retaining mesh cage — production v1', [
                [1, 'Cage forming',         'Roll the mesh disc into the cage former and spot-weld the seam.', 4, 'WS-SA-01'],
                [2, 'Seal fitting',         'Fit the EPDM strip around the rim; check it seats evenly all the way round.', 3, 'WS-SA-01'],
            ]],
            'CARBONCART' => ['Carbon cartridge — production v1', [
                [1, 'Carbon filling',       'Fill the cage to the fill line and vibrate to settle the bed.', 6, 'WS-SA-02'],
                [2, 'Compaction & weigh',   'Compact the bed and weigh the cartridge; reject anything outside the tolerance band.', 4, 'WS-SA-02'],
            ]],
            'MEDIAPACK7' => ['F7 media pack — production v1', [
                [1, 'Media pleating',       'Fold the F7 media to the cassette pitch.', 5, 'WS-PA-01'],
                [2, 'Edge sealing',         'Seal both open edges and trim the pack to cassette width.', 3, 'WS-AB-01'],
            ]],
        ];

        $templates = [];

        foreach ($defs as $productCode => [$name, $steps]) {
            $productType = $pt[$productCode] ?? null;
            if (! $productType) {
                continue;
            }

            $template = ProcessTemplate::updateOrCreate(
                ['product_type_id' => $productType->id, 'version' => 1],
                ['name' => $name, 'is_active' => true]
            );

            foreach ($steps as [$stepNo, $stepName, $instruction, $duration, $wsCode]) {
                // Match live rows only. template_steps is soft-deletable and
                // updateOrInsert() runs without the model's scope, so leaving
                // deleted_at out would let a re-run resurrect (and overwrite) a
                // step the user had deleted instead of inserting a fresh one.
                DB::table('template_steps')->updateOrInsert(
                    [
                        'process_template_id' => $template->id,
                        'step_number' => $stepNo,
                        'deleted_at' => null,
                    ],
                    [
                        'name' => $stepName,
                        'instruction' => $instruction,
                        'estimated_duration_minutes' => $duration,
                        'workstation_id' => $ws[$wsCode]?->id,
                        'created_at' => now(),
                    ]
                );
            }

            $templates[$productCode] = $template;
        }

        return $templates;
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

    /**
     * @param  array<string, ProcessTemplate>  $templates  keyed by product code
     */
    private function seedWorkOrders(array $lines, array $pt, array $templates): array
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

        // A fortnight of scheduled work ahead of today. Without it the planner's
        // horizon empties out after tomorrow and the board looks abandoned two
        // days in — the orders above only cover today.
        $horizon = [
            [2,  'WO-186-006', 'L-01', 'HEPA13_STD',  200, 3, 'Filtex — stock replenishment.'],
            [3,  'WO-186-007', 'L-02', 'CARBON',      150, 2, 'Carbon X2 for the Q4 stock build.'],
            [4,  'WO-186-008', 'L-03', 'HVAC',        80,  4, 'HVAC cassettes — Nordwind contract.'],
            [5,  'WO-186-009', 'L-01', 'HEPA13_SLIM', 140, 2, 'Slim retrofit, second batch.'],
            [6,  'WO-186-010', 'L-02', 'PREFILTER',   500, 1, 'G4 pre-filters — bulk stock.'],
            [8,  'WO-186-011', 'L-01', 'HEPA13_STD',  260, 4, 'Standard HEPA-13 — export pallet.'],
            [9,  'WO-186-012', 'L-03', 'HVAC',        60,  3, 'HVAC cassette top-up.'],
            [10, 'WO-186-013', 'L-02', 'CARBON',      180, 2, 'Carbon X2 — service parts.'],
            [11, 'WO-186-014', 'L-01', 'HEPA13_SLIM', 110, 3, 'Slim filters, retrofit phase 2.'],
            [13, 'WO-186-015', 'L-01', 'HEPA13_STD',  300, 5, 'Standard HEPA-13 — Filtex quarterly.'],
        ];

        foreach ($horizon as [$inDays, $orderNo, $line, $product, $qty, $priority, $description]) {
            $defs[] = [
                'order_no' => $orderNo,
                'line' => $line,
                'product' => $product,
                'planned_qty' => $qty,
                'produced_qty' => 0,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => $priority,
                'due_date' => $today->copy()->addDays($inDays)->setTime(14, 0),
                'description' => $description,
            ];
        }

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
            // Snapshot the product's own routing the way the app does — a
            // hand-rolled header carries no steps and no BOM, which leaves the
            // operator with nothing to work through and no kit list.
            $template = $templates[$def['product']] ?? null;
            if ($template) {
                $payload['process_snapshot'] = $template
                    ->fresh(['steps.workstation', 'bomItems.material'])
                    ->toSnapshot();
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
                'lot_number' => 'LOT-2026-'.str_pad((string) $wo->id, 4, '0', STR_PAD_LEFT),
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
