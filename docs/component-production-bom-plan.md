# Component production from imported BOMs

Analysis date: 2026-09-07. Based on the supplied Discord conversation, diagram, and current local source. This is an implementation proposal, not a statement about published roadmap commitments. No application code was changed or runtime tests performed for this analysis.

## Interpretation

The requested outcome is: import a reusable model BOM, import finished-product orders, automatically calculate component quantities, and track production of those components back to the original order.

The example proves a single-level requirement: two sofas × four identical parts = one component production job for eight parts. True multi-level support additionally handles sofa → frame → frame parts, multiplying quantities along each path. The diagram shows sales-order references, work orders, finished-product identities and quantities, then a model-specific list of parts with foam grade, dimensions, and quantity. It does not establish a need for eight individually serialized jobs or a full sales-order management module.

Suggested feature name: **Generate and track component work orders from a BOM**.

## Existing implementation and gaps

Paths below are relative to the repository root.

| Area | Evidence in current code | Consequence |
| --- | --- | --- |
| BOM structure | `backend/app/Models/BomItem.php` supports material or product-type components, quantity per unit, scrap, step association and extra data. BOMs belong to process templates. | Extend the current structure rather than adding an unrelated BOM subsystem. |
| Recursive calculations | `backend/app/Services/Material/BomExplosionService.php` expands manufactured materials through their producing templates, compounds scrap, and aggregates leaf requirements. | Arithmetic foundations exist, but product-type lines are explicitly excluded. It creates no production orders. |
| Material planning | `backend/app/Services/Material/NetRequirementsService.php` calls recursive leaf explosion using active templates. | Planning currently reads live BOMs and will need an explicit demand-ownership rule when child orders exist. |
| BOM import | `backend/app/Import/Importers/BomImporter.php` groups file rows by product/template; `backend/app/Services/Erp/BomImportService.php` resolves material codes. | Product-type components and part attributes need import support. Existing products, materials and templates are prerequisites. |
| Order creation | `backend/app/Services/WorkOrder/WorkOrderService.php` creates a single order and freezes its selected BOMs. | No parent/child generation is present in this flow. Selecting multiple BOMs currently merges requirements; it does not establish a production hierarchy. |
| Order import | `backend/app/Services/CsvImport/WorkOrderImportService.php` creates orders directly and uses `ProcessTemplate/SnapshotService.php`. | Adding generation only to `createWorkOrder()` would miss the requested import workflow. |
| Frozen configuration | `backend/app/Models/ProcessTemplate.php::toSnapshot()` and `backend/app/Services/ProcessTemplate/SnapshotService.php` snapshot immediate BOM lines. | A generated hierarchy must freeze the resolved descendant templates, BOM paths and attributes too. |
| Execution | `backend/app/Models/Batch.php`, `backend/app/Models/BatchStep.php` and `WorkOrderService::createBatch()` provide quantities, operations and statuses. | Reuse these through child work orders. Component-to-assembly dependencies need to be added. |
| Sales reference | `backend/app/Models/WorkOrder.php` and imports support `customer_order_no`. | Use this for initial sales-reference grouping. Do not require a new sales-order module to deliver component tracking. |

Existing relevant tests include `backend/tests/Feature/Material/HierarchicalBomTest.php`, `backend/tests/Feature/WorkOrder/MultiBomOrderTest.php`, `backend/tests/Feature/Api/V1/Erp/ErpWorkOrderImportTest.php` and `backend/tests/Feature/Import/EntityImportersTest.php`. These are source evidence, not tests run during this review.

## Proposed behavior and scope

- Generate pending child work orders automatically when an import explicitly enables component generation. Provide the same option for manually created orders. Keep the existing default behavior for current integrations.
- Generate production work for manufactured components with a valid process. Purchased inputs remain material requirements. A manufactured part can have operations without having its own component BOM.
- Default to one child order per resolved BOM occurrence, with the required quantity, and batches/steps underneath. Preserve separate occurrences on different branches. Cross-order batching and aggregation can follow later with allocation records.
- Track part quantities initially: target, good output, scrap and remaining. Do not infer mandatory per-piece serialization from “each individual part.”
- The finished-product order keeps its own assembly process and output quantity. Producing eight parts must not increment sofa output by eight or complete the sofa order automatically.
- For the first release, require all required component quantities to be ready before the consuming assembly step starts. Partial assembly release and supply from existing component stock are later extensions.

