# Component production from a BOM

Create reusable product processes, import their BOMs, then enable **Generate component work orders** when creating or importing finished-product orders. A two-sofa order with four identical parts per sofa creates a linked component order for eight parts.

## Try the furniture example

On the local Docker stack:

```bash
docker exec openmmes-backend php artisan migrate --force
docker exec openmmes-backend php artisan db:seed --class=FurnitureComponentDemoSeeder --force
```

The optional seeder creates only demo masters: `DEMO-SOFA`, `DEMO-FOAM-PART`, their processes, and the `COMP-DEMO` production line/workstations. It can be run repeatedly.

1. Create a work order for **Sofa 2 Seater — BOM demo**, quantity **2**.
2. Leave **Generate component work orders** checked. The preview opens automatically and shows one eight-piece component job; **Hide/Show component preview** toggles it.
3. Create the order, open its detail and accept it. **Component production** links to the cutting order and shows foam grade, dimensions, required quantity, eligible output, scrap and remaining quantity.
4. Accept the cutting order, then create an eight-piece batch on it and execute its process. Create a two-piece assembly batch on the parent through the existing operator/mobile batch workflow.
5. Assembly cannot start until all eight eligible component pieces are complete. Producing components does not increase the sofa order's output; completing assembly does.

The Playwright test `tests/e2e/component-production.spec.ts` runs this flow, using `ADMIN_USERNAME` / `ADMIN_PASSWORD` for an existing local test administrator. It seeds demo masters and leaves the completed demo order available for inspection. It does not reset existing data or credentials.

## Import formats

Import product types and their production processes first. Purchased components must already exist as materials. BOMs still belong to process templates.

Example BOM CSV:

```csv
product_type_code,component_kind,component_code,quantity_per_unit,component_template_version,foam_grade,length_mm,width_mm,thickness_mm
DEMO-SOFA,product_type,DEMO-FOAM-PART,4,1,VB 18/40,1985,100,10
```

For another level, add BOM rows whose `product_type_code` identifies the intermediate component. Manufactured components must have a process containing operations, but their own BOM can be empty. Product component versions default to the newest active process; set `component_template_version` to pin a version. Existing `material_code` files remain supported, with `material` as the default component kind.

A material marked manufactured resolves through its existing producing-process link. Generated components are dedicated work in progress for their parent, with no automatic receipt into saleable stock. Purchased inputs remain materials on the consuming job.

Example work-order CSV:

```csv
order_no,product_type_code,quantity,line_code,customer_order_no
SOFA-EXAMPLE-001,DEMO-SOFA,2,COMP-DEMO,SO1435
```

In **Import → Work orders**, select **Generate component work orders → Yes**. Generation defaults to off so existing imports keep their current behavior. Import results separately report the number of generated component jobs. Invalid recipes/orders return row errors; a failed hierarchy creates no partial production orders.

ERP work-order import accepts the per-order boolean:

```json
{
  "strategy": "update_or_create",
  "orders": [{
    "order_no": "SOFA-ERP-001",
    "line_code": "COMP-DEMO",
    "product_type_code": "DEMO-SOFA",
    "planned_qty": 2,
    "generate_components": true
  }]
}
```

ERP recipe components accept `component_kind`, `component_code`, optional `component_template_version`, and the dimensional fields shown above. Manual API creation accepts `generate_components`; `POST /api/v1/work-orders/component-preview` previews a product, selected `bom_template_ids`, and `planned_qty` without writing orders.

## Execution and change rules

- Quantities multiply down the tree. Scrap allowances compound, whole-piece jobs round up to whole pieces, and other manufactured job quantities round up to the existing two-decimal order precision. BOM arithmetic uses four decimal places.
- Each BOM occurrence owns its child work order. Identical parts on different branches remain separate jobs with distinct provenance. Child steps keep their own process/workstations; a unique process line is copied where it can be determined, otherwise assignment remains pending.
- A consuming parent step waits for its full required good quantity. Only completed batches supply it; scrap, failed latest quality checks, outstanding blocking quality tasks and quarantined/rejected output lots do not count as eligible output.
- A duplicate import reuses the frozen plan. Before any family member has batches, changing the root quantity creates a new plan version, cancels the previous pending child orders and retains their history. Recalculation uses the frozen BOM, even if master data has changed.
- After batches exist, quantity amendments are rejected. Changing the product or BOM structure requires a replacement order; automatic reconciliation of structural engineering changes and replacement demand after excessive scrap is not implemented.
- Cancel a generated order instead of deleting it. Cancelling the root cancels outstanding descendants and retains completed work and actual quantities. An inactive ancestor prevents further component execution.
- Expanded jobs consume and plan only their own raw inputs. Material planning uses their frozen snapshots, avoiding recursive parent demand plus child demand being counted twice.
- Child orders do not accrue customer revenue or generate saleable-product receipt documents. The ERP completion list returns root orders; an explicitly requested child includes its parent/root identifiers.
- Cost breakdowns retain additive per-order `total_cost`. `component_cost` and `assembly_total_cost` separately expose the total across the hierarchy, without adding child costs twice in existing report totals.

