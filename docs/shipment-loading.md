# Shipment Loading Verification (scan-to-load)

Before a truck leaves, every pallet and carton going onto it is scanned and checked
against the list of what the load must carry. Nothing ships by accident, nothing is left
behind unnoticed, and the ERP gets back exactly what left the plant.

Part of the **Packaging** module (Settings → Modules). Switching the module off hides the
screens and answers the API with `404`.

---

## Concepts

| Term | Meaning |
|---|---|
| **Shipment** | One load / truck. Identified by its `shipment_no` (the load ID the ERP or carrier knows), plus ERP reference, carrier, vehicle, destination, planned time and notes. |
| **Expected list** | The pallets and loose cartons the shipment must carry, by label number (`PAL-000012`, `CTN-000301`, or an external number). |
| **Scan log** | Append-only record of every scan at the dock: the code, what it resolved to, the answer, who, which bench, when. |

### Status

```
planned ──first scan──▶ loading ──confirm──▶ confirmed
   │                       │
   └──────── cancel ───────┴──▶ cancelled
```

A confirmed or cancelled shipment accepts no more scans, undos or edits.

---

## Planning a load (supervisor / admin)

*Admin: Warehouses → Shipments. Supervisor: Logistics → Shipments.*

1. **New shipment** — enter the shipment number and the optional header fields.
2. Paste the **expected pallets and cartons**, one label per line. A line may also name
   the type, before or after the label, separated by a comma, semicolon or tab:

   ```
   PAL-000012
   pallet,PAL-000013
   CTN-000301;carton
   ```

   Lines without a type get the type chosen in the selector (*Detect type automatically*,
   *All pallets*, *All cartons*). Detection uses the label: a known pallet or carton number
   decides; otherwise a `CTN` prefix means carton, anything else pallet.
3. **Upload CSV** fills the same box from a file (same format; a `type,identifier` header
   line is skipped), so it can be checked before saving.

Label numbers and the shipment number accept letters, digits and `- _ / .`, up to 64
characters; a list holds at most 500 units. The shipment number is unique among live
shipments of the plant and can be reused after the shipment is deleted.

The detail page shows the header, live totals (expected / loaded / missing), the expected
list with who loaded what, and the scan log. While the load is open it can be edited
(a unit that is already loaded cannot be dropped from the list — undo its scan first),
cancelled, or opened in the loading terminal. A shipment being loaded cannot be deleted;
cancel it first.

---

## Loading terminal (operator)

`/logistics/loading` — the **Loading** tab in the operator top bar, *Loading terminal* in
the admin and supervisor menus. Pick an open shipment, then scan.

The scan box keeps the focus, so a keyboard-wedge scanner (which "types" the label and
presses Enter) works without touching the screen. Each scan is answered at once, in place —
the page never navigates — with a coloured panel and a toast:

| Answer | Colour | When |
|---|---|---|
| **Loaded** | green | The unit is on the list and was not loaded yet. |
| **Duplicate** | amber | Already loaded on this truck. Also for a carton whose pallet is on the list: it travels with the pallet and is not counted twice. |
| **Not on this load** | red | A real pallet / carton that is not on the list. |
| **Unknown code** | red | No such pallet or carton (in this plant). |
| **Blocked** | red | The unit may not leave: pallet still open, already shipped, failed quality control or not released by quality yet, carries held units, carton still open, or already loaded on another open shipment. |

The expected list, totals and scan log are live: a second screen on the same shipment (the
office, a supervisor's tablet) sees every scan as it happens.

**Undo** next to a loaded row takes it off the truck again. The original scan stays in the
log and the undo is logged as *removed*.

Two scanners at the same truck are safe: each scan locks the shipment row, so a label
scanned at both at once is loaded once and answered *duplicate* on the other.

### Confirming

**Confirm loading** lets the truck go:

- loaded pallets become **shipped** (with `shipped_at`); their serialised units are shipped
  with them, and a unit on hold keeps its hold;
- the units of loaded loose cartons are shipped the same way;
- the shipment becomes **confirmed**, and the `shipment.confirmed` webhook is sent.

A **short load** (something on the list not loaded) can only be confirmed by a supervisor
or admin, who is asked for a reason; it is stored as `short_reason` and shown on the
shipment and in the API. An operator who tries is refused (`403`); a supervisor without a
reason gets `422`. A shipment with nothing loaded cannot be confirmed. Units that were not
loaded stay where they are.

---

## REST API (`auth:sanctum`)

| Method | Path | Who | |
|---|---|---|---|
| `GET` | `/api/v1/shipments?status=&active=1` | Operator, Supervisor, Admin | Shipment headers, newest first (max 200). |
| `GET` | `/api/v1/shipments/{id}` | Operator, Supervisor, Admin | The load result (below). |
| `POST` | `/api/v1/shipments` | Supervisor, Admin | Header + `items[]` (`unit_type?`, `identifier`). `201`. |
| `POST` | `/api/v1/shipments/{id}/scan` | Operator, Supervisor, Admin | `{ "code": "PAL-000012" }` → `data.scan` (`result`, `message`) and `data.totals`. |
| `POST` | `/api/v1/shipments/{id}/items/{item}/unload` | Operator, Supervisor, Admin | Undo a scan. |
| `POST` | `/api/v1/shipments/{id}/confirm` | Operator (full load), Supervisor, Admin | `{ "short_reason": "…" }` for a short load. |

A scan on a confirmed or cancelled shipment → `422`. Another tenant's shipment → `404`.

## ERP API

`POST /api/v1/erp/shipments` (scope `erp:shipments:write`) and
`GET /api/v1/erp/shipments/{shipment_no}` / `GET /api/v1/erp/shipments?since=` (scope
`erp:shipments:read`) — see [API Documentation → Shipments (ERP)](API_DOCUMENTATION.md#shipments-erp).
Both scopes are granted per key in *Settings → API Tokens → ERP API keys*.

### Load result

`GET /api/v1/shipments/{id}`, both ERP reads, the confirm response and the
`shipment.confirmed` webhook share one shape: the header (`shipment_no`, `status`,
`started_at`/`started_by`, `confirmed_at`/`confirmed_by`, `short_reason`, …), `totals`
(`expected`, `loaded`, `missing`, `loaded_units`, `unexpected_scans`, `duplicate_scans`,
`blocked_scans`), `loaded` (each with `unit_count` — serialised units on the pallet /
in the carton, else its recorded quantity — and who loaded it when), `missing`, and the
`unexpected` (incl. unknown codes), `duplicates` and `blocked` scans.

---

## Data model

| Table | Notes |
|---|---|
| `shipments` | Header and status. `shipment_no` unique per tenant among non-deleted rows. |
| `shipment_items` | Expected units: `unit_type` (`pallet`/`carton`), `identifier`, resolved `pallet_id`/`carton_id`, `loaded_at`/`loaded_by_id`. Unique (`shipment_id`, `unit_type`, `identifier`) among non-deleted rows. |
| `shipment_scans` | Append-only log: `scanned_code`, resolved `unit_type`/`unit_id`, `result` (`loaded`, `duplicate`, `unexpected`, `unknown`, `blocked`, `removed`), `message`, `user_id`, `workstation_id`, `scanned_at`. |

All three carry `tenant_id`, soft deletes and `deleted_by_id`; deleting a shipment
soft-deletes its items and scans with it (Admin → Trash restores them together).

Live sync: collections `shipments`, `shipment_items` (rolling 90 days) and
`shipment_scans` (rolling 30 days), readable by the Packaging tab and by operators.