Example, assuming zero scrap:

```text
Customer order SO1435
└─ WO-SOFA: Sofa 2 Seater, target 2
   ├─ WO-SOFA-01: Foam part A, target 8 (4 per sofa)
   │  └─ Batch → Cutting → Inspection
   └─ Assembly → Upholstery → Final inspection
```

A raw foam sheet is a material input to cutting; the cut foam part is its manufactured output. Foam grade and dimensions identify/specify that part and should be visible to the operator, not used as substitutes for a stable component code.

## Implementation sequence

### 1. Define component identity and import contract

Support both existing BOM component kinds through a common resolver:

- Material: purchased input, or manufactured component resolved through `producing_process_template_id`.
- Product type: manufactured component resolved to an explicitly selected template version, or a deterministic active version that is frozen when generating.
- Require exactly one component identity per BOM line. Validate process/output compatibility for manufactured materials; a producing template alone must not imply that its output is already posted to stock.
- Add an optional component template version/reference for product-type components. Avoid re-resolving descendants against newer active templates after generation.
- Extend the existing importer with `component_kind`, `component_code`, optional component template version, stable BOM line key, quantity, unit and supported part attributes. Preserve `material_code` compatibility for existing files/API clients.
- Store reusable grade/dimensions on the component master where supported; store model-specific overrides on the BOM line and freeze their resolved values. Explicit units are required for dimensions and quantities. Extend import/snapshot handling for these attributes.
- Validate the complete recipe before replacement. Currently the file importer can discard a malformed row before sending the remaining recipe to replacement; the new contract must not silently apply an incomplete recipe.
- Route import and manual writes through common cycle/component validation. The existing ERP BOM service writes directly through `BomItem::updateOrCreate()`, bypassing `BomService` cycle checks.

Deliverable: reusable sofa/component BOMs import successfully, with actionable errors for missing identities, invalid quantities, ambiguous versions and cycles.

### 2. Create an immutable production expansion plan

Introduce a planning service separate from persistence. Extend/reuse the existing explosion logic through the common resolver, retaining the current material-planning response contract.

Each planned occurrence needs a stable path, parent occurrence, source BOM line, component identity, resolved template/version, quantity/unit, scrap policy, part attributes and consuming parent step. Freeze the entire resolved plan and component process snapshots.

Rules:

- Multiply down every level. Keep required good quantity distinct from any scrap allowance in planned starts/material demand.
- Use decimal-safe calculations and explicit unit rounding: whole pieces round according to the agreed production policy; length/weight retain their allowed precision. Never multiply an already scaled quantity a second time.
- Reject cycles, excessive depth/node count and unresolved manufactured processes with the offending path. The existing recursion guard returns an empty child list at its limit; execution planning must fail rather than accept a truncated plan.
- Keep a tree for ownership and dependencies, and a separate aggregated material-demand projection.
- If multiple BOM templates are selected, preserve each source occurrence and the existing primary-process selection rules. Their merged material list cannot serve as the production hierarchy.
- Preview component jobs, quantities, processes and errors before committing. Cache resolved templates within planning to avoid queries per repeated occurrence.

Deliverable: a deterministic preview for both the eight-part example and a three-level assembly.

### 3. Persist and generate executable child orders

Proposed additive schema:

- `work_order_expansions`: root order, expansion version, immutable plan, generation state and request identity.
- `work_order_components`: expansion, stable occurrence key, parent occurrence, component identity, source line/template, required quantity/unit, consuming step, and optional generated child-order link. Keep purchased requirements here too.
- `work_orders.parent_work_order_id` and `root_work_order_id` for hierarchy navigation; generated child identity uniquely linked to its expansion occurrence.

Use a shared orchestration service from manual, CSV/file and ERP paths. Preserve their intentional validation differences, while sharing snapshot creation and generation semantics. Refactor the two snapshot builders toward one canonical contract with regression coverage for their existing fields.

Create each root hierarchy atomically, lock the root against concurrent generation, and enforce uniqueness in the database. Dispatch external events only after successful commit. A retried import must reuse or report an existing expansion, never create duplicate child work.

Inherit customer reference and due date. Resolve each child's line/workstation from its process; leave it pending and visibly unassigned when no valid assignment exists. Use existing batch creation/release workflows for execution rather than assuming a work-order row is already an operator task.

