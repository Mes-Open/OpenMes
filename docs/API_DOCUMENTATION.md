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
  - [Serial Units and Test Runs](#serial-units-and-test-runs)
  - [Issues](#issues)
  - [Issue Types](#issue-types)
  - [CSV Import](#csv-import)
  - [Analytics](#analytics)
  - [Reports](#reports)
  - [Audit Logs](#audit-logs)
  - [Event Logs](#event-logs)
- [ERP Integration API](#erp-integration-api)
  - [API Keys and Scopes](#api-keys-and-scopes)
  - [Key Introspection](#key-introspection)
  - [Work Orders (ERP)](#work-orders-erp)
  - [Master Data](#master-data)
  - [Master Data (read)](#master-data-read)
  - [Warehouse Stock](#warehouse-stock)
  - [Stock Documents](#stock-documents)
- [Webhooks](#webhooks)
  - [Events](#events)
  - [Verifying the signature](#verifying-the-signature)
  - [Legacy signature](#legacy-signature)
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

### Serial Units and Test Runs

Per-unit traceability: every serialised product (a serial number, optionally a
process serial number, PSN) with its history of labels, components, test
verdicts, packing and shipping. Requires a Sanctum token; any role.

#### List / Look Up Units

```http
GET /api/v1/serial-units?serial_no=<serial number>
GET /api/v1/serial-units?psn=<process serial>
GET /api/v1/serial-units?work_order_id=<id>&status=blocked
GET /api/v1/serial-units?search=<part of a serial>
Authorization: Bearer <token>
```

`serial_no` and `psn` are exact matches, normalised the way the plant
normalises scans (case and spacing, see Settings → System → Unit
serialisation); `search` is a substring match. Newest first, 100 rows.

#### Unit History

```http
GET /api/v1/serial-units/{id}
Authorization: Bearer <token>
```

The unit with its work order, carton, pallet, bound components and every
history row (event, operator, workstation, result, parameters, time).

#### Register a Unit

```http
POST /api/v1/serial-units
Content-Type: application/json

{ "serial_no": "<serial number>", "psn": "<process serial>", "work_order_id": <id> }
```

A unit may also be registered on its process serial alone - `{ "psn": "<process serial>", "work_order_id": <id> }` -
when the line numbers the unit at its first station and the serial number arrives later with the product label.
The serial number then goes onto that same unit: at the label station (process serial + serial number), or when a
tester posts a run carrying both numbers.

#### Hold / Release a Unit

```http
POST /api/v1/serial-units/{id}/block     { "scrap_reason_id": <id>, "note": "<what was found>" }
POST /api/v1/serial-units/{id}/unblock   { "note": "<why it may go on>" }
```

A held unit (non-conforming, with an error code from the scrap reasons) is not packed and fails its pallet's
quality gate. A passing retest does not lift a hold put on by hand. Releasing needs the Supervisor or Admin role.

#### Post a Test Run (tester integration)

The tester posts the run it just finished. Three bodies are accepted; the
format is detected from the first record, using the parsers listed in
`config/traceability.php` (a plant adds its own tester's layout there).

**1. The tester's own JSONL log**, exactly the lines it writes to file:

```http
POST /api/v1/test-runs?work_order=<order number>&source=<tester or file>
Authorization: Bearer <token>
Content-Type: application/x-ndjson

...one JSON object per line, as the tester writes its log file...
```

**2. The native JSON shape** (one object, `serial_no` is the only required field):

```http
POST /api/v1/test-runs
Authorization: Bearer <token>
Content-Type: application/json

{
    "serial_no": "<serial number>",
    "psn": "<process serial>",
    "run_id": "<unique run id>",
    "verdict": "pass | fail | rework",
    "started_at": "<ISO 8601>",
    "ended_at": "<ISO 8601>",
    "station": "<workstation code>",
    "line": "<line code>",
    "operator": "<name, e-mail or username>",
    "steps": [
        { "id": "<step id>", "name": "<step name>", "verdict": "pass | fail", "duration_ms": 0,
          "measurements": [ { "name": "<name>", "value": 0, "unit": "<unit>", "low": 0, "high": 0 } ] }
    ],
    "failed_steps": ["<step id>"],
    "extra": { "<key>": "<value>" }
}
```

`verdict` is `pass`, `fail` or `rework`; without it a run with a failed step
fails, any other passes.

**3. Decoded records**: `{ "records": [ ...the JSONL rows as JSON objects... ] }`.

Optional query or body fields beside the run: `work_order` (order number to
attach a newly seen unit to), `workstation` (code to attribute the run to,
instead of the station named in the log), `source` (free text kept on the
history row, e.g. the log file name).

What happens: the unit is registered on first sight (with its PSN and work
order); the run is stamped with the tester's own time; the verdict runs the
plant's fail policy (block after N fails, scrap, or record only); a passing
retest lifts a block; a pallet the unit is already on re-reads its quality
gate; a run with the same `run_id` posted again is not recorded twice. The
operator on the history row is the user the log names when known, otherwise
the token's user - give each tester its own account.

```json
{
    "message": "Test run recorded",
    "status": "imported",
    "data": {
        "unit": { "id": 1, "serial_no": "<serial number>", "psn": "<process serial>", "status": "blocked", "work_order_id": 1 },
        "run_id": "<unique run id>",
        "verdict": "fail",
        "history_id": 1
    }
}
```

`201` when recorded, `200` with `"status": "duplicate"` when that run was
already there, `422` when no parser recognises the body or it holds no
complete run. Rate limit: 120 requests per minute per token.

The same parsers serve the offline path: `php artisan traceability:import-jsonl <files or folders>`.

#### Record a Step / Bind a Component

```http
POST /api/v1/serial-units/{id}/steps        { "result": "pass", "parameters": { "event": "test" }, "workstation_id": <id> }
POST /api/v1/serial-units/{id}/components   { "identifier": "<lot number or component serial>", "material_id": <id>, "quantity": 1 }
```

The generic history write and the component scan, for integrations that
already know the unit's id. A tester should prefer `POST /api/v1/test-runs`,
which addresses the unit by serial number and de-duplicates runs.

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
| `erp:masterdata:read` | Read lines, warehouses and product types (codes, names, activity) |
| `erp:stock:read` | Read warehouse balances and the stock-document backlog |
| `erp:stock:write` | Sync warehouse balances, acknowledge stock documents |

A key sees only its own tenant's data. Missing, inactive or expired key → `401`; key
without the required scope → `403`.

In **Settings → API Tokens**, the **ERP API keys** section (shown while the ERP
integration module is on) lists keys, creates one with a name, scopes and optional
expiry — the full `omk_…` key is shown **once**, right after creation — and revokes
keys. Changing a key's scopes or IP allowlist is done through
`PATCH /api/v1/api-keys/{id}` (admin session).

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
        ],
        "warnings": [
            { "row": 4, "field": "unit_of_measure", "message": "Unknown unit 'Fässer' — saved as sent. Add an alias under Settings → Units of Measure to map it." }
        ]
    }
}
```

`200` when `errors` is empty, `207 Multi-Status` when some rows failed, `422` when
the payload itself is malformed (wrong types, missing required keys, too many rows).

`warnings` (master-data and stock imports) lists rows that **were** saved but deserve
a look — same shape as `errors`. It is always present (an empty array when there is
nothing to say) and never changes the status code. Clients that ignore unknown keys
need no change.

`strategy` on the master-data imports: `update_or_create` (default), `skip_existing`,
`error_on_duplicate`.

### Key Introspection

```http
GET /api/v1/erp/me                           # any valid key, no scope needed
```

Tells an integration what its key may do, so it can report a missing scope up front
instead of failing on a `403` later. Rate limited like the other reads (`erp-read`).
The key's id, prefix, hash and IP allowlist are never returned.

```json
{
    "data": {
        "name": "Odoo",
        "scopes": ["erp:masterdata:read", "erp:production:read"],
        "missing_scopes": [
            "erp:orders:import",
            "erp:quality:read",
            "erp:masterdata:write",
            "erp:stock:read",
            "erp:stock:write"
        ],
        "tenant": { "id": 1, "name": "Main plant" },
        "expires_at": "2027-01-31T23:59:00+00:00",
        "modules": { "erp": true, "warehouse": false },
        "app_version": "v0.27.1"
    }
}
```

`expires_at` and `tenant` are `null` when the key has no expiry / no tenant.
`modules.warehouse` tells whether `/erp/warehouses` and the stock endpoints have
data behind them.

### Units of Measure

ERPs name units their own way — `Units`, `szt.`, `Stück`, the UN/ECE codes `C62`,
`KGM`, `LTR` — while OpenMES stores one name per unit (`pcs`, `kg`, `l`, …). Every
master-data and stock import translates the `unit_of_measure` it receives through the
tenant's **unit aliases**, edited by an Admin under **Settings → Units of Measure**
(`/admin/unit-aliases`).

**Matching.** The incoming unit is trimmed, lower-cased, inner whitespace collapsed and
a trailing dot dropped, so `"Szt."`, `" szt "` and `"SZT"` are the same alias. It is
then looked up in the tenant's own aliases — only those: deleting an alias on the
settings page really stops it from resolving. (An install without tenants uses its
install-wide list.) A unit that already *is* a canonical unit in another case (`"KG"`)
resolves to it. Aliases are applied once — never chained.

**Defaults** (pre-filled for the install and for every tenant — existing ones by the
upgrade, new ones when they are created — all editable; a default an admin deleted is
not re-added):

| Unit | Aliases |
|---|---|
| `pcs` | pcs, pc, piece, pieces, szt, sztuka, sztuk, units, unit, ea, each, st, stk, stück, stueck, C62, H87 |
| `kg` | kg, KGM, kilogram, kilograms |
| `g` | g, GRM, gram, grams |
| `t` | t, TNE, tonne |
| `l` | l, LTR, litre, liter, litr |
| `ml` | ml, MLT |
| `m` | m, MTR, metre, meter, mb |
| `mm` / `cm` | mm, MMT / cm, CMT |
| `m2` / `m3` | m2, m², MTK / m3, m³, MTQ |
| `h` / `min` | h, HUR, hour / min |

**What happens per endpoint:**

| Endpoint | Known unit | Unknown unit | Unit differs from the item's | No unit on the row |
|---|---|---|---|---|
| `products/import`, `materials/import` | stored as the canonical unit | stored as sent, row gets a `warnings` entry (still `200`) | — | as before (`pcs` on create, unchanged on update) |
| `material-lots/import`, `stock/import` | stored in the **item's** unit | — | row **fails** (`field: unit_of_measure`), nothing is written for it | the item's unit, as before |
| `boms/import` | — recipes carry `quantity_per_unit` only, no unit field | | | |

On a quantity the unit is never converted: a balance of `340` `g` for a material kept
in `kg` is refused rather than stored as 340 kg. An item whose own unit is an alias
(`szt`, imported before aliases existed) still accepts rows in `pcs`.

```json
// POST /api/v1/erp/stock/import → 207 Multi-Status
{
    "data": {
        "imported": 1,
        "updated": 0,
        "skipped": 0,
        "errors": [
            {
                "row": 2,
                "field": "unit_of_measure",
                "message": "Unit 'g' does not match the unit of 'SUGAR-01' (kg). Quantities are not converted — send the quantity in kg or add a unit alias."
            }
        ],
        "warnings": []
    }
}
```

> **Behaviour change:** before unit checking, a stock or lot row whose unit did not
> match the item's was accepted and stored with the wrong unit. Such rows now fail.

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

`/production/completions` returns completed (`DONE`) orders by default. `status`
narrows or widens that:

| Query | Returns |
|---|---|
| *(none)* | `DONE` orders only (unchanged default) |
| `?status=IN_PROGRESS` | orders in that one status |
| `?status[]=DONE&status[]=IN_PROGRESS` | orders in any of the listed statuses |
| `?status=all` | orders in every status |

Each value must be one of `PENDING`, `ACCEPTED`, `IN_PROGRESS`, `BLOCKED`, `PAUSED`,
`CHANGE_HOLD`, `DONE`, `REJECTED`, `CANCELLED` (case-sensitive); anything else —
including `all` inside a list — is a `422` on `status.N`.

```bash
curl -H "X-Api-Key: omk_…" \
  "https://mes.example.com/api/v1/erp/production/completions?status=all&since=2026-11-01T00:00:00Z&limit=500"
```

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
inflating stock. Lots are matched by (`material_code`, `lot_number`). A lot's
`unit_of_measure`, when given, must be the material's unit after
[unit aliases](#units-of-measure) — otherwise the row fails.

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

### Master Data (read)

```http
GET /api/v1/erp/lines                        # scope: erp:masterdata:read
GET /api/v1/erp/product-types                # scope: erp:masterdata:read
GET /api/v1/erp/warehouses                   # scope: erp:masterdata:read, Warehouses module
```

Read-only dictionaries so the ERP side can offer OpenMES codes in a picker instead
of having them typed in. Same envelope as the other exports (`data` + `meta` with
`next_cursor`, `has_more`, `count`, `per_page`), ordered by creation.

| Parameter | Meaning |
|---|---|
| `since` | ISO timestamp — only rows changed (`updated_at`) at or after it |
| `active` | `1` / `0` — only active / only inactive rows; both when omitted |
| `limit` | Page size, 1–500 (default 100) |
| `cursor` | `meta.next_cursor` of the previous page |

Fields: lines — `code`, `name`, `description`, `is_active`, `updated_at`;
warehouses — `code`, `erp_code`, `name`, `kind`, `is_default`, `is_active`,
`updated_at`; product types — see below. Deleted rows are not listed.

`/erp/warehouses` returns `404` while the **Warehouses** module is off (checked
before the scope).

`GET /api/v1/erp/product-types?active=1`:

```json
{
    "data": [
        {
            "code": "BREAD-01",
            "name": "Rye bread 500 g",
            "unit_of_measure": "pcs",
            "is_active": true,
            "has_active_process_template": true,
            "updated_at": "2026-11-02T08:15:00+00:00"
        },
        {
            "code": "BREAD-02",
            "name": "Spelt bread 400 g",
            "unit_of_measure": "pcs",
            "is_active": true,
            "has_active_process_template": false,
            "updated_at": "2026-11-05T12:40:00+00:00"
        }
    ],
    "meta": { "next_cursor": null, "has_more": false, "count": 2, "per_page": 100 }
}
```

`has_active_process_template: false` means a work order for that product has no
routing to follow yet — show a warning on the ERP side before importing orders for
it rather than waiting for the import error.

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

Each row names **exactly one** item — `material_code` or `product_type_code`. A row's
`unit_of_measure`, when given, must be the item's unit after
[unit aliases](#units-of-measure) — otherwise the row fails. The
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

## Webhooks

Outgoing webhooks push domain events to your endpoint as they happen, so an ERP or
integration does not have to poll. Endpoints are managed under **Admin → Webhooks**
(or `GET/POST/PATCH/DELETE /api/v1/webhooks`). Each endpoint subscribes to one or more
events and has a signing secret.

### Request format

Every delivery is an HTTP `POST` with a JSON body:

```json
{
  "event": "work_order.status_changed",
  "data": { "...": "event-specific payload, see below" },
  "timestamp": "2026-11-06T09:15:02+00:00"
}
```

| Header | Meaning |
|---|---|
| `X-OpenMES-Event` | Event key, e.g. `work_order.progress` (`webhook.test` for *Send test*). |
| `X-OpenMES-Delivery` | Delivery id. It stays the same across retries, so use it to de-duplicate. |
| `X-OpenMES-Timestamp` | Unix time (seconds) when this attempt was signed. A new value is sent on every attempt. |
| `X-OpenMES-Signature` | `sha256=<hex HMAC-SHA256>`. See [Verifying the signature](#verifying-the-signature). |

Your custom headers (configured per endpoint) are sent too, but they cannot override the
`X-OpenMES-*` headers.

Respond with any `2xx` status within 10 seconds. A failure or a non-`2xx` response is
retried up to 5 attempts in total, with back-off of 10 s, 30 s, 60 s, 300 s and 900 s.
Each attempt has a fresh timestamp and signature.

### Events

| Event | Fires when | `data` |
|---|---|---|
| `work_order.created` | A work order is created. | Work order payload, with `previous_status: null`. |
| `work_order.status_changed` | A work order's status changes. | Work order payload. |
| `work_order.progress` | `produced_qty` changes. Throttled, see below. | Work order payload + `previous_produced_qty`. |
| `issue.created` | Any issue is reported. | Compact, id-based issue payload. |
| `quality_issue.created` | An issue is reported against a work order. | Same shape as `GET /api/v1/erp/quality/issues`. |
| `batch.completed` | A batch reaches `DONE`. | `id`, `work_order_id`, `status`, `completed_at`. |
| `stock_document.posted` | A warehouse document is posted. | Same shape as `GET /api/v1/erp/stock-documents`. |

An event only reaches endpoints of the tenant that owns the entity. An issue takes its
work order's tenant; an issue without a work order takes the reporting user's tenant (or
the API key's tenant for headless writes).

**Work order payload.** The same fields as `GET /api/v1/erp/production/...`, plus `id`,
`previous_status` and the internal ids `line_id` / `product_type_id`, which are kept for
older receivers:

```json
{
  "id": 42,
  "order_no": "WO-20261106-0042",
  "customer_order_no": "PO-4711",
  "status": "DONE",
  "planned_qty": 100.0,
  "produced_qty": 100.0,
  "unit_price": null,
  "counting_source": "manual",
  "line_code": "LINE-A",
  "product_type_code": "BREAD-01",
  "completed_at": "2026-11-06T09:15:01+00:00",
  "updated_at": "2026-11-06T09:15:01+00:00",
  "previous_status": "IN_PROGRESS",
  "line_id": 3,
  "product_type_id": 7
}
```

**Progress throttle.** A machine counter can change `produced_qty` many times a second, so
`work_order.progress` is sent at most once per work order per window. The window is 30 s
by default and is set with `WEBHOOK_PROGRESS_THROTTLE_SECONDS` (`0` turns the throttle off).
Changes inside the window are dropped, not queued. The next progress event, or the
`work_order.status_changed` event when the order finishes, carries the current quantity.

**`stock_document.posted` example:**

```json
{
  "id": 9,
  "document_no": "PR/2026/0009",
  "type": "product_receipt",
  "status": "posted",
  "direction": "in",
  "warehouse_code": "FG-1",
  "warehouse_erp_code": "MAG-02",
  "work_order_no": "WO-20261106-0042",
  "notes": null,
  "erp_reference": null,
  "erp_synced_at": null,
  "posted_at": "2026-11-06T09:16:00+00:00",
  "updated_at": "2026-11-06T09:16:00+00:00",
  "lines": [
    { "material_code": null, "product_type_code": "BREAD-01", "lot_number": null,
      "quantity": 100.0, "unit_of_measure": "pcs", "notes": null }
  ]
}
```

**`quality_issue.created` example:**

```json
{
  "id": 17,
  "title": "Seal not closed",
  "status": "OPEN",
  "disposition": "pending",
  "nc_source": null,
  "non_conforming_qty": 3.0,
  "issue_type": "Seal defect",
  "severity": "HIGH",
  "work_order_no": "WO-20261106-0042",
  "root_cause": null,
  "containment_action": null,
  "reported_at": "2026-11-06T09:20:00+00:00",
  "acknowledged_at": null,
  "resolved_at": null,
  "closed_at": null,
  "updated_at": "2026-11-06T09:20:00+00:00"
}
```

### Verifying the signature

The signature is an HMAC-SHA256 over `"{timestamp}.{raw body}"`, keyed with the endpoint's
signing secret. The timestamp is part of what is signed, so a captured request cannot be
replayed later with a new timestamp.

1. **Read the raw request body** as bytes, before any JSON parsing. Re-encoding parsed JSON
   changes the bytes and the signature will not match.
2. **Read `X-OpenMES-Timestamp`.** Reject the request if it is missing, not an integer, or
   more than **300 seconds (5 minutes)** away from your current time: `|now − timestamp| > 300`.
3. **Build the signed string:** `timestamp + "." + rawBody`.
4. **Compute** `expected = "sha256=" + hex(HMAC_SHA256(secret, signedString))`.
5. **Compare** `expected` with `X-OpenMES-Signature` using a **constant-time** comparison.
   Reject the request on a mismatch.
6. *(Optional)* Store processed `X-OpenMES-Delivery` ids and ignore a repeat, so a retry
   after a timeout is not processed twice.

PHP:

```php
$body = file_get_contents('php://input');
$ts = $_SERVER['HTTP_X_OPENMES_TIMESTAMP'] ?? '';
$sig = $_SERVER['HTTP_X_OPENMES_SIGNATURE'] ?? '';

if (! ctype_digit($ts) || abs(time() - (int) $ts) > 300) {
    http_response_code(400); exit;
}
$expected = 'sha256='.hash_hmac('sha256', $ts.'.'.$body, $secret);
if (! hash_equals($expected, $sig)) {
    http_response_code(401); exit;
}
```

Node.js (Express, with `express.raw({ type: 'application/json' })`):

```js
const crypto = require('crypto');

function verify(req, secret) {
  const ts = req.get('X-OpenMES-Timestamp') ?? '';
  const sig = req.get('X-OpenMES-Signature') ?? '';
  if (!/^\d+$/.test(ts) || Math.abs(Date.now() / 1000 - Number(ts)) > 300) return false;
  const expected = 'sha256=' + crypto.createHmac('sha256', secret)
    .update(`${ts}.`).update(req.body).digest('hex');
  const a = Buffer.from(expected);
  const b = Buffer.from(sig);
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}
```

Python:

```python
import hashlib, hmac, time

def verify(raw_body: bytes, headers, secret: str) -> bool:
    ts = headers.get("X-OpenMES-Timestamp", "")
    sig = headers.get("X-OpenMES-Signature", "")
    if not ts.isdigit() or abs(time.time() - int(ts)) > 300:
        return False
    expected = "sha256=" + hmac.new(secret.encode(), ts.encode() + b"." + raw_body, hashlib.sha256).hexdigest()
    return hmac.compare_digest(expected, sig)
```

### Legacy signature

Endpoints created before timestamped signatures existed were switched to **Legacy
signature** automatically on upgrade, so they keep working. For them,
`X-OpenMES-Signature` is `sha256=hex(HMAC_SHA256(secret, rawBody))`, without the
timestamp. `X-OpenMES-Timestamp` is still sent, but it is not signed. New endpoints use
the timestamped scheme unless you tick the option.

To migrate a receiver:

1. Deploy the verification above. While you are at it, check how you read quantities:
   in work-order payloads `planned_qty` and `produced_qty` are now JSON numbers
   (`100.0`), not decimal strings (`"100.00"`). Accept both if your parser is strict.
2. Untick **Legacy signature** on the endpoint (Admin → Webhooks → Edit), or `PATCH
   /api/v1/webhooks/{id}` with `"legacy_signature": false`.
3. Click **Send test** and check the delivery log. The *Signature* column shows `v1` and
   *Signed at* shows the timestamp that was sent.

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
