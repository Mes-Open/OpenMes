# OpenMES REST API Documentation

OpenMES provides a versioned REST API for ERP integration, custom dashboards, and automation.

**Base URL:** `https://your-openmmes-url/api`

---

## Table of Contents

- [Authentication](#authentication)
- [Response Format](#response-format)
- [Endpoints](#endpoints)
  - [Health Check](#health-check)
  - [Authentication API](#authentication-api)
  - [Lines](#lines)
  - [Work Orders](#work-orders)
  - [Production Stops and Change Control](#production-stops-and-change-control)
  - [Batches](#batches)
  - [Batch Steps](#batch-steps)
  - [Issues](#issues)
  - [Issue Types](#issue-types)
  - [CSV Import](#csv-import)
  - [Analytics](#analytics)
  - [Reports](#reports)
  - [Audit Logs](#audit-logs)
  - [Event Logs](#event-logs)
- [ERP Integration API](#erp-integration-api)
  - [API Keys and Scopes](#api-keys-and-scopes)
  - [Work Orders (ERP)](#work-orders-erp)
  - [Master Data](#master-data)
  - [Warehouse Stock](#warehouse-stock)
  - [Stock Documents](#stock-documents)
- [Error Codes](#error-codes)
- [Rate Limiting](#rate-limiting)

---

## Authentication

All API endpoints (except `/api/health` and `/api/auth/login`) require a Bearer token.

### Obtaining a Token

**Via the web UI** (recommended):
1. Log in as Admin
2. Go to **Settings → API Tokens**
3. Create a new token and copy it

**Via the API:**

```http
POST /api/auth/login
Content-Type: application/json

{
    "username": "admin",
    "password": "your-password"
}
```

Response:
```json
{
    "token": "1|abc123...",
    "user": {
        "id": 1,
        "username": "admin",
        "email": "admin@example.com",
        "role": "Admin"
    }
}
```

### Using the Token

Include the token in every request:

```http
Authorization: Bearer 1|abc123...
```

### Revoking a Token

```http
POST /api/auth/logout
Authorization: Bearer 1|abc123...
```

---

## Response Format

All responses return JSON. Successful responses follow this structure:

```json
{
    "data": { ... },
    "meta": { ... }
}
```

Lists include pagination metadata:

```json
{
    "data": [ ... ],
    "meta": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 15,
        "total": 72
    }
}
```

---

## Endpoints

### Health Check

Check if the server is running. No authentication required.

```http
GET /api/health
```

Response:
```json
{
    "status": "ok",
    "timestamp": "2025-04-03T10:00:00+00:00"
}
```

---

### Authentication API

#### Get Current User

```http
GET /api/auth/me
Authorization: Bearer <token>
```

Response:
```json
{
    "id": 1,
    "username": "admin",
    "email": "admin@example.com",
    "name": "Administrator",
    "role": "Admin"
}
```

#### Change Password

```http
POST /api/auth/change-password
Authorization: Bearer <token>
Content-Type: application/json

{
    "current_password": "old-password",
    "new_password": "new-secure-password",
    "new_password_confirmation": "new-secure-password"
}
```

---

### Lines

#### List Lines

Returns all active production lines.

```http
GET /api/v1/lines
Authorization: Bearer <token>
```

Response:
```json
{
    "data": [
        {
            "id": 1,
            "name": "Line A",
            "code": "LA",
            "description": "Main assembly line",
            "is_active": true,
            "division": null,
            "workstations_count": 4
        }
    ]
}
```

#### Get Line

```http
GET /api/v1/lines/{id}
Authorization: Bearer <token>
```

---

### Work Orders

#### List Work Orders

```http
GET /api/v1/work-orders
Authorization: Bearer <token>
```

Query parameters:

| Parameter | Type | Description |
|---|---|---|
| `status` | string | Filter by status: `pending`, `accepted`, `in_progress`, `completed`, `paused`, `rejected` |
| `line_id` | integer | Filter by line |
| `due_before` | date | Filter by due date (YYYY-MM-DD) |
| `week_number` | integer | Filter by production week |
| `month_number` | integer | Filter by production month |
| `production_year` | integer | Filter by production year |
| `search` | string | Search in order_no and product_name |
| `per_page` | integer | Results per page (default: 15, max: 100) |
| `page` | integer | Page number |

Response:
```json
{
    "data": [
        {
            "id": 42,
            "order_no": "WO-2025-0042",
            "product_name": "Wooden Chair Model A",
            "quantity": 100,
            "status": "in_progress",
            "priority": 2,
            "due_date": "2025-04-10",
            "week_number": 15,
            "month_number": 4,
            "production_year": 2025,
            "line": {
                "id": 1,
                "name": "Line A"
            },
            "product_type": {
                "id": 3,
                "name": "Wooden Chair"
            },
            "batches_count": 2,
            "produced_quantity": 65,
            "created_at": "2025-04-01T08:00:00Z",
            "updated_at": "2025-04-02T14:30:00Z"
        }
    ],
    "meta": { ... }
}
```

#### Get Work Order

```http
GET /api/v1/work-orders/{id}
Authorization: Bearer <token>
```

Returns full detail including batches and their steps.

#### Create Work Order

```http
POST /api/v1/work-orders
Authorization: Bearer <token>
Content-Type: application/json

{
    "order_no": "WO-2025-0099",
    "product_name": "Wooden Chair Model B",
    "quantity": 50,
    "line_id": 1,
    "product_type_id": 3,
    "process_template_id": 2,
    "priority": 1,
    "due_date": "2025-04-20",
    "week_number": 17,
    "production_year": 2025
}
```

Required fields: `order_no`, `quantity`

Response: `201 Created` with the created work order object.

#### Update Work Order

```http
PATCH /api/v1/work-orders/{id}
Authorization: Bearer <token>
Content-Type: application/json

{
    "priority": 3,
    "due_date": "2025-04-25"
}
```

Only updatable when status is `pending` or `accepted`.

#### Delete Work Order

```http
DELETE /api/v1/work-orders/{id}
Authorization: Bearer <token>
```

Only deletable when status is `pending` or `rejected`. Returns `204 No Content`.

---

### Production Stops and Change Control

Stopping a running order, changing what it builds under review, and resuming on the
new configuration ([#182](https://github.com/Mes-Open/OpenMes/issues/182)). Nothing
here rewrites execution data: completed batch steps, recorded consumption, quality
results and produced quantities are never modified by a change.

#### Stop Production

```http
POST /api/v1/work-orders/{id}/stop
Authorization: Bearer <token>
Content-Type: application/json

{
    "type": "ENGINEERING_CHANGE",
    "reason": "Hole diameter must be changed before continuing production",
    "batch_id": 18,
    "requires_change": true,
    "downtime_reason_id": 4
}
```

`type` is one of `OPERATIONAL`, `MATERIAL_SHORTAGE`, `MACHINE_FAILURE`,
`QUALITY_HOLD`, `ENGINEERING_CHANGE`, `OTHER`. Required: `type`, `reason`.

The stop records who stopped production and when, and photographs the state at that
moment (produced quantity, batches, in-progress steps, allocated/consumed material,
active configuration version). `requires_change: true` sets the order to
`CHANGE_HOLD` — resume is then refused until an approved change request has been
applied; otherwise the order goes to `PAUSED`. Supplying `downtime_reason_id` also
opens a linked `production_downtimes` record, closed automatically on resume.

Only an `IN_PROGRESS` order can be stopped, and only one stop may be open at a time
(`422` otherwise). Response: `201 Created`.

#### List Stops

```http
GET /api/v1/work-orders/{id}/stops
Authorization: Bearer <token>
```

Newest first. Each row carries `duration_minutes` once resumed, and
`duration_minutes_current` as a running total while the stop is still open.

#### Resume Production

```http
POST /api/v1/work-orders/{id}/resume
Authorization: Bearer <token>
Content-Type: application/json

{
    "change_request_id": 52,
    "notes": "Production resumed using product revision C"
}
```

Both fields are optional — an order paused the simple way resumes on an empty body.
An order on `CHANGE_HOLD` must quote a change request that has been **applied**
(not merely approved), or the call answers `422`. Resuming closes the open stop,
stamps its duration and returns the order to `IN_PROGRESS`.

#### Create Change Request

```http
POST /api/v1/work-orders/{id}/change-requests
Authorization: Bearer <token>
Content-Type: application/json

{
    "title": "Move remaining production to revision C",
    "reason": "Customer approved ECO-118",
    "proposed": {
        "product_revision_id": 42,
        "planned_qty": 150,
        "bom_template_ids": [7, 9]
    },
    "effective_from": "NEXT_BATCH",
    "produced_disposition": "Units 1-35 ship as revision B",
    "material_disposition": "Return unused revision-B brackets to stock"
}
```

`proposed` accepts only these fields: `product_revision_id`, `planned_qty`,
`line_id`, `bom_template_ids`, `due_date`, `description`, `production_notes`.
Anything else is rejected. `effective_from` is `NEXT_BATCH` (default),
`REMAINING_QUANTITY` or `IMMEDIATE`.

The response carries the generated code (`CR/2026/0001`) and the **impact analysis**:
produced vs. remaining quantity, batch and step counts, allocated/consumed material,
the revision change, the engineering documents being replaced, and warnings where the
proposal conflicts with completed work.

#### Read and Edit

```http
GET   /api/v1/work-orders/{id}/change-requests
GET   /api/v1/work-order-change-requests/{id}
GET   /api/v1/work-order-change-requests/{id}/impact
PATCH /api/v1/work-order-change-requests/{id}
```

`GET .../{id}` includes a field-by-field `diff`. `GET .../impact` recomputes the
analysis live for a review screen (the stored `impact` is frozen as the approver saw
it). `PATCH` works on drafts only.

#### Review Workflow

```http
POST /api/v1/work-order-change-requests/{id}/submit
POST /api/v1/work-order-change-requests/{id}/approve
POST /api/v1/work-order-change-requests/{id}/reject     { "reason": "..." }
POST /api/v1/work-order-change-requests/{id}/cancel
POST /api/v1/work-order-change-requests/{id}/apply
```

Status flow: `DRAFT → SUBMITTED → APPROVED → APPLIED`, with `REJECTED` and
`CANCELLED` as alternative endings. Any other transition answers `422`. Rejection
requires a reason. `approve`, `reject` and `apply` require the
`approve work order changes` permission (Admin and Supervisor by default); raising
and editing a request needs only `edit work orders`.

#### Apply a Change

```http
POST /api/v1/work-order-change-requests/{id}/apply
Authorization: Bearer <token>
Content-Type: application/json

{
    "effective_from": "REMAINING_QUANTITY",
    "implementation_notes": "Applied during the night shift"
}
```

Applying is only allowed on an **approved** request against a **stopped** order. It
captures the before-state, rebuilds the configuration and appends it as the next
`work_order_snapshots` version — earlier versions stay readable exactly as the shop
floor received them, and new batches are stamped with the version they were generated
from. The remaining material requirements are recalculated onto the record; existing
allocations and consumption are untouched.

Refused with `422` when the planned quantity would fall below what was already
produced, or when `IMMEDIATE` is requested after any step has been started or
completed (use `NEXT_BATCH` or `REMAINING_QUANTITY`).

---

### Batches

A batch is a production run for a work order. Large orders may have multiple batches.

#### List Batches for a Work Order

```http
GET /api/v1/work-orders/{workOrderId}/batches
Authorization: Bearer <token>
```

Response:
```json
{
    "data": [
        {
            "id": 10,
            "work_order_id": 42,
            "quantity": 30,
            "status": "completed",
            "started_at": "2025-04-02T08:00:00Z",
            "completed_at": "2025-04-02T11:30:00Z",
            "operator": {
                "id": 5,
                "username": "operator1"
            },
            "steps": [ ... ]
        }
    ]
}
```

#### Create Batch

Start a new production run for a work order.

```http
POST /api/v1/work-orders/{workOrderId}/batches
Authorization: Bearer <token>
Content-Type: application/json

{
    "quantity": 25
}
```

The work order must be in `accepted` or `in_progress` status.

#### Get Batch

```http
GET /api/v1/batches/{id}
Authorization: Bearer <token>
```

---

### Batch Steps

Each batch progresses through steps defined in the process template.

#### Start a Step

```http
POST /api/v1/batch-steps/{batchStepId}/start
Authorization: Bearer <token>
```

Marks the step as in progress and records the start time and operator.

#### Complete a Step

```http
POST /api/v1/batch-steps/{batchStepId}/complete
Authorization: Bearer <token>
Content-Type: application/json

{
    "comment": "Completed without issues"
}
```

`comment` is optional.

#### Report a Problem on a Step

```http
POST /api/v1/batch-steps/{batchStepId}/problem
Authorization: Bearer <token>
Content-Type: application/json

{
    "issue_type_id": 2,
    "description": "Material crack found during assembly"
}
```

This also creates an Issue linked to the work order.

---

### Issues

Issues (Andon system) track problems reported during production.

#### List Issues

```http
GET /api/v1/issues
Authorization: Bearer <token>
```

Query parameters:

| Parameter | Type | Description |
|---|---|---|
| `status` | string | `open`, `acknowledged`, `resolved`, `closed` |
| `line_id` | integer | Filter by line |
| `work_order_id` | integer | Filter by work order |
| `issue_type_id` | integer | Filter by issue type |

Response:
```json
{
    "data": [
        {
            "id": 7,
            "work_order_id": 42,
            "issue_type": {
                "id": 2,
                "name": "Material shortage",
                "is_critical": false
            },
            "description": "Steel rods out of stock",
            "status": "acknowledged",
            "reported_by": {
                "id": 5,
                "username": "operator1"
            },
            "acknowledged_by": {
                "id": 3,
                "username": "supervisor1"
            },
            "created_at": "2025-04-02T09:15:00Z"
        }
    ]
}
```

#### Get Issue

```http
GET /api/v1/issues/{id}
Authorization: Bearer <token>
```

#### Create Issue

```http
POST /api/v1/issues
Authorization: Bearer <token>
Content-Type: application/json

{
    "work_order_id": 42,
    "issue_type_id": 2,
    "description": "Detailed description of the problem"
}
```

#### Acknowledge Issue

```http
POST /api/v1/issues/{id}/acknowledge
Authorization: Bearer <token>
```

Requires Supervisor or Admin role.

#### Resolve Issue

```http
POST /api/v1/issues/{id}/resolve
Authorization: Bearer <token>
Content-Type: application/json

{
    "resolution_notes": "Restocked from warehouse B"
}
```

#### Close Issue

```http
POST /api/v1/issues/{id}/close
Authorization: Bearer <token>
```

#### Line Issue Stats

Returns issue counts grouped by line.

```http
GET /api/v1/issues/stats/line
Authorization: Bearer <token>
```

---

### Issue Types

#### List Issue Types

```http
GET /api/v1/issue-types
Authorization: Bearer <token>
```

Response:
```json
{
    "data": [
        {
            "id": 1,
            "name": "Machine breakdown",
            "description": "Equipment failure requiring maintenance",
            "is_critical": true
        },
        {
            "id": 2,
            "name": "Material shortage",
            "is_critical": false
        }
    ]
}
```

#### Create / Update / Delete Issue Types

Admin role required.

```http
POST /api/v1/issue-types
PATCH /api/v1/issue-types/{id}
DELETE /api/v1/issue-types/{id}
```

---

### CSV Import

Import work orders in bulk from a CSV or Excel file.

#### Upload File

```http
POST /api/v1/csv-imports/upload
Authorization: Bearer <token>
Content-Type: multipart/form-data

file=@work_orders.csv
```

Response includes the parsed headers so you can build the column mapping.

#### Execute Import

```http
POST /api/v1/csv-imports/execute
Authorization: Bearer <token>
Content-Type: application/json

{
    "import_id": "abc123",
    "strategy": "insert_or_update",
    "mapping": {
        "order_no": "Order Number",
        "quantity": "Qty",
        "product_name": "Description",
        "line_id": "Line Code",
        "due_date": "Due Date"
    }
}
```

`strategy` options: `insert_only`, `update_only`, `insert_or_update`

#### List Imports

```http
GET /api/v1/csv-imports
Authorization: Bearer <token>
```

#### Get Import Status

```http
GET /api/v1/csv-imports/{id}
Authorization: Bearer <token>
```

#### Saved Mappings

```http
GET /api/v1/csv-import-mappings
POST /api/v1/csv-import-mappings
```

---

### Analytics

Supervisor and Admin roles required.

#### Overview

Key production metrics for the current period.

```http
GET /api/v1/analytics/overview
Authorization: Bearer <token>
```

Response:
```json
{
    "data": {
        "total_orders": 152,
        "completed_orders": 98,
        "in_progress_orders": 24,
        "pending_orders": 30,
        "open_issues": 7,
        "on_time_rate": 0.89,
        "avg_cycle_time_hours": 4.2
    }
}
```

#### Production by Line

```http
GET /api/v1/analytics/production-by-line
Authorization: Bearer <token>
```

#### Cycle Time

```http
GET /api/v1/analytics/cycle-time
Authorization: Bearer <token>
```

Query parameters: `line_id`, `from` (date), `to` (date)

#### Throughput

```http
GET /api/v1/analytics/throughput
Authorization: Bearer <token>
```

Query parameters: `period` (`daily`, `weekly`, `monthly`), `line_id`

#### Issue Statistics

```http
GET /api/v1/analytics/issue-stats
Authorization: Bearer <token>
```

#### Step Performance

```http
GET /api/v1/analytics/step-performance
Authorization: Bearer <token>
```

---

### Reports

Supervisor and Admin roles required.

#### Production Summary Report

```http
GET /api/v1/reports/production-summary
Authorization: Bearer <token>
```

Query parameters: `from`, `to`, `line_id`

#### Batch Completion Report

```http
GET /api/v1/reports/batch-completion
Authorization: Bearer <token>
```

#### Downtime Report

```http
GET /api/v1/reports/downtime
Authorization: Bearer <token>
```

#### Export CSV

```http
GET /api/v1/reports/export-csv?report=production-summary&from=2025-04-01&to=2025-04-30
Authorization: Bearer <token>
```

Returns a CSV file download.

---

### Audit Logs

Admin role required.

#### List Audit Logs

```http
GET /api/v1/audit-logs
Authorization: Bearer <token>
```

Query parameters: `from`, `to`, `user_id`, `entity_type`

#### Logs for a Specific Entity

```http
GET /api/v1/audit-logs/entity?entity_type=WorkOrder&entity_id=42
Authorization: Bearer <token>
```

#### Export Audit Logs

```http
GET /api/v1/audit-logs/export?from=2025-04-01&to=2025-04-30
Authorization: Bearer <token>
```

Returns a CSV file download.

---

### Event Logs

#### List Event Logs

```http
GET /api/v1/event-logs
Authorization: Bearer <token>
```

#### Event Logs for a Specific Entity

```http
GET /api/v1/event-logs/entity?entity_type=WorkOrder&entity_id=42
Authorization: Bearer <token>
```

---

## ERP Integration API

Everything under `/api/v1/erp/*` is the machine-to-machine surface an ERP talks to.
It is **ERP-agnostic**: one canonical JSON contract that any ERP (SAP, Comarch,
enova365, Dynamics / Business Central, Pantheon, Subiekt, …) can be mapped onto.
The mapping from a specific ERP's tables to this contract is integration work on
the ERP side — OpenMES deliberately ships no vendor-specific adapters.

Requires the **ERP integration** module to be enabled (Settings → Modules); with it
off every endpoint below returns 404.

### API Keys and Scopes

These endpoints do **not** use user tokens. They authenticate with a per-integration
API key, created in **Settings → API Keys**, and are authorized per endpoint by scope.

```http
POST /api/v1/erp/products/import
X-Api-Key: omk_xxxxxxxxxxxxxxxxxxxxxxxx
Content-Type: application/json
```

`Authorization: Bearer omk_…` is accepted as an alternative to the `X-Api-Key` header.

| Scope | Grants |
|---|---|
| `erp:orders:import` | Import work orders |
| `erp:production:read` | Read production completions |
| `erp:quality:read` | Read quality / non-conformance reports |
| `erp:masterdata:write` | Import products, materials, material lots and recipes |
| `erp:stock:read` | Read warehouse balances and the stock-document backlog |
| `erp:stock:write` | Sync warehouse balances, acknowledge stock documents |

A key sees only its own tenant's data. Missing or inactive key → `401`; key without
the required scope → `403`.

**Import response contract.** Every `*/import` endpoint processes rows
independently and answers with a per-row report — one unresolvable reference never
fails the batch:

```json
{
    "data": {
        "imported": 12,
        "updated": 3,
        "skipped": 1,
        "errors": [
            { "row": 7, "field": "material_code", "message": "Material 'GHOST' not found" }
        ]
    }
}
```

`200` when `errors` is empty, `207 Multi-Status` when some rows failed, `422` when
the payload itself is malformed (wrong types, missing required keys, too many rows).

`strategy` on the master-data imports: `update_or_create` (default), `skip_existing`,
`error_on_duplicate`.

### Work Orders (ERP)

```http
POST /api/v1/erp/work-orders/import          # scope: erp:orders:import
GET  /api/v1/erp/work-orders/{id}            # scope: erp:production:read
GET  /api/v1/erp/production/completions      # scope: erp:production:read
GET  /api/v1/erp/quality/issues              # scope: erp:quality:read
```

```json
{
    "strategy": "update_or_create",
    "orders": [
        {
            "order_no": "WO-2026-001",
            "line_code": "L1",
            "product_type_code": "BREAD-01",
            "planned_qty": 500,
            "due_date": "2026-09-01",
            "customer_order_no": "PO-77",
            "priority": 10
        }
    ]
}
```

The export endpoints are cursor-paginated — follow `meta.next_cursor` until it is
null — and accept `?since=<ISO timestamp>` for incremental polling.

### Master Data

All four require the `erp:masterdata:write` scope.

#### Products

```http
POST /api/v1/erp/products/import
```

```json
{
    "external_system": "pantheon",
    "only_categories": ["FINISHED", "SEMI"],
    "products": [
        {
            "code": "BREAD-01",
            "name": "Rye bread 500g",
            "category": "FINISHED",
            "unit_of_measure": "pcs"
        }
    ]
}
```

ERPs keep finished products and raw materials in **one** item table, told apart by a
classification code (Pantheon calls it a Classification, `acClassif`). Send the whole
item list and let `only_categories` decide what becomes a product — matching is
case-insensitive, and rows outside the allowlist come back as `skipped`. Omit
`only_categories` to accept everything. Products are matched by `code`.

#### Materials

```http
POST /api/v1/erp/materials/import
```

```json
{
    "only_categories": ["RAW", "PACKAGING"],
    "materials": [
        {
            "code": "FLOUR-01",
            "name": "Rye flour",
            "category": "RAW",
            "unit_of_measure": "kg",
            "tracking_type": "batch",
            "unit_price": 2.35,
            "price_currency": "EUR",
            "min_stock_level": 500
        }
    ]
}
```

`tracking_type`: `none` | `batch` | `serial`. The row's `material_type_code` (or, if
absent, its `category`) becomes the OpenMES material type and is created on first use.

#### Material Lots and Available Quantities

```http
POST /api/v1/erp/material-lots/import
```

```json
{
    "warehouse_code": "0100",
    "lots": [
        {
            "material_code": "FLOUR-01",
            "lot_number": "L-2026-0431",
            "quantity_available": 120.5,
            "unit_of_measure": "kg",
            "expiry_date": "2026-12-31",
            "supplier_lot_no": "SUP-99"
        }
    ]
}
```

The ERP is authoritative about what physically exists, so `quantity_available`
**replaces** the lot's remaining quantity — re-running the sync converges instead of
inflating stock. Lots are matched by (`material_code`, `lot_number`).

`warehouse_code` (top level, or per row) matches a warehouse's `code` **or** its
`erp_code`. When a warehouse is named, the per-warehouse lot balance is written, the
material's warehouse total is recomputed from its lots, and the global material
quantity is re-derived from those totals (booked as an audited `adjustment` in the
stock ledger).

#### Recipes (Bills of Materials)

```http
POST /api/v1/erp/boms/import
```

```json
{
    "mode": "replace",
    "recipes": [
        {
            "product_type_code": "BREAD-01",
            "components": [
                { "material_code": "FLOUR-01", "quantity_per_unit": 0.5, "scrap_percentage": 2 },
                { "material_code": "YEAST-01", "quantity_per_unit": 0.01 }
            ]
        }
    ]
}
```

`quantity_per_unit` is the quantity per **one** unit of finished product — total
consumption is that times the quantity produced, which is how ERPs store recipes.
The recipe attaches to the product's process template (`process_template_version`
picks one explicitly; otherwise the newest active template wins).

`mode`: `replace` (default — the payload becomes the template's complete component
list, so components the ERP dropped are removed) or `merge` (upsert only what is
listed). A recipe with one unknown material is reported as a single failed row and
applied not at all — never half-way.

### Warehouse Stock

Requires the **Warehouses** module in addition to the ERP module.

#### Push a Balance Snapshot

```http
POST /api/v1/erp/stock/import            # scope: erp:stock:write
```

```json
{
    "warehouse_code": "0100",
    "balances": [
        { "material_code": "FLOUR-01", "quantity": 340.25, "unit_of_measure": "kg" },
        { "warehouse_code": "0200", "product_type_code": "BREAD-01", "quantity": 640 }
    ]
}
```

Each row names **exactly one** item — `material_code` or `product_type_code`. The
quantity is a snapshot that replaces the OpenMES balance, and the resulting change to
the global per-material quantity is booked as an audited `adjustment`, so allocation,
MRP and the shortage reports never see an unexplained jump.

#### Read Balances

```http
GET /api/v1/erp/stock?warehouse=0100&since=2026-08-01T00:00:00Z    # scope: erp:stock:read
```

```json
{
    "data": [
        {
            "warehouse_code": "RAW-1",
            "warehouse_erp_code": "0100",
            "material_code": "FLOUR-01",
            "product_type_code": null,
            "lot_number": "L-2026-0431",
            "quantity": 120.5,
            "unit_of_measure": "kg",
            "erp_synced_at": "2026-08-03T18:34:38+00:00",
            "updated_at": "2026-08-03T18:34:38+00:00"
        }
    ],
    "meta": { "next_cursor": null, "has_more": false, "count": 1, "per_page": 100 }
}
```

A row with `lot_number: null` is the material's **total** in that warehouse; rows
carrying a lot are its breakdown — do not add the two together.

### Stock Documents

The warehouse paperwork production generates: a material release for what a work
order consumed and a product receipt for what it produced (plus the reverse types).
A completed work order creates them as drafts; posting one is what moves stock.

#### Poll the Backlog

```http
GET /api/v1/erp/stock-documents?unsynced_only=1                   # scope: erp:stock:read
```

```json
{
    "data": [
        {
            "id": 3,
            "document_no": "MI/2026/0001",
            "type": "material_issue",
            "status": "posted",
            "direction": "out",
            "warehouse_code": "RAW-1",
            "warehouse_erp_code": "0100",
            "work_order_no": "WO-2026-001",
            "erp_reference": null,
            "erp_synced_at": null,
            "posted_at": "2026-08-03T18:37:13+00:00",
            "lines": [
                {
                    "material_code": "FLOUR-01",
                    "product_type_code": null,
                    "lot_number": "L-2026-0431",
                    "quantity": 51,
                    "unit_of_measure": "kg",
                    "notes": null
                }
            ]
        }
    ],
    "meta": { "next_cursor": null, "has_more": false, "count": 1, "per_page": 100 }
}
```

`type`: `material_issue` | `material_receipt` | `product_receipt` | `product_issue`.
`direction` is `in` or `out` — line quantities are always positive, the type carries
the sign. Defaults to `status=posted`, because a draft is not a real stock movement
yet; add `&unsynced_only=1` for only what the ERP has not booked, and `?since=` /
`?type=` / `?warehouse=` to narrow further.

#### Acknowledge

```http
POST /api/v1/erp/stock-documents/{id}/ack                         # scope: erp:stock:write
Content-Type: application/json

{ "erp_reference": "RW-2026/00042" }
```

Records the ERP's own document number and stamps `erp_synced_at`, which takes the
document off the `unsynced_only` backlog. `erp_reference` is optional. Unknown id (or
another tenant's document) → `404`.


---

## Error Codes

| HTTP Status | Meaning |
|---|---|
| `200 OK` | Request successful |
| `201 Created` | Resource created |
| `204 No Content` | Request successful, no body |
| `400 Bad Request` | Invalid request data |
| `401 Unauthorized` | Missing or invalid token |
| `403 Forbidden` | Insufficient permissions |
| `404 Not Found` | Resource not found |
| `422 Unprocessable Entity` | Validation failed |
| `429 Too Many Requests` | Rate limit exceeded |
| `500 Internal Server Error` | Server error |

### Validation Error Format

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "order_no": ["The order no field is required."],
        "quantity": ["The quantity must be a positive number."]
    }
}
```

---

## Rate Limiting

API endpoints are rate-limited to prevent abuse:
- **Authentication endpoints** (`/api/auth/login`): 10 requests per minute per IP
- **All other endpoints**: 120 requests per minute per token

When the limit is exceeded, the server returns `429 Too Many Requests` with a `Retry-After` header indicating when to retry.