Before any affected production starts, quantity/BOM changes can create a replacement expansion version with an audited reconciliation of pending children. Once production has started, require a controlled change; do not overwrite completed work or discard actual production. Cancellation must preserve that history and reconcile outstanding child demand.

Deliverable: importing two sofas generates the eight-part child job once, visible in planning and executable through batches.

### 4. Connect readiness, quantities and material accounting

- Add component prerequisites to the existing step readiness rules, with the dependency attached to the consuming assembly step. Default an unspecified assembly dependency to the first parent step and show this in preview.
- Base readiness on good, eligible output committed to the parent. Scrap, cancelled output and rejected/quarantined quantities do not fulfill component requirements. Serialize readiness/allocation changes to prevent concurrent double use.
- Keep separate progress for finished products and components; show which missing component blocks assembly.
- Assign input consumption to the order that physically consumes it. The cutting job consumes foam sheet; assembly consumes the resulting part if that part is stock-tracked.
- Inspect and extend the existing material-lot/output/genealogy flows before promising automatic stock receipt for a completed child. Product-type references currently have no stock material identity, so introduce an explicit output mapping when stock tracking is needed.
- Update material planning so parent recursive demand and generated child demand are not both counted. For expanded orders, derive requirements once from the frozen occurrence plan and its remaining execution demand; retain legacy behavior for unexpanded orders.
- Cover costing and production reports: component output must not be reported as additional finished-product sales output, and transferred component cost must not duplicate raw-material cost.

Deliverable: components enable their parent assembly correctly, while stock, demand and output totals remain consistent.

### 5. Expose the workflow and verify it end to end

Add a component tree/table to order detail showing part code, dimensions, foam grade, required/good/scrap/remaining quantities, child order, current operation and blocking reason. Link each child back to its root and customer reference. Carry the same component context into web/mobile operator views. Include generated-job counts and errors in import results.

Required acceptance scenarios:

1. Two sofas × four parts generates one eight-part job for that BOM occurrence, with executable operations.
2. Two sofas × two frames × three rails generates four frames and twelve rails, with correct dependency levels.
3. Purchased inputs generate requirements and no production jobs; manufactured parts with operations and an empty BOM remain valid jobs.
4. Repeated components on separate paths preserve provenance and yield correct aggregate demand.
5. Mixed product/material components, unit precision and compounded scrap follow the selected policy.
6. Cycles, missing processes and excessive expansion size fail without a partially generated root hierarchy.
7. Duplicate and concurrent imports create no duplicate work. A malformed BOM row cannot trigger partial recipe replacement.
8. Later master-data edits do not change existing plans, dimensions, component processes or quantities.
9. Partial completion, rejected output, cancellations and controlled quantity changes preserve actuals and readiness rules.
10. Parent and child orders do not double material demand, consumption, costs or finished-product output.
11. Manual, file and ERP entry points agree; existing flat BOM, multi-BOM, snapshot, stock and import tests continue to pass.

## Suggested delivery boundary and later questions

Ship the first release with recursive manufactured-component planning, automatic opt-in generation on import, quantity-based child jobs, parent visibility, full-quantity assembly readiness, repeat-import protection and correct material accounting. These together satisfy the request; a calculation preview alone does not.

Defer per-piece serial labels, cross-order cutting batches, nesting optimization, partial assembly release, automatic make-versus-stock decisions and full sales-order management.

No clarification is needed to prepare this plan. Before committing to a pilot workflow, confirm whether the requester needs individual-piece identity instead of quantity tracking, whether components are made to order or drawn from stock, and whether partial assembly is necessary. These affect scope; the recommendations above explicitly assume quantity tracking, make to order and full-quantity readiness.

## Implementation follow-up (2026-09-08)

The implemented first release and its import samples are documented in [component-production.md](component-production.md). Expansion versions are stored in `work_orders.component_plan` (including prior versions), with versioned `work_order_components` occurrences. Pending quantity amendments are supported; structural BOM/process changes and amendments after batches exist require replacement orders rather than automatic engineering-change reconciliation.

The subsequent inventory and precise-start extension is implemented and documented in [component-production.md](component-production.md#component-inventory-and-production-timing) and [component-stock-plan.md](component-stock-plan.md). The original make-to-order-only scope above describes the first iteration.
