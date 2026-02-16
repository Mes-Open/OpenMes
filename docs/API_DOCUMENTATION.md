[← Back to README](../README.md) | [Quick Start](QUICKSTART.md) | [Testing Guide](TESTING.md) | [Contributing](CONTRIBUTING.md) | [Implementation Status](IMPLEMENTATION_STATUS.md)

---

# OpenMES API Documentation

## Base URL
```
http://localhost:8000/api
```

## Authentication

OpenMES uses Laravel Sanctum for API authentication with bearer tokens.

### Login
```http
POST /auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "your_password"
}
```

**Response:**
```json
{
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "username": "admin",
      "email": "admin@openmmes.local",
      "roles": [...],
      "lines": [...]
    },
    "token": "1|abc123...",
    "force_password_change": false
  }
}
```

### Authenticated Requests
Include the token in the Authorization header:
```http
Authorization: Bearer 1|abc123...
```

### Refresh Token
```http
POST /auth/refresh
Authorization: Bearer YOUR_TOKEN
```

### Logout
```http
POST /auth/logout
Authorization: Bearer YOUR_TOKEN
```

### Get Current User
```http
GET /auth/me
Authorization: Bearer YOUR_TOKEN
```

### Change Password
```http
POST /auth/change-password
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "current_password": "old_password",
  "new_password": "new_password",
  "new_password_confirmation": "new_password"
}
```

---

## Lines

### List Lines
```http
GET /v1/lines
Authorization: Bearer YOUR_TOKEN
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "code": "LINE-01",
      "name": "Assembly Line 1",
      "description": "Main assembly line",
      "is_active": true,
      "workstations": [...]
    }
  ]
}
```

**Authorization:**
- Admin/Supervisor: sees all lines
- Operator: sees only assigned lines

### Get Line Details
```http
GET /v1/lines/{line}
Authorization: Bearer YOUR_TOKEN
```

---

## Work Orders

### List Work Orders
```http
GET /v1/work-orders?status=PENDING&line_id=1
Authorization: Bearer YOUR_TOKEN
```

