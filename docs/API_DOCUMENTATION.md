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
  - [Batches](#batches)
  - [Batch Steps](#batch-steps)
  - [Issues](#issues)
  - [Issue Types](#issue-types)
  - [CSV Import](#csv-import)
  - [Analytics](#analytics)
  - [Reports](#reports)
  - [Audit Logs](#audit-logs)
  - [Event Logs](#event-logs)
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
