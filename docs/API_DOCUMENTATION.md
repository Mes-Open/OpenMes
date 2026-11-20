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
  - [Material Lots and Genealogy](#material-lots-and-genealogy)
  - [Issues](#issues)
  - [Issue Types](#issue-types)
  - [CSV Import](#csv-import)
  - [Analytics](#analytics)
  - [Reports](#reports)
  - [OEE and Downtimes](#oee-and-downtimes)
  - [Shift Handover](#shift-handover)
  - [Audit Logs](#audit-logs)
  - [Event Logs](#event-logs)
- [ERP Integration API](#erp-integration-api)
  - [API Keys and Scopes](#api-keys-and-scopes)
  - [Key Introspection](#key-introspection)
  - [Work Orders (ERP)](#work-orders-erp)
  - [Change Requests (ERP)](#change-requests-erp)
  - [Master Data](#master-data)
  - [Master Data (read)](#master-data-read)
  - [Warehouse Stock](#warehouse-stock)
  - [Stock Documents](#stock-documents)
  - [Webhooks (ERP)](#webhooks-erp)
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
3. Pick the token's **Access** — *Full API access*, *Read-only* or *Machine gateway (OPC UA)* — then create it and copy it

A **read-only** token is refused (`403`, `"This token is read-only."`) on every request
other than `GET`, `HEAD` and `OPTIONS`, on core and module routes alike. Every token is also
limited to what its creator's role allows. `GET /api/auth/me` reports the calling token's
access as `token.scope` (`full`, `read-only` or `machine-gateway`; `null` for a browser session).

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
    "data": {
        "id": 1,
        "username": "admin",
        "email": "admin@example.com",
        "name": "Administrator",
        "accessible_tabs": ["orders", "admin"]
    },
    "token": { "scope": "read-only", "abilities": ["read"] },
    "plant": { "timezone": "Europe/Warsaw", "utc_offset": "+01:00" }
}
```

- `token` is `null` for session (cookie) authentication.
- `plant.timezone` is the zone set in Settings → System. Timestamps in the API
  (`started_at`, `due_date`, `created_at`, …) are UTC instants; convert them to this zone to
  show plant time. Calendar dates (`record_date`, `expiry_date`, …) are plain `YYYY-MM-DD`
  plant days and need no conversion. `utc_offset` is the offset at the moment of the
  request, so it changes with daylight saving; use `timezone` for conversions.

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

Returns the work orders the caller may see (Admins and Supervisors: all; Operators: orders on
their assigned lines that are open for production), ordered by priority score, priority,
due date and creation time.

**Default response.** Without `page`, every matching order comes back in one `data` array
with no `meta`, and each order includes `line`, `product_type` and `batches` with their
`steps`. This is the behaviour existing clients rely on and it does not change. On a large
plant it is a big response (megabytes): use `page`, `include` and `fields=summary` to keep
it small.

Query parameters (all optional, combinable):

| Parameter | Type | Description |
|---|---|---|
| `status` | string or list | Exact status (`PENDING`, `ACCEPTED`, `IN_PROGRESS`, `BLOCKED`, `PAUSED`, `CHANGE_HOLD`, `DONE`, `REJECTED`, `CANCELLED`). Repeat as `status[]=PENDING&status[]=PAUSED` to match any of several. An unknown status matches nothing. |
| `line_id` | integer | Orders on this line. |
| `search` | string, max 255 | Case-insensitive substring match on `order_no`, `customer_order_no`, `description`, and the product type's `name` or `code`. The term is matched literally: `%`, `_` and `\` are not wildcards. |
| `due_from` | date, exactly `YYYY-MM-DD` | Orders due on or after this day. Orders with no due date are excluded. Other formats (`10.03.2026`, times, relative dates) are a `422`. |
| `due_to` | date, exactly `YYYY-MM-DD` | Orders due on or before this day (the whole day counts). Must not be before `due_from`. |
| `include` | comma list or list | Relations to embed, from `line`, `product_type`, `batches`, `batches.steps`. Default: `line,product_type,batches.steps`. `batches` alone embeds the batches without their steps. Any other value is a `422`. |
| `fields` | `full` or `summary` | `full` (default): every work-order column. `summary`: only `id`, `order_no`, `customer_order_no`, `status`, `priority`, `planned_qty`, `produced_qty`, `line_id`, `product_type_id`, `due_date`, `planned_start_at`, `planned_end_at`, `completed_at`, `description`, `created_at`, `updated_at`, plus the relations `include` selects. The large JSON columns (`process_snapshot`, `extra_data`, `custom_fields`) are not read from the database at all. Any other value is a `422`. |
| `page` | integer, 1–1000000 | Turns pagination on and selects the page. The response then carries `meta`. |
| `per_page` | integer, 1–100 | Page size when `page` is given (default 25). **Ignored without `page`**: older clients send `per_page` alone and expect the full, unpaginated list. |

Default response (`GET /api/v1/work-orders`), abbreviated:
```json
{
    "data": [
        {
            "id": 42,
            "order_no": "WO-2025-0042",
            "customer_order_no": "PO-7781",
            "status": "IN_PROGRESS",
            "planned_qty": "100.00",
            "produced_qty": "65.00",
            "priority": 2,
            "due_date": "2025-04-10T00:00:00.000000Z",
            "description": "Wooden Chair Model A",
            "line": { "id": 1, "name": "Line A", "code": "LA" },
            "product_type": { "id": 3, "name": "Wooden Chair", "code": "WCH" },
            "batches": [
                { "id": 7, "batch_number": 1, "status": "IN_PROGRESS", "steps": [ { "id": 31, "step_number": 1, "status": "DONE" } ] }
            ]
        }
    ]
}
```

Lean, paginated search (`GET /api/v1/work-orders?search=chair&status=IN_PROGRESS&fields=summary&include=line,product_type&page=1&per_page=20`), relations abbreviated:
```json
{
    "data": [
        {
            "id": 42,
            "order_no": "WO-2025-0042",
            "customer_order_no": "PO-7781",
            "status": "IN_PROGRESS",
            "priority": 2,
            "planned_qty": "100.00",
            "produced_qty": "65.00",
            "line_id": 1,
            "product_type_id": 3,
            "due_date": "2025-04-10T00:00:00.000000Z",
            "planned_start_at": "2025-04-07T06:00:00.000000Z",
            "planned_end_at": "2025-04-09T14:00:00.000000Z",
            "completed_at": null,
            "description": "Wooden Chair Model A",
            "created_at": "2025-04-01T08:00:00.000000Z",
            "updated_at": "2025-04-02T14:30:00.000000Z",
            "line": { "id": 1, "name": "Line A", "code": "LA" },
            "product_type": { "id": 3, "name": "Wooden Chair", "code": "WCH" }
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 20,
        "total": 1,
        "last_page": 1
    }
}
```

A page past the last one returns an empty `data` array with the same `meta`. Invalid
parameters (`page=0`, `per_page=500` with `page`, a date not in `YYYY-MM-DD` form, `due_to` before
`due_from`, an unknown `include`, a non-numeric `line_id`, a `search` longer than 255
characters) return `422` with the usual `errors` object.

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

### Material Lots and Genealogy

Material lots (ISA-95 material lots: inbound deliveries and lots produced in
house) with their genealogy. Requires a Sanctum token; any role may read.

#### List / Look Up Lots

```http
GET /api/v1/material-lots?lot_number=<our lot number>
GET /api/v1/material-lots?supplier_lot_no=<part of the supplier's lot number>
GET /api/v1/material-lots?material_id=<id>&status=released&available_only=1
GET /api/v1/material-lots?expiring_within_days=30&per_page=100
Authorization: Bearer <token>
```

`lot_number` is an exact match on the plant's own lot number;
`supplier_lot_no` is a substring match. All filters combine. Newest received
first, `per_page` 1–100 (default 30), with `meta.current_page`, `per_page`,
`total` and `last_page`.

```http
GET /api/v1/material-lots/{id}                       # the lot with material, inspection, sublots
GET /api/v1/material-lots/{id}/genealogy/forward     # batch steps / work orders that consumed it, plus a recall view
GET /api/v1/material-lots/{id}/genealogy/backward    # inspection, supplier and the upstream lots it was made from
```

#### Where-Used (unit level)

```http
GET /api/v1/material-lots/{id}/where-used?max_depth=10&limit=1000&include_removed=0
Authorization: Bearer <token>
```

The containment answer for a lot in one call: every serialised unit the lot
was scanned into as a component, and - walking up the component bindings -
every unit that contains one of those (a pinion inside a repair kit inside a
carton), with each unit's work order, batch, pallet and status, and a count per
work order.

| Parameter | Default | Meaning |
|-----------|---------|---------|
| `max_depth` | 10 (0–20) | How many levels of containing units to walk up. `0` returns the directly bound units only. |
| `limit` | 1000 (1–5000) | Most units returned in total (direct and containing). |
| `include_removed` | `false` | `0`, `1`, `true` or `false`. Also follow bindings that were taken out again (`unbound_at` set). By default only parts still installed count. |

`precision` says how exact the returned answer is:

- `unit` - the lot has component bindings (still installed ones; removed ones
  too with `include_removed=1`); `direct_units` and `parent_units` are exactly
  the affected units. `has_unit_bindings` is `true`.
- `batch` - no such binding, only batch-step consumption was recorded.
  `direct_units` is empty; `batch_level` lists the consuming batches and how
  many units each produced (`candidate_units`). Every candidate *may* contain
  the lot - it is an upper bound, not a list of affected units.
- `none` - neither bindings nor consumption are recorded.

When every binding of the lot was taken out again and `include_removed` is
off, `precision` is `batch` or `none`, and `totals.removed_bindings` (always
reported) says how many there are - ask again with `include_removed=1` to see
those units.

`batch_level` is always returned, also next to unit bindings as a cross-check
(`bound_units` against `candidate_units` per batch shows how complete the
scanning was). It covers the lot's own consumption only; for multi-stage chains
through lots produced from it use `genealogy/forward`.

A unit is matched when its binding resolved to the lot, or when it was scanned
by the lot number before the lot existed (`bindings[].match` is `material_lot`
or `identifier`; the identifier is compared ignoring case and spaces, as the
station scan is). `top_level` marks the outermost units - installed in nothing
else - which is what left the plant. Cycles in the binding data are walked
once. When `limit` or `max_depth` cut the walk short, `truncated` is `true` and
`truncated_by` names which (`limit`, `depth`); `totals.direct_units` still
counts every directly bound unit, while `work_orders` (sorted by order
number) and the other totals cover the returned units only. A unit whose
containing unit was cut off has `top_level: null` (unknown) and an empty
`contained_in`; it is left out of `top_level_units` and counted in
`totals.top_level_unknown_units` - with `max_depth=0` that is every direct
unit that sits inside another.

```json
{
    "data": {
        "lot": { "id": 42, "lot_number": "PIN-2026-001", "supplier_lot_no": "SUP-881", "status": "consumed",
                 "material": { "id": 7, "code": "PIN-12", "name": "Pinion 12T" } },
        "precision": "unit",
        "has_unit_bindings": true,
        "params": { "max_depth": 10, "limit": 1000, "include_removed": false },
        "truncated": false,
        "truncated_by": [],
        "totals": {
            "direct_units": 2, "direct_units_returned": 2, "parent_units": 1, "top_level_units": 2,
            "top_level_unknown_units": 0, "removed_bindings": 0, "work_orders": 2, "by_status": { "completed": 2, "shipped": 1 }
        },
        "work_orders": [
            { "work_order": { "id": 10, "order_no": "WO-KIT", "status": "COMPLETED", "customer_order_no": "CO-77", "product": "Repair kit" },
              "direct_units": 0, "parent_units": 1, "top_level_units": 1, "by_status": { "shipped": 1 } },
            { "work_order": { "id": 9, "order_no": "WO-PINION", "status": "COMPLETED", "customer_order_no": null, "product": "Pinion" },
              "direct_units": 2, "parent_units": 0, "top_level_units": 1, "by_status": { "completed": 2 } }
        ],
        "direct_units": [
            {
                "id": 101, "serial_no": "PIN-A", "psn": null, "status": "completed",
                "material": { "id": 7, "code": "PIN-12", "name": "Pinion 12T" },
                "work_order": { "id": 9, "order_no": "WO-PINION", "status": "COMPLETED", "customer_order_no": null, "product": "Pinion" },
                "batch": { "id": 31, "batch_number": 1, "lot_number": "B-2026-031" },
                "pallet": null,
                "produced_at": "2026-11-02T08:14:00+00:00", "shipped_at": null,
                "depth": 0, "top_level": false,
                "contained_in": [ { "id": 201, "serial_no": "KIT-1", "psn": null } ],
                "bindings": [ { "identifier": "PIN-2026-001", "match": "material_lot", "quantity": 1, "installed": true,
                                "bound_at": "2026-11-02T08:10:00+00:00", "unbound_at": null, "unbind_reason": null } ],
                "contains": []
            }
        ],
        "parent_units": [
            {
                "id": 201, "serial_no": "KIT-1", "psn": null, "status": "shipped",
                "material": null,
                "work_order": { "id": 10, "order_no": "WO-KIT", "status": "COMPLETED", "customer_order_no": "CO-77", "product": "Repair kit" },
                "batch": null,
                "pallet": { "id": 5, "pallet_no": "PAL-000777", "status": "shipped" },
                "produced_at": null, "shipped_at": "2026-11-05T13:00:00+00:00",
                "depth": 1, "top_level": true,
                "contained_in": [],
                "contains": [ { "id": 101, "serial_no": "PIN-A", "psn": null } ]
            }
        ],
        "batch_level": {
            "work_orders": [
                { "work_order": { "id": 9, "order_no": "WO-PINION", "status": "COMPLETED", "customer_order_no": null, "product": "Pinion" },
                  "quantity_consumed": 2, "candidate_units": 2,
                  "batches": [ { "id": 31, "batch_number": 1, "lot_number": "B-2026-031",
                                 "quantity_consumed": 2, "candidate_units": 2, "bound_units": 2 } ] }
            ],
            "totals": { "work_orders": 1, "batches": 1, "candidate_units": 2, "quantity_consumed": 2 }
        }
    }
}
```

The example shows one of the two direct units. `404` when the lot does not
exist or belongs to another tenant, `422` for an out-of-range parameter.

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

Shared query parameters (each endpoint uses the ones listed for it). Invalid values answer *422*:

| Parameter | Format | Meaning |
|---|---|---|
| `line_id` | integer | Restrict to one line. An unknown line, or one of another tenant, is a 422. |
| `days` | integer, 1–3650 | Look-back window ending now (default 30). |
| `start_date`, `end_date` | `YYYY-MM-DD`, both inclusive | Window for `production-by-line` (default: the last 7 days). |

#### Overview

Current counts. Parameter: `line_id`.

```http
GET /api/v1/analytics/overview
Authorization: Bearer <token>
```

Response:
```json
{
    "data": {
        "total_work_orders": 152,
        "pending_work_orders": 30,
        "in_progress_work_orders": 24,
        "active_work_orders": 54,
        "completed_work_orders": 98,
        "done_today_work_orders": 6,
        "blocked_work_orders": 2,
        "total_batches": 310,
        "active_batches": 12,
        "open_issues": 7,
        "blocking_issues": 2,
        "critical_issues": 1,
        "active_lines": 5
    }
}
```

- `in_progress_work_orders` includes accepted orders.
- `done_today_work_orders` counts orders **completed** since midnight in the plant time zone.

#### Production by Line

Work orders **created** in the window, per line: counts by status, plus planned and produced
quantity. Parameters: `start_date`, `end_date`.

```http
GET /api/v1/analytics/production-by-line?start_date=2026-03-01&end_date=2026-03-07
Authorization: Bearer <token>
```

#### Cycle Time

Start-to-finish time of batches completed in the last `days`. Parameters: `line_id`, `days`.

```http
GET /api/v1/analytics/cycle-time
Authorization: Bearer <token>
```

#### Throughput

Units produced per plant day over the last `days` plant days (today included, so `days=7` is at most seven rows), from the output ledger: every change to a work
order's `produced_qty`, dated when it happened. A correction that lowers a count shows as a
negative amount on its day. Parameters: `line_id`, `days`.

```http
GET /api/v1/analytics/throughput?days=7
Authorization: Bearer <token>
```

Response:
```json
{
    "data": {
        "daily_production": [
            { "date": "2026-03-10", "total_produced": "50.00" },
            { "date": "2026-03-11", "total_produced": "-2.00" }
        ],
        "average_daily_throughput": 24,
        "period_start": "2026-03-06",
        "period_end": "2026-03-12"
    }
}
```

#### Issue Statistics

Issues reported in the last `days`, by type and by status, plus average resolution time.
Parameters: `line_id`, `days`.

```http
GET /api/v1/analytics/issue-stats
Authorization: Bearer <token>
```

#### Step Performance

Average duration per process step, over steps completed in the last `days`. Parameters:
`line_id`, `days`.

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

### OEE and Downtimes

Open to every authenticated user (operators report downtime from the shop floor).

Both list endpoints below keep their original response when called without
`page` / `per_page`: a plain `data` array, newest first, capped at **100** OEE
records or **50** downtimes, with no `meta` and no way to reach older rows.
Send `page` and/or `per_page` to switch to a paginated response that carries a
`meta` block; this is the way to read a full date range without silent truncation.

| Parameter | Type | Description |
|---|---|---|
| `page` | integer 1–100000 | Page to return. Turns pagination on. |
| `per_page` | integer 1–100 | Page size. Turns pagination on. Defaults to the legacy cap (100 for OEE, 50 for downtimes) when only `page` is sent. |
| `lean` | `1`/`0`/`true`/`false` | Embed only the identifying columns of each relation (see each endpoint) instead of the full records. Works with or without pagination. |

Out-of-range values (`per_page=0`, `per_page=101`, `page=0`, `page=100001`, …) return `422`.
Ids are checked against the caller's tenant only: a line or workstation that
belongs to another tenant is answered exactly like an id that does not exist.
Rows are ordered newest first; rows that tie on the date are ordered by `id`
descending, so pages never overlap or skip a row.

#### List OEE Records

```http
GET /api/v1/oee
Authorization: Bearer <token>
```

Query parameters (all optional and combinable):

| Parameter | Type | Description |
|---|---|---|
| `line_id` | integer | Filter by line (must exist in your tenant, otherwise `422`) |
| `workstation_id` | integer | Filter by workstation (must exist in your tenant, otherwise `422`) |
| `date_from` | date | Records with `record_date` on or after this day |
| `date_to` | date | Records with `record_date` on or before this day (inclusive) |

`record_date` is a calendar date, compared as such; the plant timezone does not apply.
| `page`, `per_page`, `lean` | | See above |

With `lean=1`, `line` carries `id`, `name`, `code` and `shift` carries `id`, `name`.

Paginated response (`GET /api/v1/oee?date_from=2026-06-24&date_to=2026-06-30&per_page=2&lean=1`):
```json
{
    "data": [
        {
            "id": 812,
            "line_id": 1,
            "workstation_id": null,
            "shift_id": 2,
            "record_date": "2026-06-30",
            "planned_minutes": 480,
            "operating_minutes": 420,
            "downtime_minutes": 60,
            "availability_pct": "87.50",
            "performance_pct": "91.20",
            "quality_pct": "97.40",
            "oee_pct": "77.72",
            "line": { "id": 1, "name": "Assembly 1", "code": "L1" },
            "shift": { "id": 2, "name": "Morning" }
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 2,
        "total": 21,
        "last_page": 11
    }
}
```

(Record fields abbreviated.) Without `page`/`per_page` the same request returns
`{"data": [...]}` with up to 100 records and no `meta`.

#### OEE for One Line

```http
GET /api/v1/oee/{line}?days=7
Authorization: Bearer <token>
```

Records for the line over the last `days` days (today included), newest first.

#### List Downtimes

```http
GET /api/v1/downtimes
Authorization: Bearer <token>
```

Query parameters (all optional and combinable):

| Parameter | Type | Description |
|---|---|---|
| `line_id` | integer | Filter by line (an unknown id returns an empty list) |
| `workstation_id` | integer | Filter by workstation (must exist in your tenant, otherwise `422`) |
| `date` | date | Downtimes that started on this day (same as `date_from` = `date_to` = this day) |
| `date_from` | date | Downtimes that started on or after this day |
| `date_to` | date | Downtimes that started on or before this day (inclusive) |
| `active` | `1`/`0`/`true`/`false` | `1`: only open downtimes (no `ended_at`); `0`: only finished ones |
| `page`, `per_page`, `lean` | | See above |

Date filters match on the **plant-local** day the downtime **started** (the
timezone set under *Settings*), not the UTC day: with the plant in Europe/Warsaw,
a stop at 00:30 local time on 15 January (`started_at` `2026-01-14T23:30:00Z` in
the response) is found by `date=2026-01-15`. Only the date part of the
parameter is used. A stop that began before `date_from` and ran into the range
is not included.

With `lean=1`, `reason` carries `id`, `code`, `name`, `kind`; `line` and
`workstation` carry `id`, `name`, `code`; `reported_by` carries `id`, `name`,
`username`.

Paginated response (`GET /api/v1/downtimes?line_id=1&date_from=2026-06-24&date_to=2026-06-30&page=1&per_page=1&lean=1`):
```json
{
    "data": [
        {
            "id": 311,
            "line_id": 1,
            "workstation_id": 4,
            "downtime_reason_id": 3,
            "started_at": "2026-06-30T08:12:00.000000Z",
            "ended_at": "2026-06-30T08:40:00.000000Z",
            "duration_minutes": 28,
            "notes": "Jammed feeder",
            "reason": { "id": 3, "code": "jam", "name": "Material jam", "kind": "unplanned" },
            "line": { "id": 1, "name": "Assembly 1", "code": "L1" },
            "workstation": { "id": 4, "name": "Press 2", "code": "WS-P2" },
            "reported_by": { "id": 5, "name": "Alex Smith", "username": "operator1" }
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 1,
        "total": 14,
        "last_page": 14
    }
}
```

(Record fields abbreviated.) Without `page`/`per_page` the same request returns
`{"data": [...]}` with up to 50 downtimes and no `meta`.

#### Start / Stop a Downtime

```http
POST /api/v1/downtimes
PATCH /api/v1/downtimes/{downtime}
Authorization: Bearer <token>
```

`POST` takes `line_id`, `downtime_reason_id` (required), `workstation_id`, `notes`.
`PATCH` stops an open downtime; stopping one that already ended returns `422`.
Active reasons are listed by `GET /api/v1/downtime-reasons`.

---

### Shift Handover

Supervisor and Admin roles required. REST twin of *Supervisor → Shift Handover*. When the
calling account belongs to a tenant, handovers are limited to that tenant: another tenant's
handover is never listed and returns `404`. Accounts without a tenant (single-tenant installs)
see all handovers.

#### Current Balance and Recent Handovers

```http
GET /api/v1/shift-handover?line_id=3
Authorization: Bearer <token>
```

`line_id` (optional) scopes the live `balance` to one line; a missing or non-numeric value means
all lines. It does **not** filter `recent`: as on the web page, `recent` is always the 20 newest
handovers across all lines, newest first (by `confirmed_at`). For older handovers or filtering,
use [the history endpoint](#handover-history).

```json
{
  "data": {
    "lines": [{ "id": 3, "name": "Assembly 1", "code": "A1" }],
    "selected_line_id": 3,
    "balance": { "line_id": 3, "shift_id": 1, "window": { "...": "..." }, "produced_qty": 120, "...": "..." },
    "recent": [
      {
        "id": 42,
        "business_date": "2026-11-10",
        "shift_start": "2026-11-10 06:00",
        "line_name": "Assembly 1",
        "produced_qty": 480,
        "good_qty": 466,
        "packed_qty": 400,
        "shipped_qty": 200,
        "confirmed_by": "Sam Supervisor",
        "confirmed_at": "2026-11-10 14:05",
        "shift_end": "2026-11-10 14:00",
        "line_id": 3,
        "shift_id": 1,
        "shift_name": "Morning",
        "scrap_qty": 14,
        "wip_open_pallets_qty": 24,
        "wip_unpacked_qty": 42,
        "discrepancies": {
          "unpacked": { "label": "Good output not yet packed", "value": 42, "severity": "warning" },
          "awaiting_shipment": { "label": "Packed but not yet shipped", "value": 200, "severity": "info" }
        },
        "breakdown": {
          "wip_total_qty": 66,
          "open_pallets_count": 1,
          "open_pallets": [],
          "shift": { "name": "Morning", "code": "I", "start": "06:00", "end": "14:00" }
        },
        "notes": "Conveyor jammed twice; maintenance informed.",
        "confirmed_by_id": 7
      }
    ]
  }
}
```

Handover fields (the same in `recent`, history and the single-handover response):

- Timestamps (`shift_start`, `shift_end`, `confirmed_at`) are `YYYY-MM-DD HH:MM` in the
  application timezone.
- `discrepancies` and `breakdown` are the snapshot captured when the shift was closed, and are
  always JSON objects (`{}` when empty). `discrepancies` is keyed by discrepancy (`unpacked`,
  `awaiting_shipment`, `over_packed`), each with `label`, `value` and `severity`.
- `shift_name` is the shift name captured in the snapshot, so renaming a shift later does not
  change history; older handovers without one show the shift's current name.
- `line_name`, `shift_name` and `confirmed_by` resolve even if the line, shift or user has since
  been deleted.

#### Handover History

```http
GET /api/v1/shift-handover/history?line_id=3&business_date_from=2026-11-01&business_date_to=2026-11-07&per_page=50
Authorization: Bearer <token>
```

Always paginated, newest first (by `confirmed_at`). Does not compute the live balance. Query
parameters (all optional):

| Parameter | Description |
|---|---|
| `line_id` | Only handovers of this line (positive integer). |
| `business_date_from`, `business_date_to` | Business date range, inclusive, `YYYY-MM-DD`. `business_date_to` must not be before `business_date_from`. |
| `per_page` | Page size, 1–100 (default 25). |
| `page` | Page number (default 1). |

Invalid parameters return `422`.

```json
{
  "data": [ { "id": 42, "business_date": "2026-11-10", "...": "same fields as a recent item" } ],
  "meta": { "current_page": 1, "per_page": 25, "total": 57, "last_page": 3 }
}
```

#### Get One Handover

```http
GET /api/v1/shift-handover/42
Authorization: Bearer <token>
```

Returns `{ "data": { ... } }` with the same fields as one `recent` item. `404` when the handover
does not exist or is outside the caller's tenant.

#### Close the Shift

```http
POST /api/v1/shift-handover
Authorization: Bearer <token>
Content-Type: application/json

{ "line_id": 3, "notes": "End of A-shift." }
```

Both fields are optional (`notes` up to 2000 characters). The server recomputes the balance and
stores it as the handover snapshot; returns `201` with the stored handover record (its database
columns plus `line` and `confirmed_by` objects, a different shape from the items above). Fetch
`GET /api/v1/shift-handover/{id}` for the handover fields described above.

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
| `erp:orders:import` | Import and cancel work orders |
| `erp:production:read` | Read production completions, single work orders and the change-request feed |
| `erp:quality:read` | Read quality / non-conformance reports |
| `erp:masterdata:write` | Import products, materials, material lots, recipes and routings (process templates) |
| `erp:masterdata:read` | Read lines, warehouses and product types (codes, names, activity) |
| `erp:stock:read` | Read warehouse balances and the stock-document backlog |
| `erp:stock:write` | Sync warehouse balances, acknowledge stock documents |
| `erp:shipments:write` | Send shipments (loads) with the pallets / cartons each must carry |
| `erp:shipments:read` | Read shipment load results — loaded, missing, unexpected |
| `erp:webhooks:manage` | Register and manage the key's own outgoing webhooks |

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
POST /api/v1/erp/work-orders/{id}/cancel     # scope: erp:orders:import
GET  /api/v1/erp/work-orders/{id}            # scope: erp:production:read
GET  /api/v1/erp/production/completions      # scope: erp:production:read
GET  /api/v1/erp/change-requests             # scope: erp:production:read
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
            "priority": 10,
            "unit_price": 12.5,
            "price_currency": "EUR",
            "external_system": "pantheon",
            "external_ref": "SO-4711/3",
            "customer_name": "Acme GmbH",
            "extra_data": { "color": "red", "sizes": ["S", "M"] }
        }
    ]
}
```

Fields per order (only the first four are required):

| Field | Rules | Notes |
|-------|-------|-------|
| `order_no` | string, ≤ 100 | Matches an existing order on re-import |
| `line_code` | string, ≤ 100 | Must resolve to a line (per-row error otherwise) |
| `product_type_code` | string, ≤ 100 | Must resolve to a product with an active process template |
| `planned_qty` | number, 0.01 – 99 999 999 | |
| `customer_order_no` | string, ≤ 100 | |
| `unit_price` | number, ≥ 0 | |
| `price_currency` | ISO 4217 code from the supported list (`PLN`, `EUR`, `USD`, `GBP`, `CHF`, `CZK`, …) | Case-insensitive on input, stored upper-case. Omitted = the installation currency |
| `external_system` | string, ≤ 50 | The system the order comes from, e.g. `pantheon`, `sap` |
| `external_ref` | string, ≤ 100 | The order's id in that system, e.g. a sales order line id |
| `customer_name` | string, ≤ 255 | Free text; not matched to OpenMES customer records |
| `extra_data` | object, ≤ 100 keys, ≤ 16 KB encoded | Each value a string, number, boolean or null, or a list of those — no deeper nesting |
| `priority` | integer, 0 – 100 | |
| `due_date` | date | |
| `description` | string, ≤ 2000 | |

On re-import (`update_or_create`) a field that is **absent or `null` keeps its stored
value**. `extra_data` is merged key by key over what is stored, so keys set elsewhere
survive. A wrong currency, an `extra_data` that is a list, nested deeper than a list of
values, or larger than 16 KB, or an over-long string is rejected for the whole request
with `422` (`errors["orders.0.price_currency"]`, …). Orders that are already `DONE` or
`CANCELLED` are skipped, as before. `(external_system, external_ref)` is indexed but not
unique: orders are still matched by `order_no`.

`GET /api/v1/erp/work-orders/{id}` and each item of `GET /api/v1/erp/production/completions`
return the order with its source:

```json
{
    "data": {
        "order_no": "WO-2026-001",
        "customer_order_no": "PO-77",
        "status": "DONE",
        "planned_qty": 500,
        "produced_qty": 500,
        "unit_price": 12.5,
        "price_currency": "EUR",
        "external_system": "pantheon",
        "external_ref": "SO-4711/3",
        "customer_name": "Acme GmbH",
        "extra_data": { "color": "red", "sizes": ["S", "M"] },
        "counting_source": "operator",
        "line_code": "L1",
        "product_type_code": "BREAD-01",
        "completed_at": "2026-08-03T18:37:13+00:00",
        "updated_at": "2026-08-03T18:37:13+00:00"
    }
}
```

`price_currency` is the currency `unit_price` is in: the one the ERP sent, else the
installation currency (*Settings → System*); `null` when the order has no price. The
source fields and `extra_data` are `null` for orders that did not come with them.

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

Optional per order: `customer_order_no`, `unit_price`, `priority`, `due_date`,
`description`, and `change_reason` — the text a manager reads when the row turns into
a change request (see below).

#### What an update and a cancel do, by order status

The ERP owns an order until production starts. After that it can only **ask**: the
update or cancellation becomes a change request that a manager (a user with the
*approve work order changes* permission) approves or rejects in **Production →
Change requests**. The ERP reads the decision back from the API.

| Order status | `update_or_create` import | `POST …/cancel` |
|---|---|---|
| `PENDING`, `ACCEPTED` | updated at once → `updated` | cancelled at once → `200`, `cancelled` |
| `IN_PROGRESS`, `PAUSED`, `BLOCKED`, `CHANGE_HOLD` | change request with the fields that differ → `change_requested` (no difference → `skipped`) | change request of type `CANCEL` → `202`, `change_requested` |
| `DONE`, `CANCELLED`, `REJECTED` | `skipped` | `409` with the current `status` |

For an order in production:

- Only `line_code`, `planned_qty`, `due_date` and `description` can be changed through
  a request. A different `product_type_code`, `priority`, `customer_order_no` or
  `unit_price` makes the **row** an error (`field` names it); nothing is requested.
- Optional fields are compared only when the row carries them — an absent key means
  "no opinion", not "clear it".
- While the ERP's request for that order is still open (submitted or approved, not
  yet applied), a new import **revises the same request** instead of opening a
  second one. A revised request that was already approved goes back to *Submitted*:
  the manager approved different values. Re-sending the **same** proposal (a periodic
  sync) leaves the request and its approval untouched. A row identical to the order
  withdraws the open request (`CANCELLED`) and reports `skipped`.
- Repeating a cancel while the ERP's cancellation request is open returns the same
  request.
- Applying a change to a running order still requires the manager to pause it first
  (existing change-control rule). A cancellation cannot be applied while the order is
  in `CHANGE_HOLD`; the hold has to be released first, as in the UI.

`skip_existing` and `error_on_duplicate` are unchanged. The CSV importer and
Admin → Import are not ERP calls and keep updating orders directly.

#### Import report

Next to the counters every import returns `results`, one entry per row that was not
an error (error rows are in `errors`):

```json
{
    "data": {
        "imported": 1,
        "updated": 1,
        "skipped": 0,
        "change_requested": 1,
        "errors": [],
        "results": [
            { "row": 1, "order_no": "WO-2026-001", "id": 812, "action": "created" },
            { "row": 2, "order_no": "WO-2026-002", "id": 790, "action": "updated" },
            { "row": 3, "order_no": "WO-2026-003", "id": 774, "action": "change_requested", "change_request_id": 55 }
        ]
    }
}
```

`action`: `created` | `updated` | `skipped` | `change_requested`. `change_request_id`
is present whenever the row created, revised or withdrew a request.

#### Reading one order back

```http
GET /api/v1/erp/work-orders/812
GET /api/v1/erp/work-orders/WO%2F2026%2F001?by=order_no
```

`by` is `id` (default) or `order_no` — the order number the ERP knows. URL-encode
`/`, `?` and `#` in the number (`/` → `%2F`). An unknown number, a non-numeric id with
`by=id`, or an order of another tenant is `404`; an unknown `by` is `422`. The answer
includes `id` and `change_requests` — every request raised against the order, in the
shape described under [Change Requests (ERP)](#change-requests-erp).

#### Cancelling an order

```http
POST /api/v1/erp/work-orders/WO%2F2026%2F001/cancel?by=order_no
Content-Type: application/json

{ "reason": "Customer withdrew the order" }
```

`reason` is optional (max 1000 characters); for a pending order it is kept on the
order, for a running one it is the change request's reason.

```json
// 200 — not started, cancelled at once
{ "data": { "result": "cancelled", "work_order": { "id": 812, "order_no": "WO/2026/001", "status": "CANCELLED" } } }

// 202 — in production, waiting for a manager
{ "data": { "result": "change_requested", "change_request_id": 56,
            "change_request": { "id": 56, "type": "CANCEL", "status": "SUBMITTED", "…": "…" },
            "work_order": { "id": 812, "order_no": "WO/2026/001", "status": "IN_PROGRESS" } } }

// 409 — already finished
{ "message": "Work order is already DONE.", "status": "DONE", "work_order": { "…": "…" } }
```

### Change Requests (ERP)

```http
GET /api/v1/erp/change-requests?since=2026-11-01T00:00:00Z   # scope: erp:production:read
```

How the ERP learns what was decided. Lists every change request of the key's tenant
— the ones the ERP raised and the ones raised in OpenMES — cursor-paginated like the
other exports (`limit` 1–500, `cursor`, `meta.next_cursor`). Filters: `since`
(on `updated_at`, so every decision moves a request back into the window), `status`
(`DRAFT`, `SUBMITTED`, `APPROVED`, `REJECTED`, `APPLIED`, `CANCELLED`) and `type`
(`CHANGE`, `CANCEL`).

```json
{
    "data": [
        {
            "id": 55,
            "code": "CR/2026/0012",
            "type": "CHANGE",
            "status": "REJECTED",
            "work_order_id": 774,
            "order_no": "WO-2026-003",
            "title": "ERP change: planned_qty",
            "proposed": { "planned_qty": 150 },
            "previous_values": null,
            "reason": "Customer upsized",
            "rejection_reason": "Material shortage",
            "origin": "api_key",
            "submitted_at": "2026-11-06T08:00:00+00:00",
            "approved_at": null,
            "rejected_at": "2026-11-06T09:12:00+00:00",
            "applied_at": null,
            "decided_at": "2026-11-06T09:12:00+00:00",
            "created_at": "2026-11-06T08:00:00+00:00",
            "updated_at": "2026-11-06T09:12:00+00:00"
        }
    ],
    "meta": { "next_cursor": null, "has_more": false, "count": 1, "per_page": 100 }
}
```

- `proposed` / `previous_values` use ERP field names (`line_code`, not an internal line
  id). `previous_values` is filled when the request is applied; for a `CANCEL` it holds
  the order's `status` before cancellation.
- `decided_at` is when the request was rejected or approved (for a withdrawn request,
  when it was withdrawn); `applied_at` is when the change reached the order.
- `origin` is `api_key` or `user`; no user names or e-mail addresses are exposed.

Send `Accept: application/json` on every call so validation errors come back as a
JSON `422` instead of a redirect.

#### Settling a work order: `?include=consumption,scrap,costs`

`GET /work-orders/{id}` and `GET /production/completions` accept an optional
`include` — a comma-separated subset of `consumption`, `scrap`, `costs`. Each value
adds one section to every work-order object, so an ERP can close a production order
(materials with lots, scrap, costs) from a single call with the API key alone.

- Without `include` the response is exactly the base shape below — nothing changes
  for existing integrations.
- Any other value (`?include=foo`, an empty item) → `422` on `include`.
- On `production/completions`, `limit` is capped at **100** (default **50**) when
  `include` is present; without it the cap stays 500 (default 100). Sections are
  eager-loaded per page, so a page costs the same number of queries however many
  orders it holds.

```http
GET /api/v1/erp/work-orders/42?include=consumption,scrap,costs
X-Api-Key: omk_xxxxxxxxxxxxxxxxxxxxxxxx
Accept: application/json
```

```json
{
    "data": {
        "order_no": "WO-2026-001",
        "customer_order_no": "PO-77",
        "status": "DONE",
        "planned_qty": 500,
        "produced_qty": 480,
        "unit_price": null,
        "counting_source": "operator",
        "line_code": "L1",
        "product_type_code": "BREAD-01",
        "completed_at": "2026-09-01T14:02:11+00:00",
        "updated_at": "2026-09-01T14:02:11+00:00",
        "consumption": [
            {
                "material_code": "FLOUR-RYE",
                "material_name": "Rye flour",
                "unit_of_measure": "kg",
                "consumed_qty": 241.5,
                "scrap_qty": 3.5,
                "returned_qty": 5,
                "status": "consumed",
                "consumed_at": "2026-09-01T13:55:00+00:00",
                "lots": [
                    { "lot_number": "LOT-2026-0815", "quantity": 250 }
                ]
            }
        ],
        "scrap": {
            "total_qty": 20,
            "entries": [
                {
                    "quantity": 20,
                    "reason": { "code": "BURNT", "name": "Burnt", "category": "machine" },
                    "step": { "batch_number": 1, "step_number": 3, "name": "Baking" },
                    "reported_at": "2026-09-01T12:40:00+00:00",
                    "notes": "Oven 2 overheated"
                }
            ]
        },
        "costs": {
            "currency": "PLN",
            "mixed_currency": false,
            "total_cost": 1393,
            "cost_per_unit": 2.9021,
            "materials": {
                "total": 483,
                "items": [
                    { "material_code": "FLOUR-RYE", "source": "actual", "qty": 241.5,
                      "unit_price": 2, "currency": "PLN", "line_total": 483 }
                ]
            },
            "labor": {
                "total": 870,
                "items": [
                    { "worker_code": "W-0012", "pay_type": "hourly", "basis": 29,
                      "basis_unit": "h", "rate": 30, "currency": "PLN", "line_total": 870 }
                ]
            },
            "additional": {
                "total": 40,
                "items": [
                    { "description": "Courier", "cost_source_code": "TRANSPORT",
                      "cost_source_name": "Transport", "amount": 40, "currency": "PLN" }
                ]
            }
        }
    }
}
```

Where the numbers come from:

| Section | Source |
|---|---|
| `consumption` | The order's material allocations — where consumption is recorded on the work-order page (**Materials → Record consumption**). Only allocations with a consumed or scrapped quantity are listed. `lots` are the lots picked for the allocation; `quantity` is the **picked** quantity (lot tracking is opt-in, so `lots` may be empty). Consumption booked directly against a lot without an allocation (`POST /material-lots/{id}/consume`, genealogy only) is not included. |
| `scrap` | The order's scrap entries (deleted ones excluded). `reason` and `step` are `null` when not set; the reporting person is deliberately not exported. |
| `costs` | The same calculation as the **Production Cost** report. `materials` prices recorded consumption (`source: "actual"`); a material with no recorded consumption is estimated from the BOM × produced quantity (`source: "bom"`). `labor` is priced from logged work time and pay rates (worker code only, no names). `additional` lists manually booked costs. `mixed_currency: true` means some item is in a different currency than `currency` — amounts are summed without FX conversion. OpenMES has no machine-cost model, so no machine cost is reported. |

### Master Data

All five require the `erp:masterdata:write` scope. A full sync calls them in this
order, so every reference already exists when it is needed:

1. `products/import` (and `materials/import`, `material-lots/import`)
2. `process-templates/import` — the routing of each product
3. `boms/import` — recipes attach to the routing imported in step 2
4. `work-orders/import` — an order freezes the newest active routing and its recipe

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

**Default process template.** A work order is accepted only for a product type with
an active process template. When the ERP has no routing to send, set
`create_default_template` so each imported product is orderable at once:

```json
{
    "create_default_template": true,
    "default_step_name": "Production",
    "products": [ { "code": "STOOL-01", "name": "Stool" } ]
}
```

| Field | Type | Default | Meaning |
|---|---|---|---|
| `create_default_template` | boolean | `false` | Give a created or updated product type that has **no** process template (of any state) an active version 1 with a single step. A type that already has a template — active or not — is left untouched. Rows skipped by `strategy` or `only_categories` are not touched either. |
| `default_step_name` | string, max 255 | `"Production"` in the plant language (Settings → language), e.g. `"Produkcja"` | Name of that single step. |

Rows that got a template are listed in `rows`; the key is absent when none did:

```json
{
    "data": {
        "imported": 1, "updated": 0, "skipped": 0, "errors": [],
        "rows": [ { "row": 1, "code": "STOOL-01", "template_created": true } ]
    }
}
```

#### Process Templates (Routings)

```http
POST /api/v1/erp/process-templates/import
```

One row per product: its routing, as the ordered list of operations the ERP keeps.
Each row becomes an OpenMES process template with its steps.

```json
{
    "mode": "new_version",
    "templates": [
        {
            "product_type_code": "CHAIR-01",
            "template_name": "Chair — routing 1",
            "steps": [
                {
                    "step_number": 10,
                    "name": "Cutting",
                    "workstation_code": "WS-CUT",
                    "line_code": "L1",
                    "setup_time_minutes": 15,
                    "run_time_per_unit_minutes": 2.5
                },
                {
                    "step_number": 20,
                    "name": "Assembly",
                    "workstation_code": "WS-ASM",
                    "setup_time_minutes": 5,
                    "run_time_per_unit_minutes": 4,
                    "instruction": "Glue and screw the legs",
                    "requires_confirmation": true
                }
            ]
        }
    ]
}
```

| Field | Type | Required | Meaning |
|---|---|---|---|
| `mode` | `new_version` \| `replace_draft` | no (`new_version`) | See below. |
| `templates` | array, 1–500 | yes | One routing per row. |
| `templates.*.product_type_code` | string, max 50 | yes | Product type `code`. Unknown → row error. |
| `templates.*.template_name` | string, max 255 | no | Defaults to the product type's name. |
| `templates.*.steps` | array, 1–200 | yes | The operations. |
| `steps.*.step_number` | integer ≥ 1 | yes | The ERP operation number. Unique within one routing. |
| `steps.*.name` | string, max 255 | yes | Step name shown to operators. |
| `steps.*.workstation_code` | string, max 50 | no | Workstation `code`. Unknown → row error. |
| `steps.*.line_code` | string, max 50 | no | Narrows the workstation lookup to this line; needs `workstation_code`. Unknown line, or a workstation not on it → row error. |
| `steps.*.setup_time_minutes` | integer ≥ 0 | no | Setup (changeover) time per batch, in minutes. |
| `steps.*.run_time_per_unit_minutes` | number ≥ 0 | no | Run time per piece, in minutes (stored to 2 decimals). |
| `steps.*.instruction` | string, max 10000 | no | Work instruction text. |
| `steps.*.requires_confirmation` | boolean | no (`false`) | Operator must confirm the step. |

**Step numbers.** ERP operation numbers (`10`, `20`, `30`…) only order the steps:
OpenMES stores them as `1, 2, 3…` in ascending order of `step_number`, because a
step's predecessor is the one numbered one lower. A routing listing the same
number twice is rejected with `422`.

**Modes.**

- `new_version` (default) — works exactly like adding a version in *Production →
  Product Types → Process Templates*: the routing becomes version *max + 1*, active,
  and earlier versions are left as they are. Every new work order (from the ERP, CSV
  or the UI) uses the **newest active** version, and so does `boms/import`. The
  recipe (`bom_items`) of the current template is copied onto the new version, each
  line attached to the step with the same number (or to the whole order when that
  step no longer exists); quality checks, photos, media and checklists are not
  copied. Re-sending a routing identical to the newest active version (same steps,
  workstations, times, instructions) changes nothing and is reported as `skipped`,
  so a nightly sync does not stack up versions.
- `replace_draft` — rewrites the newest **inactive** template that is newer than the
  newest active version and that no work order was created from; if there is none,
  creates a new inactive version. Steps are matched
  by number (updated in place, so their media and checklists survive); steps whose
  number is gone are removed. The template stays inactive until a technologist
  activates it in the UI, so this mode alone does not make a product orderable.
  An active template, or one any work order refers to, is never rewritten.

**Orders already released are never changed.** A work order freezes its routing
when it is created (`process_snapshot`); neither mode edits an existing active
version, so orders in progress keep exactly the steps they started with.

A routing with one unknown product type, line or workstation is reported as one
failed row (`field` points at the step, e.g. `steps.1.workstation_code`) and
written not at all — never half-way. Each created or rewritten template is recorded
in the audit log (`process_template.erp_imported`).

**Mapping an ERP routing.** Example for an SAP routing (table `PLPO`); other ERPs
have the same concepts under other names (Comarch "technologia", Business Central
"Routing Lines", Odoo "Operations" of a BoM):

| ERP (SAP `PLPO`) | OpenMES field | Note |
|---|---|---|
| Material of the routing header (`MAPL-MATNR`) | `product_type_code` | |
| Routing text (`PLKO-KTEXT`) | `template_name` | optional |
| Operation number `VORNR` (`0010`, `0020`, `0030`) | `steps.*.step_number` | `10`, `20`, `30` — stored as 1, 2, 3 |
| Operation short text `LTXA1` | `steps.*.name` | |
| Work center `ARBPL` | `steps.*.workstation_code` | create workstations with the same codes |
| Plant / production line of the work center | `steps.*.line_code` | optional |
| Setup time (`VGW01`, *Rüstzeit*) | `steps.*.setup_time_minutes` | convert to whole minutes |
| Machine/labour time per base quantity (`VGW02` / `BMSCH`) | `steps.*.run_time_per_unit_minutes` | convert to minutes **per one piece** |
| Long text of the operation | `steps.*.instruction` | |

Response (`207` — the second routing named a workstation OpenMES does not know):

```json
{
    "data": {
        "imported": 1,
        "updated": 0,
        "skipped": 0,
        "errors": [
            { "row": 2, "field": "steps.1.workstation_code", "message": "Workstation 'WS-PAINT' not found" }
        ],
        "rows": [
            { "row": 1, "code": "CHAIR-01", "version": 2 }
        ]
    }
}
```

`rows` lists the version each created (`new_version`) or rewritten (`replace_draft`)
routing got; skipped rows are not listed.

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
            "work_order_external_system": "pantheon",
            "work_order_external_ref": "SO-4711/3",
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
`?type=` / `?warehouse=` to narrow further. `work_order_external_system` /
`work_order_external_ref` repeat the source reference the work order was imported
with, so the ERP can link the document to its own sales order; both are `null` when
the document has no work order or the order came without one.

#### Acknowledge

```http
POST /api/v1/erp/stock-documents/{id}/ack                         # scope: erp:stock:write
Content-Type: application/json

{ "erp_reference": "RW-2026/00042" }
```

Records the ERP's own document number and stamps `erp_synced_at`, which takes the
document off the `unsynced_only` backlog. `erp_reference` is optional. Unknown id (or
another tenant's document) → `404`.

### Shipments (ERP)

Scan-to-load verification — see [Shipment Loading Verification](shipment-loading.md) for
the dock workflow. Requires the Packaging module.

```http
POST /api/v1/erp/shipments                                        # scope: erp:shipments:write
Content-Type: application/json

{
  "shipment_no": "SO-4711/1",
  "erp_reference": "SO-4711",
  "carrier": "Fast Freight",
  "vehicle_no": "WX 12345",
  "destination": "Berlin DC",
  "planned_at": "2026-11-19T06:00:00Z",
  "items": [
    { "unit_type": "pallet", "identifier": "PAL-000012" },
    { "identifier": "CTN-000301" }
  ]
}
```

`unit_type` is `pallet` or `carton`; without it the type is detected from the label.
`shipment_no` and every `identifier`: up to 64 characters, letters, digits and `- _ / .`.
At most 500 items. A new `shipment_no` → `201`. Re-sending a `shipment_no` that is still
`planned` replaces its header and expected list → `200`; once the dock has started
loading (or the load is confirmed / cancelled) → `409`. The status is checked on the
locked shipment row the replace writes, so a dock starting the load at the same moment
gets either the old list or the new one, never half of each. Two identical creates sent
at the same instant (an ERP retry) resolve to one shipment: one `201`, the other `200`.

```http
GET /api/v1/erp/shipments/{shipment_no}                           # scope: erp:shipments:read
GET /api/v1/erp/shipments?since=2026-11-18T00:00:00Z&status=confirmed&limit=50
```

`shipment_no` may contain `/` (URL-encode it as `%2F`). The list is cursor-paginated
(`meta.next_cursor`), filtered by `updated_at >= since` and optionally by `status`
(`planned`, `loading`, `confirmed`, `cancelled`). Each entry is the load result:

```json
{
  "shipment_no": "SO-4711/1",
  "status": "confirmed",
  "started_at": "2026-11-19T06:02:11+00:00", "started_by": "Anna Nowak",
  "confirmed_at": "2026-11-19T06:41:52+00:00", "confirmed_by": "Jan Kowalski",
  "short_reason": "Second pallet not ready",
  "totals": { "expected": 2, "loaded": 1, "missing": 1, "loaded_units": 48,
              "unexpected_scans": 1, "duplicate_scans": 0, "blocked_scans": 0 },
  "loaded": [ { "unit_type": "pallet", "identifier": "PAL-000012", "unit_count": 48,
                "loaded_at": "2026-11-19T06:05:40+00:00", "loaded_by": "Anna Nowak" } ],
  "missing": [ { "unit_type": "carton", "identifier": "CTN-000301" } ],
  "unexpected": [ { "scanned_code": "PAL-000099", "unit_type": "pallet", "result": "unexpected",
                    "message": "PAL-000099 is not on this shipment.",
                    "scanned_at": "2026-11-19T06:07:02+00:00", "scanned_by": "Anna Nowak" } ],
  "duplicates": [], "blocked": []
}
```

Another tenant's shipment → `404`.

### Webhooks (ERP)

Instead of polling, an integration can register its own receiver and have OpenMES
push events to it (work-order status changes, issues, batch completions, …). Requires
the `erp:webhooks:manage` scope.

```http
GET    /api/v1/erp/webhook-event-types        # catalog of subscribable events
GET    /api/v1/erp/webhooks                   # this key's webhooks
GET    /api/v1/erp/webhooks/{id}
POST   /api/v1/erp/webhooks
PATCH  /api/v1/erp/webhooks/{id}
DELETE /api/v1/erp/webhooks/{id}
```

**Ownership.** A key sees and manages only the webhooks it created itself. Webhooks
an administrator created in the web UI, or another key created, are invisible: they
are not listed, and `GET`/`PATCH`/`DELETE` on their id returns `404`, as for an id
that does not exist. Administrators see every webhook, including key-owned ones, in
**Webhooks → Endpoints**.

#### Register

```http
POST /api/v1/erp/webhooks
X-Api-Key: omk_xxxxxxxxxxxxxxxxxxxxxxxx
Content-Type: application/json

{
    "name": "Odoo production feed",
    "url": "https://erp.example.com/openmes/hook",
    "events": ["work_order.status_changed", "batch.completed"],
    "headers": { "X-Odoo-Db": "production" },
    "secret": "optional-own-secret-min-16-chars",
    "is_active": true
}
```

```json
{
    "data": {
        "id": 12,
        "name": "Odoo production feed",
        "url": "https://erp.example.com/openmes/hook",
        "events": ["work_order.status_changed", "batch.completed"],
        "headers": { "X-Odoo-Db": "production" },
        "is_active": true,
        "last_triggered_at": null,
        "created_at": "2026-11-06T10:00:00+00:00"
    },
    "secret": "Zx8…40 characters…"
}
```

- `secret` is returned **only in this `201` response** and is never readable again.
  Store it now. Leave it out of the request and OpenMES generates one.
- `name` must be unique among the tenant's live webhooks. `events` must be keys from
  `/webhook-event-types`.
- `headers` are extra static headers sent with every delivery. `Host`,
  `Content-Length`, `Content-Type`, `Transfer-Encoding`, `Connection` and other
  hop-by-hop headers, and any `X-OpenMES-*` header, are rejected with `422`.
- One key can own at most **25** webhooks. The 26th returns `422`.

#### Update

`PATCH` is partial: send only the fields you want to change. An omitted `is_active`
keeps the current state, and the secret is rotated only when you send a new `secret`.
For example, `{ "is_active": false }` pauses deliveries.

#### Delete

`DELETE` returns `204`. The webhook is soft-deleted and stops receiving events.

#### When the key stops working

Deactivating or deleting the API key deactivates all of its webhooks. Reactivating
the key does **not** re-enable them; do that with `PATCH { "is_active": true }` or
from the Webhooks page. A delivery for a webhook whose key has expired, been
deactivated or been deleted is not sent and is logged as failed.

#### Verifying deliveries

Each delivery is a `POST` with a JSON body and these headers:

| Header | Value |
|---|---|
| `X-OpenMES-Event` | the event key, e.g. `work_order.status_changed` |
| `X-OpenMES-Delivery` | the delivery id (use it to de-duplicate retries) |
| `X-OpenMES-Signature` | `sha256=` + hex HMAC-SHA256 of the **raw request body**, keyed with the webhook secret |

Compute the HMAC over the bytes you received, before parsing the JSON, and compare it
in constant time:

```python
expected = "sha256=" + hmac.new(secret.encode(), raw_body, hashlib.sha256).hexdigest()
hmac.compare_digest(expected, request.headers["X-OpenMES-Signature"])
```

A non-2xx response or a timeout (10 s) is retried up to 5 times with backoff
(10 s, 30 s, 1 min, 5 min, 15 min).

#### Receivers in the local network

By default, webhook URLs must resolve to **public** addresses. This SSRF protection
applies when a webhook is saved and again on every delivery. Private addresses
(`10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, IPv6 `fc00::/7`) are rejected with
`422`: *"Webhook URL resolves to a private/reserved address."*

An on-premises ERP in the same LAN as OpenMES (for example Odoo on
`http://192.168.1.50:8069`) needs an administrator to allow it in **Webhooks →
Endpoints → Local network receivers**. Enter one entry per line:

- a CIDR range: `192.168.1.0/24`, `fd00:1::/64` (prefix at least `/8`, IPv6 at least `/7`; `/0` is refused);
- a single address: `10.0.0.5`, stored as `10.0.0.5/32`;
- an exact hostname: `odoo.lan`. A private address that this name resolves to is
  allowed. This trusts your DNS for that name, so prefer an IP range when you can.

Only administrators can change the list, and every change is recorded in the audit log
(`webhook_network_allowlist_updated`, with the user and the list before and after).
The list is not included in settings export or import.

Whatever is on the list, these addresses are **always** blocked: loopback
(`127.0.0.0/8`, `::1`), unspecified (`0.0.0.0/8`, `::`), link-local (`169.254.0.0/16`,
`fe80::/10`), cloud metadata endpoints (`169.254.169.254`, `169.254.170.2`,
`100.100.100.200`, `fd00:ec2::254`), multicast and other reserved ranges, and IPv6
forms that embed an IPv4 address (IPv4-compatible `::/96`, NAT64 `64:ff9b::/96`,
6to4 `2002::/16`, Teredo `2001::/32`). IPv4-mapped IPv6 forms such as
`::ffff:127.0.0.1` count as their IPv4 address. If a hostname resolves to several
addresses, every one of them must be allowed. Each delivery is pinned to the address
that was checked, so DNS rebinding cannot redirect it, and redirects are never
followed: a `3xx` answer counts as a failed attempt.


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
| `shipment.confirmed` | A load is confirmed at the dock. | Same shape as `GET /api/v1/erp/shipments/{shipment_no}`. |

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