**Query Parameters:**
- `status` (optional): PENDING, IN_PROGRESS, BLOCKED, DONE, CANCELLED
- `line_id` (optional): Filter by line ID

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "order_no": "WO-001",
      "line_id": 1,
      "product_type_id": 1,
      "planned_qty": "100.00",
      "produced_qty": "0.00",
      "status": "PENDING",
      "priority": 1,
      "due_date": "2026-02-20T00:00:00Z",
      "line": {...},
      "product_type": {...},
      "batches": [...]
    }
  ]
}
```

### Get Work Order Details
```http
GET /v1/work-orders/{workOrder}
Authorization: Bearer YOUR_TOKEN
```

**Response:**
```json
{
  "data": {
    "id": 1,
    "order_no": "WO-001",
    "process_snapshot": {
      "template_id": 1,
      "template_name": "Standard Assembly",
      "template_version": 1,
      "steps": [
        {
          "step_number": 1,
          "name": "Cut pieces",
          "instruction": "Use table saw..."
        }
      ]
    },
    "batches": [...],
    "issues": [...]
  }
}
```

### Create Work Order
```http
POST /v1/work-orders
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "order_no": "WO-001",
  "line_id": 1,
  "product_type_id": 1,
  "planned_qty": 100,
  "priority": 1,
  "due_date": "2026-02-20",
  "description": "Rush order for customer ABC"
}
```

**Response:** 201 Created
```json
{
  "message": "Work order created successfully",
  "data": {...}
}
```

**Permissions Required:** `create work orders`

### Update Work Order
```http
PATCH /v1/work-orders/{workOrder}
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "planned_qty": 120,
  "priority": 2,
  "due_date": "2026-02-25"
}
```

**Permissions Required:** `edit work orders`

**Note:** Cannot update completed work orders.

### Delete Work Order
```http
DELETE /v1/work-orders/{workOrder}
Authorization: Bearer YOUR_TOKEN
```

**Permissions Required:** `delete work orders`

**Note:** Only pending work orders can be deleted.

---

## Batches

### List Batches for Work Order
```http
GET /v1/work-orders/{workOrder}/batches
Authorization: Bearer YOUR_TOKEN
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "work_order_id": 1,
      "batch_number": 1,
      "target_qty": "50.00",
      "produced_qty": "0.00",
      "status": "PENDING",
      "steps": [...]
    }
  ]
}
```

### Get Batch Details
```http
GET /v1/batches/{batch}
Authorization: Bearer YOUR_TOKEN
```

### Create Batch
```http
POST /v1/work-orders/{workOrder}/batches
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "target_qty": 50
}
```

**Response:** 201 Created
```json
{
  "message": "Batch created successfully",
  "data": {
    "id": 1,
    "batch_number": 1,
    "target_qty": "50.00",
    "steps": [
      {
        "id": 1,
        "step_number": 1,
        "name": "Cut pieces",
        "instruction": "Use table saw...",
        "status": "PENDING"
      }
    ]
  }
}
```

**Notes:**
- Batch number is auto-incremented
- Steps are created from work order process snapshot
- Validates against overproduction (configurable)

---

## Batch Steps (Step Execution)

### Start Step
```http
POST /v1/batch-steps/{batchStep}/start
Authorization: Bearer YOUR_TOKEN
```

**Response:** 200 OK
```json
{
  "message": "Step started successfully",
  "data": {
    "id": 1,
    "status": "IN_PROGRESS",
    "started_at": "2026-02-16T15:30:00Z",
    "started_by": {
      "id": 1,
      "username": "operator1"
    }
  }
}
```

**Validation Errors (422 Unprocessable Entity):**
```json
{
  "message": "Cannot start step: previous step not completed",
  "errors": {
    "step": ["Step 1 must be completed before starting step 2"]
  }
}
```

**Blocking Errors (422 Unprocessable Entity):**
```json
{
  "message": "Work order is blocked by issues: Material shortage",
  "errors": {
    "step": ["Work order is blocked by issues: Material shortage"]
  }
}
```

**Business Rules:**
- Step must be PENDING
- Previous step must be DONE/SKIPPED (if `force_sequential_steps` enabled)
- Work order must not be BLOCKED
- Updates batch status to IN_PROGRESS
- Records started_at timestamp and started_by user

### Complete Step
```http
POST /v1/batch-steps/{batchStep}/complete
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "produced_qty": 50
}
```

**Response:** 200 OK
```json
{
  "message": "Step completed successfully",
  "data": {
    "id": 1,
    "status": "DONE",
    "started_at": "2026-02-16T15:30:00Z",
    "completed_at": "2026-02-16T16:15:00Z",
    "duration_minutes": 45,
    "completed_by": {
      "id": 1,
      "username": "operator1"
    }
  }
}
```

**Business Rules:**
- Step must be IN_PROGRESS
- Calculates duration automatically
- Records completed_at timestamp and completed_by user
- Updates batch status to DONE if all steps complete
- Updates batch and work order produced_qty
- Cascades status updates to work order

### Report Problem
```http
POST /v1/batch-steps/{batchStep}/problem
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "issue_type_id": 1,
  "title": "Material defect",
  "description": "Wood pieces have knots"
}
```

**Response:** 501 Not Implemented
```json
{
  "message": "Issue reporting will be implemented in Phase 4"
}
```

---

## Error Responses

### 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

### 403 Forbidden
```json
{
  "message": "This action is unauthorized."
}
```

### 404 Not Found
```json
{
  "message": "Model not found."
}
```

### 422 Unprocessable Entity (Validation Error)
```json
{
  "message": "The order no field is required.",
  "errors": {
    "order_no": ["The order no field is required."]
  }
}
```

### 500 Internal Server Error
```json
{
  "message": "Server Error"
}
```

---

## Rate Limiting

API endpoints are rate-limited to prevent abuse:
- Authentication endpoints: 5 requests per minute
- Other endpoints: 60 requests per minute

**Rate limit headers:**
```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
```

---

## Pagination

List endpoints support pagination:

```http
GET /v1/work-orders?page=2&per_page=20
```

**Response:**
```json
{
  "data": [...],
  "meta": {
    "current_page": 2,
    "per_page": 20,
    "total": 150,
    "last_page": 8
  },
  "links": {
    "first": "http://localhost:8000/api/v1/work-orders?page=1",
    "last": "http://localhost:8000/api/v1/work-orders?page=8",
    "prev": "http://localhost:8000/api/v1/work-orders?page=1",
    "next": "http://localhost:8000/api/v1/work-orders?page=3"
  }
}
```

---

## Testing with cURL

### Complete Workflow Example

```bash
# 1. Login
TOKEN=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"CHANGE_ON_FIRST_LOGIN"}' \
  | jq -r '.data.token')

# 2. Get lines
curl -X GET http://localhost:8000/api/v1/lines \
  -H "Authorization: Bearer $TOKEN"

# 3. Create work order
curl -X POST http://localhost:8000/api/v1/work-orders \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "order_no": "WO-TEST-001",
    "line_id": 1,
    "product_type_id": 1,
    "planned_qty": 100,
    "priority": 1
  }'

# 4. Create batch
curl -X POST http://localhost:8000/api/v1/work-orders/1/batches \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"target_qty": 50}'

# 5. Start first step
curl -X POST http://localhost:8000/api/v1/batch-steps/1/start \
  -H "Authorization: Bearer $TOKEN"

# 6. Complete first step
curl -X POST http://localhost:8000/api/v1/batch-steps/1/complete \
  -H "Authorization: Bearer $TOKEN"
```

---

## WebSocket Support (Future)

Real-time updates will be implemented in a future phase using Laravel Broadcasting.

---

## Versioning

The API uses URL versioning:
- Current version: `v1`
- Base path: `/api/v1/`

Future versions will be released as `/api/v2/`, etc.
