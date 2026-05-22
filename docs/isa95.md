# ISA-95 Compatibility — OpenMES

OpenMES implements the [ANSI/ISA-95 / IEC 62264](https://www.isa.org/standards-and-publications/isa-standards/isa-standards-committees/isa95)
reference model for Manufacturing Operations Management (MOM) at automation **Level 3** (between SCADA/PLC at Level 2 and ERP at Level 4).

> **Important:** ISA-95 is a reference standard — there is no formal certification body for software products. This document is a **self-declared mapping** of OpenMES capabilities onto the standard's models and activity categories, with explicit gaps listed below. The same approach is used by SAP, Siemens, GE, Rockwell, and other Level 3 MES vendors.

## Hierarchies

### Equipment Hierarchy (ISA-95 §5.3)

| ISA-95 level | OpenMES model | Migration / class |
|---|---|---|
| Enterprise | `Company` | `companies` |
| **Site** | `Site` | `sites`, since v0.12 |
| **Area** | `Area` | `areas`, since v0.12 |
| Work Center | `Line` | `lines.area_id` FK |
| Work Unit | `Workstation` | `workstations` |

A `Division` exists in the data model as a workforce grouping (Personnel Class precursor), not part of the equipment hierarchy.

### Personnel Hierarchy (ISA-95 §5.5)

| ISA-95 concept | OpenMES model |
|---|---|
| Person | `User`, `Worker` |
| Personnel Class | `PersonnelClass` (with `required_skill_ids`, `default_required_cert_level`) |
| Personnel Capability | `worker_skills` pivot with `cert_level` (trainee / operator / expert / trainer), `certified_from`, `certified_until` |
| Qualification Test | `Inspection` (when used internally for certifications — out-of-the-box not wired) |

Run `php artisan certs:check-expiry --days=30` to list certifications expiring soon.

### Material Hierarchy (ISA-95 §5.4)

| ISA-95 concept | OpenMES model |
|---|---|
| Material Class | `MaterialType` |
| Material Definition | `Material` |
| **Material Lot** | `MaterialLot` (qty received/available, expiry, supplier, status: received/quarantine/released/consumed/expired/rejected) |
| **Material Sublot** | `MaterialSublot` |
| Material Movement | not yet — see Gaps |

Forward/backward genealogy: query `material_lots.batch_step_lot_consumption` join — see API `/api/v1/material-lots/{lot}/genealogy/{forward,backward}`.

## Manufacturing Operations Management (Part 3 — MOM)

ISA-95 Part 3 defines four MOM categories, each with eight standard activities. OpenMES coverage:

### Production Operations — ~85%

| Activity | OpenMES |
|---|---|
| Definition Management | `ProcessTemplate`, `TemplateStep`, `ProductType`, `BomItem`, **`ProcessSegment`** (reusable segments since v0.12) |
| Resource Management | `User`, `Worker`, `Workstation`, `Line`, `Skill`, `PersonnelClass` |
| Detailed Scheduling | `SchedulePlanner` (weekly / daily / hourly with minute granularity, since v0.12) |
| Dispatching | `WorkOrder` PENDING → ACCEPTED → IN_PROGRESS → DONE |
| Execution Management | `Batch`, `BatchStep`, `ProcessConfirmation`, sequential step gating |
| Data Collection | `WorkOrderShiftEntry`, `ProductionAnomaly` |
| Tracking | `process_snapshot` (immutable per WO), `MaterialLot` consumption via `BatchStepLotConsumption` |
| Performance Analysis | OEE dashboard, gauges, PDF reports |

### Maintenance Operations — ~80%

| Activity | OpenMES |
|---|---|
| Definition Management | `MaintenanceSchedule` (recurrence templates) |
| Resource Management | `assigned_to` on `MaintenanceEvent` |
| Detailed Scheduling | recurrence: daily / weekly / monthly / quarterly / annually / by_hours |
| Dispatching | `MaintenanceEvent` pending/in_progress/completed/cancelled |
| Execution Management | start/complete/cancel transitions |
| Data Collection | resolution_notes, actual_cost, duration |
| Tracking | per `tool` / `line` / `workstation` |
| Performance Analysis | basic (no MTBF/MTTR yet — see Gaps) |

### Quality Operations — ~75%

| Activity | OpenMES |
|---|---|
| Definition Management | `InspectionPlan`, `QualityCheckTemplate` |
| Resource Management | inspector assignment on `Inspection` |
| Detailed Scheduling | ad-hoc + inbound trigger |
| Dispatching | `Inspection` |
| Execution Management | pass/fail + `InspectionResult` |
| **Disposition** (ISA-95 §6.5) | accept / accept_with_deviation / rework / quarantine / scrap / reject / return_to_supplier — synchronized to `MaterialLot.status` (since v0.12) |
| Data Collection | `QualityCheckSample` |
| Tracking | `inspections.source` (inbound_inspection / in_process / customer_complaint), link to `Material`, `WorkOrder` |
| Performance Analysis | pass rate widget |

### Inventory Operations — ~45%

| Activity | OpenMES |
|---|---|
| Definition Management | `Material`, `MaterialType` |
| Resource Management | `MaterialSource` (supplier) |
| Detailed Scheduling | not yet (no replenishment rules) |
| Dispatching | `MaterialAllocation` at batch start |
| Execution Management | `MaterialLot` + `MaterialSublot` (since v0.12) |
| Data Collection | EAN scanning (Packaging module), lot consumption recording |
| Tracking | lot-level quantity tracking, forward/backward genealogy |
| Performance Analysis | no inventory turns / stockout reports yet |

## API & Integration (Part 5 — B2MML)

ISA-95 Part 5 defines XML transaction schemas (B2MML). OpenMES exposes REST API equivalents under `/api/v1/`:

| B2MML Transaction | OpenMES REST endpoint |
|---|---|
| Personnel Information | `GET /api/v1/users`, `/api/v1/workers`, `/api/v1/personnel-classes` |
| Equipment Information | `/api/v1/sites`, `/api/v1/areas`, `/api/v1/lines`, `/api/v1/workstations` |
| Material Information | `/api/v1/materials`, `/api/v1/material-lots` (incl. genealogy endpoints) |
| Process Segment | `/api/v1/process-segments` |
| Production Schedule | `/api/v1/work-orders` (POST creates) |
| Production Performance | `/api/v1/oee`, `/api/v1/work-orders/{wo}` |
| Operations Definition | via `/api/v1/process-templates` |

Full XML/B2MML message support is not implemented — JSON REST is the canonical wire format.

## Gaps and Roadmap

Transparent list of what is **not** yet implemented:

### Inventory
- Material movement / transfer log (between locations)
- Storage locations / bins
- Replenishment rules and safety stock alerts
- Inventory turns, ABC classification, stockout reports

### Maintenance
- MTBF / MTTR computed metrics per equipment
- Spare parts catalog tied to maintenance events
- `by_hours` recurrence frequency coupled to actual machine runtime (currently wall-clock)

### Quality
- SPC control charts (X-bar, R, p, c)
- Cpk / Ppk capability indices
- Non-conformance / CAPA workflow (tracked in [issue #17](https://github.com/Mes-Open/openMes/issues/17))
- Sample size calculation rules (AQL, MIL-STD-105)

### Production
- Energy consumption tracking per equipment (ISO 50001 alignment)
- Operations Capability — max throughput per Line per ProductType

### Integration
- B2MML XML schema messages (REST API is the supported format)
- ERP webhook subscriptions for state changes (tracked in [issue #20](https://github.com/Mes-Open/openMes/issues/20))

## References

- [ANSI/ISA-95.00.01-2010 / IEC 62264-1](https://www.isa.org/products/ansi-isa-95-00-01-2010-iec-62264-1-mod-enter)
- [B2MML XML schemas](https://www.mesa.org/topics-resources/b2mml/) — MESA International
- [Manufacturing Enterprise Solutions Association (MESA) ISA-95 working group](https://www.mesa.org/)