No individual-piece serial tracking, cross-order production batching, nesting optimization, or partial assembly release is included. Component progress is available in admin, supervisor, operator web, and mobile order details.

## Verification

Feature coverage is in `backend/tests/Feature/WorkOrder/ComponentWorkOrderTest.php`; the browser flow is in `tests/e2e/component-production.spec.ts`. Use a test database for PHPUnit (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`), not the development database.

## Component inventory and production timing

Enable **Use available component stock**, choose active warehouses, and set **Planned start** / **Planned end** (date and time, in the plant timezone shown in the preview). Component generation defaults on in the web create form; using inventory is an explicit choice. File and ERP imports keep generation off unless requested.

The expanded tree shows **Required**, **Available** (eligible stock minus other reservations), **From stock**, and **To produce**. Netting happens at every level before calculating its descendants. With 20 EX-SOFA, EX-BEAM needs 120 pieces: 50 eligible pieces give a reservation of 50 and a production order for 70. If five finished frames are available instead, only 15 frames and their corresponding descendants are planned. A fully stocked component produces no child order. Purchased materials stay in the existing material planning/consumption flow.

The preview does not reserve anything. Saving locks inventory balances and recalculates the plan atomically. The web form sends the preview fingerprint; changed availability rejects the save so the planner can **Refresh availability** and inspect the new proposal. Other creators cannot reserve the same stock. Unchecking a manufactured node never deletes its requirement: saving is allowed only if stock fully covers that requirement. Purchased input checkboxes are read-only.

A reservation is **held** until the first start of its consuming operation, when a posted warehouse issue document converts it to **issued**. This issues the entire order's allocation to production once, even when multiple batches use it. Other issues/transfers cannot consume held stock. Unissued allocations are **released** on cancellation/rejection; issued documents and actuals remain historical and cannot be reversed independently. Completing an order with unissued component reservations is rejected. Reopening a cancelled order does not reacquire released inventory; use a replacement order when supply must be rebuilt.

Before any family batch exists, a root quantity amendment releases held reservations and replans from the frozen BOM/process/document graph against current stock. The new version retains the previous plan and cancelled jobs. After batches exist, use a replacement order.

### Matching and quality

Stock must match the component item, unit, explicitly pinned process/revision, and all supplied specification attributes (including foam grade and dimensions). Missing identity metadata cannot satisfy a pinned specification. Inactive warehouses and quarantined/rejected stock are excluded. Manufactured material lots additionally require release, sufficient lot availability and a valid expiry date at the known consuming start. When that start is unknown, current eligibility is checked and the schedule remains unconfirmed; assigning/moving a start rechecks readiness. Quality/stock changes after reservation can block assembly.

ERP stock balances can supply `component_status` (`released`, `quarantine`, `rejected`) and `component_specification`, e.g.:

```json
{
  "warehouse_code": "COMPONENTS",
  "product_type_code": "FOAM-PART",
  "quantity": 50,
  "unit_of_measure": "pcs",
  "component_status": "released",
  "component_specification": {
    "component_template_id": 123,
    "extra_data": { "foam_grade": "VB 18/40", "length_mm": 1985 }
  }
}
```

The metadata describes the whole existing warehouse/item balance. Different specifications must have distinct item codes or locations; product balances do not support mixed-version product lots. An ordinary receipt does not certify its version: adding it to a balance with specification metadata clears that metadata, requiring verification/reimport before version-sensitive allocation. Existing unversioned stock may cover an unversioned, attribute-free BOM component.

### Planner and imports

`needed_at` is the **consuming parent's planned_start_at**, not the sales delivery date. Root assembly start applies to its direct parts; lower levels get their own needed times when their consuming component orders are scheduled. The planner and order detail flag an unconfirmed component schedule or a child planned to finish after it is needed. Dragging/resizing updates reservation needed times and returns visible warnings. Future planned completion never counts as already available inventory. Actual start timestamps remain on batches/operations; no actual start is invented from a plan. This feature does not automatically optimize or schedule child jobs backwards.

API creation/preview and ERP rows accept `use_component_stock`, `component_warehouse_ids`, `planned_start_at`, `planned_end_at`; creation also accepts `excluded_component_paths` and optional `component_preview_token`. Prefer ISO timestamps with an explicit offset for integrations. File import has **Use available component stock** and a single **Component warehouse** option, plus date/time column mappings `planned_start_at` and `planned_end_at`. Direct service/API consumers can select multiple warehouses. Existing generated plans are reused on repeated imports; stock-source/structure changes require a replacement order.

Order details show eligible/reserved quantities, warehouse, reservation status, and the issue document link for administrators. Component summaries obey the caller's work-order line visibility, and generated jobs preserve their product's released engineering documents through quantity amendments.
