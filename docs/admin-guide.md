# Admin Guide

This guide covers installation, configuration, and administration of OpenMES.

---

## Table of Contents

- [Installation](#installation)
- [First Steps After Installation](#first-steps-after-installation)
- [User Management](#user-management)
- [Production Structure](#production-structure)
  - [Factories and Divisions](#factories-and-divisions)
  - [Lines](#lines)
  - [Workstations](#workstations)
- [Product Configuration](#product-configuration)
  - [Product Types](#product-types)
  - [Process Templates](#process-templates)
- [Work Orders](#work-orders)
  - [Creating Orders Manually](#creating-orders-manually)
  - [Importing Orders via CSV/Excel](#importing-orders-via-csvexcel)
- [HR Management](#hr-management)
- [System Settings](#system-settings)
  - [Production Period](#production-period)
  - [Overproduction](#overproduction)
  - [Sequential Steps](#sequential-steps)
- [Warehouses](#warehouses)
  - [Setting Up Warehouses](#setting-up-warehouses)
  - [Stock Documents](#stock-documents)
  - [Syncing Stock With an ERP](#syncing-stock-with-an-erp)
- [Modules](#modules)
  - [Enabling and Disabling Modules](#enabling-and-disabling-modules)
  - [Installing a Module from ZIP](#installing-a-module-from-zip)
- [API Tokens](#api-tokens)
- [Shifts](#shifts)
- [Audit Logs](#audit-logs)
- [Maintenance](#maintenance)
- [Updates](#updates)

---

## Installation

### Prerequisites

- Docker 20.10+ and Docker Compose
- Git

### Steps

```bash
# 1. Clone the repository
git clone https://github.com/Mes-Open/OpenMes.git
cd OpenMes

# 2. Start containers
docker-compose up -d

# 3. Open browser
# Navigate to http://localhost
```

The web installer will guide you through three steps:
1. **Basic Configuration** — site name, URL
2. **Database Configuration** — PostgreSQL connection details
3. **Admin Account** — your admin username, email, and password

After completing the wizard, the system is ready.

### Production Deployment

For production use, see the deployment notes in the main README. Ensure:
- `APP_DEBUG=false` in your `.env`
- HTTPS is configured (use a reverse proxy like Caddy or Nginx)
- The `.env` file is secured and not committed to version control

---

## First Steps After Installation

After installing, complete this checklist:

1. **Create production lines** — at least one line is required before operators can work
2. **Add users** — create accounts for supervisors and operators
3. **Assign users to lines** — operators must be assigned to see their queue
4. **Create product types** — categories for your products
5. **Create process templates** — step-by-step workflows for each product
6. **Import or create work orders** — orders to be produced
7. **Load sample data** (optional) — Settings → Sample Data loads realistic test data

---

## User Management

Go to **Admin → Users** to manage accounts.

### Roles

| Role | Access |
|---|---|
| `Admin` | Full system access: settings, all data, user management |
| `Supervisor` | Accept/reject orders, manage issues, view reports |
| `Operator` | View assigned line queue, execute steps, report issues |

### Creating a User

1. Click **New User**
2. Fill in username, email, password, and select a role
3. For Operator/Supervisor roles, assign them to one or more lines
4. Click **Save**

### Assigning Lines

Operators see only the lines they are assigned to. Assign lines:
1. Open the user's profile
2. In the **Lines** section, select lines from the list
3. Save changes

---

## Production Structure

### Factories and Divisions

Use **Factories** and **Divisions** to organise your physical locations:
- Factory → Division → Line → Workstation

Navigate to **Admin → Structure → Factories** and **Admin → Structure → Divisions**.

### Lines

Lines are the core production units. Each work order is assigned to a line.

1. Go to **Admin → Production → Lines**
2. Click **New Line**
3. Enter name, code, and optionally assign a division
4. Set `Active` to true for the line to appear in operator selection
5. Save

### Workstations

Workstations are individual machines or work positions within a line.

1. Go to **Admin → Structure → Workstations** (or from within a Line's detail page)
2. Assign a workstation type, line, and optionally a division

---

## Product Configuration

### Product Types

Product types are categories that group similar products.

1. Go to **Admin → Production → Product Types**
2. Create types (e.g. "Wooden Chair", "Metal Frame", "Assembly Kit")

### Process Templates

Process templates define the step-by-step production workflow for a product.

1. Go to **Admin → Production → Process Templates**
2. Click **New Template**
3. Add steps with:
   - **Name** — e.g. "Cut", "Drill", "Paint", "Assembly", "Quality Check"
   - **Order** — sequence number
   - **Required role** — optional, restrict step to a specific role
4. Assign the template to one or more product types

When creating work orders, selecting a product type automatically selects the associated process template.

---

## Work Orders

### Creating Orders Manually

1. Go to **Admin → Work Orders → New**
2. Fill in:
   - **Order number** (unique)
   - **Product name**
   - **Quantity**
   - **Line** (production line)
   - **Product type** and **Process template**
   - **Due date** and **Priority** (1–5)
   - Week/month/year numbers (if production period is configured)
3. Save — the order is created with status **Pending**

A supervisor must accept the order before operators can work on it.

### Importing Orders via CSV/Excel

For bulk imports (e.g. from ERP or Excel):

1. Go to **Admin → Work Orders → CSV Import**
2. Upload a `.csv`, `.xls`, or `.xlsx` file
3. Map your file's columns to OpenMES fields:
   - **Required**: Order Number, Quantity
   - **Optional**: Product Name, Line, Due Date, Priority, Week, Month, Year
4. Choose an import strategy:
   - `Insert only` — skip orders with duplicate order numbers
   - `Update only` — only update existing orders
   - `Insert or Update` — upsert (recommended for ERP sync)
5. Click **Process** to import

Previously saved column mappings are stored as profiles and can be reused.

---

## HR Management

OpenMES includes basic HR tracking linked to production:

- **Workers** — Shop floor workers (separate from system users)
- **Crews** — Work groups assigned to shifts
- **Skills** — Competency tracking
- **Wage Groups** — Pay classification (for reporting)

Navigate to **Admin → HR** to manage these.

---

## System Settings

Go to **Settings → System** (Admin only).

### Production Period

Controls whether work orders must specify a week or month number:

| Value | Behavior |
|---|---|
| `none` | No period required |
| `weekly` | Week number required on all orders |
| `monthly` | Month number required on all orders |

### Overproduction

`allow_overproduction`: if enabled, operators can produce more units than planned on a work order. If disabled, the batch completion form enforces the planned quantity as a ceiling.

### Sequential Steps

`force_sequential_steps`: if enabled, operators must complete steps in defined order. If disabled, steps can be completed in any order.

---

## Warehouses

Warehousing is an optional module — enable **Warehouses** under
**Settings → System → Modules** (see [Modules](#modules)). With it off, the pages
below are hidden and nothing in this section applies.

### Setting Up Warehouses

1. Go to **Admin → Production → Warehouses → All Warehouses**
2. Click **+ New Warehouse** and give it a **code**, a **name** and a **kind**:
   - **Raw materials** — materials are issued out of it
   - **Finished goods** — product is received into it
   - **Mixed** — both
3. Optionally set an **ERP code** — the identifier of the same warehouse in your
   ERP, so stock syncs and documents can be matched up on both sides
4. Tick **Default for its kind** on the warehouse that should be used whenever an
   import or an automatically generated document names no warehouse

Set one default for raw materials and one for finished goods before you rely on
automatic document generation — without them, nothing is generated.

**Admin → Production → Warehouses → Stock On Hand** shows live balances. A row with
an empty **Lot** column (shown as *total*) is the item's total in that warehouse;
rows carrying a lot are its breakdown — don't add the two together.

A warehouse that still holds stock or carries documents cannot be deleted;
deactivate it instead.

### Stock Documents

**Admin → Production → Warehouses → Stock Documents** lists the warehouse paperwork:

| Type | Meaning |
|---|---|
| **Material release** | Raw material leaving a warehouse for production |
| **Material receipt** | Raw material arriving (ERP receipt, return from the floor) |
| **Product receipt** | Finished product arriving when an order is concluded |
| **Product release** | Finished product leaving (shipment, correction) |

Every document is a **draft** until posted, and **posting is what moves stock** — it
updates the warehouse balance, the material's stock quantity and the stock-movement
ledger. **Cancelling a posted document reverses all of it**, so a mistake is undone
without editing balances by hand. A posted document must be cancelled before it can
be deleted.

**Completing a work order creates its paperwork automatically** (drafts): a material
release for what it consumed — from the lots the shop floor actually booked, or from
the order's BOM × produced quantity when it didn't — and a product receipt for what it
produced. Review and post them from this page. To turn generation off, set
`WAREHOUSE_AUTO_DOCUMENTS=false` in `.env`.

Create a document by hand with **+ New Document**: pick the type, the warehouse, and
add one line per item. The item picker follows the type — material documents list
materials, product documents list products.

### Syncing Stock With an ERP

With both the **Warehouses** and **ERP integration** modules enabled, an ERP can keep
OpenMES in step over the REST API:

- **push balances** — a quantity snapshot per warehouse and item; the ERP owns the
  warehouse, so a synced quantity replaces the OpenMES figure and re-running the sync
  converges instead of drifting
- **push lots** — lot numbers with their available quantities
- **pull documents** — the backlog of posted releases and receipts the ERP has not
  booked yet, then acknowledge each one with the ERP's own document number so it
  leaves the backlog

Create a key under **Admin → API keys** with the `erp:stock:read` /
`erp:stock:write` scopes (and `erp:masterdata:write` for products, materials, lots and
recipes). Endpoints, payloads and scopes:
[API documentation → ERP Integration API](API_DOCUMENTATION.md#erp-integration-api).

---

## Modules

Modules are optional extensions that add functionality to OpenMES without modifying core code.

### Enabling and Disabling Modules

1. Go to **Admin → Modules → Installed**
2. Each module shows its status (enabled/disabled)
3. Click **Enable** or **Disable** as needed
4. A server restart may be required for full effect

### Installing a Module from ZIP

1. Go to **Admin → Modules → Install**
2. Upload a `.zip` file containing the module
3. The module is extracted and appears in the installed list
4. Enable it to activate

Module ZIP structure:
```
MyModule.zip
└── MyModule/
    ├── module.json        (required: name, display_name, version)
    ├── Providers/
    │   └── MyModuleServiceProvider.php
    ├── Controllers/
    ├── Models/
    ├── views/
    └── migrations/
```

See [HOOKS.md](../HOOKS.md) and [development.md](development.md) for how to build modules.

---

## API Tokens

OpenMES provides a token-based REST API for ERP integration, custom dashboards, and automation.

1. Go to **Settings → API Tokens**
2. Click **Create Token**
3. Enter a name (e.g. "ERP Integration")
4. Copy and store the token immediately — it is not shown again
5. Use the token in the `Authorization: Bearer <token>` header

See [API Documentation](API_DOCUMENTATION.md) for full endpoint reference.

To revoke a token, click the **Revoke** button next to it in the list.

---

## Shifts

Shifts define working hours for automatic shift-based reporting and module features.

1. Go to **Admin → Schedule → Shifts**
2. Create shifts with start time, end time, and name (e.g. "Day Shift", "Night Shift")
3. Shifts are used by modules (e.g. Packaging) and reports

---

## Audit Logs

Every data-changing action in OpenMES is logged with the user, timestamp, entity type, and old/new values.

1. Go to **Admin → Audit Logs**
2. Filter by date, user, or entity type
3. Export logs as CSV for compliance reporting

Audit logs are **immutable** — enforced at the database level. They cannot be edited or deleted through the application.

---

## Maintenance

The **Maintenance** section tracks equipment and tooling:

- **Maintenance Events** — scheduled and unscheduled maintenance with costs and duration
- **Tools** — inventory of tools with last-service dates
- **Cost Sources** — categories for maintenance cost classification

Navigate to **Admin → Maintenance**.

---

## Updates

OpenMES can check for and apply updates:

1. Go to **Admin → System → Updates**
2. Click **Check for Updates**
3. If an update is available, click **Apply Update**

The update process pulls the latest release ZIP and applies it. A changelog is shown before applying.

> **Backup first.** Always back up your database and `.env` before applying updates in production.

```bash
# Manual backup
docker compose exec postgres pg_dump -U openmmes_user openmmes > backup_$(date +%Y%m%d).sql
```
