# Rollout: Warehouses & ERP sync (#212)

Deployment runbook for the warehousing module and the ERP master-data / stock sync.
Written to be followed top to bottom on a production install, by someone who did
not write the feature.

**What changes on an existing install:** four new tables, three new columns on
`product_types`, one new column on `material_lots` and `stock_movements`, three new
API scopes, and one new optional module. **Nothing becomes visible or active until
an admin enables the module** — deploying the code alone changes no behaviour.

| | |
|---|---|
| **Downtime** | Only the container restart (seconds). Migrations are additive; no table is rewritten. |
| **Reversible** | Yes — see [Rollback](#rollback). |
| **Risk if skipped halfway** | Low. Code without configuration is inert. |

---

## Contents

- [1. Before you start](#1-before-you-start)
- [2. Deploy the code](#2-deploy-the-code)
- [3. Verify the migration](#3-verify-the-migration)
- [4. Configure warehousing](#4-configure-warehousing)
- [5. Load opening stock](#5-load-opening-stock)
- [6. Connect the ERP (optional)](#6-connect-the-erp-optional)
- [7. Acceptance test](#7-acceptance-test)
- [8. First week of monitoring](#8-first-week-of-monitoring)
- [9. Rollback](#9-rollback)
- [10. Known gotchas](#10-known-gotchas)

---

## 1. Before you start

**Back up the database and `.env`.** Migrations are additive, but the rollback path
drops the new tables, and opening-stock loads are easy to get wrong on the first try.

```bash
docker compose exec -T postgres pg_dump -U openmmes_user openmmes \
  > backup_pre_warehouse_$(date +%Y%m%d_%H%M).sql
cp .env .env.backup_pre_warehouse
```

**Decide the warehouse layout up front** — it is the one thing that is awkward to
change later, because documents reference warehouses:

- How many warehouses? The minimum that works is **two**: one raw-material store and
  one finished-goods store.
- Which is the **default** for each kind? Auto-generated documents and imports that
  name no warehouse land there.
- Does each warehouse have a **counterpart code in the ERP**? Write it down now; it
  goes in the `ERP code` field and is what lets both sides match documents up.

**Confirm who owns stock.** If the ERP is the system of record for quantities (the
usual case), OpenMES balances are a mirror: an ERP sync **replaces** them. If OpenMES
is to own them, do not run the stock import at all.

**Check the current version** so you can name it in the rollback: Admin → System →
Updates, or `git -C /opt/openmes describe --tags`.

---

## 2. Deploy the code

Pick the path that matches how the install is managed.

### Docker Compose (self-hosted, the usual case)

```bash
cd /path/to/OpenMes
git fetch --all
git checkout main          # or the release tag you are deploying
git pull --ff-only

docker compose up -d --build
```

The entrypoint runs `php artisan migrate --force` on the **primary** container
(Octane) at boot — sidecars skip it, so there is no race. Watch it land:

```bash
docker compose logs -f backend | grep -E "Running migrations|DONE|error"
```

### Admin → System → Updates

Works too: the update flow downloads the release, backs up, copies, migrates and
clears caches. Take the database backup from step 1 first anyway — the built-in
backup covers files, not the database.

### After either path

```bash
docker compose exec backend php artisan optimize:clear
docker compose exec backend php artisan octane:reload
```

> Octane keeps the framework booted in memory. Without the reload, new routes and
> config can appear to be missing — the single most common "the endpoint 404s"
> report after a deploy.

---

## 3. Verify the migration

```bash
docker compose exec backend php artisan migrate:status | grep 2026_08_03
```

All six must read `Ran`:

```
2026_08_03_100000_create_warehouses_table
2026_08_03_100001_create_warehouse_stocks_table
2026_08_03_100002_create_stock_documents_table
2026_08_03_100003_create_stock_document_lines_table
2026_08_03_100004_add_erp_fields_to_product_types_table
2026_08_03_100005_add_warehouse_to_material_lots_and_stock_movements
```

Sanity-check the schema:

```bash
docker compose exec -T postgres psql -U openmmes_user -d openmmes -c "\d warehouses" | head -20
```

Nothing else has changed yet. The new pages are hidden and the new endpoints 404
until the module is enabled — that is expected.

---

## 4. Configure warehousing

1. **Enable the module.** Settings → System → **Modules** → tick **Warehouses** →
   save. (An install that has never customised its module set gets it on
   automatically; one that has saved a custom set gets it **off** — this step is why.)
2. **Grant tab access** if your roles are restricted: Admin → Access → the
   `warehouse` tab, for the roles that should see it.
3. **Create the warehouses** — Admin → Production → Warehouses → All Warehouses:
   - code, name, **kind** (`Raw materials` / `Finished goods` / `Mixed`),
   - **ERP code** where applicable,
   - tick **Default for its kind** on one warehouse per kind.
4. **Decide on automatic documents.** By default, completing a work order creates a
   draft material release and a draft product receipt. To turn that off:

   ```bash
   # .env
   WAREHOUSE_AUTO_DOCUMENTS=false
   ```

   then `docker compose up -d backend && docker compose exec backend php artisan config:cache`.

   Leave it on unless the ERP is going to create that paperwork itself. Generated
   documents are **drafts** — they move no stock until posted.
5. **Point each line at its stock location.** Admin → Lines → *Stock location*.
   Consumption booked on a line is deducted from the location the material actually
   came off: the **picked lot's** warehouse if the pick knows one, otherwise the
   line's stock location, otherwise the default raw-material warehouse. Every
   deduction writes a `stock_movements` row carrying that warehouse. A line left
   without a stock location still works — it falls back to the default — and with the
   Warehouses module off, consumption moves no location balance at all.
6. **Check the negative-stock policy.** Settings → System → *block negative stock*.
   With it on, posting a release that would drive a material below zero is refused —
   and so is **shop-floor consumption a location cannot cover** (step 5) — which is
   what you want once opening stock is loaded, and painful before that. Load the
   opening stock first, or leave the setting off until cutover is done.

At this point the UI works and nothing is synced yet.

---

## 5. Load opening stock

Order matters: an item must exist before a lot or a balance can reference it.

1. **Materials and products** — via the existing CSV import, the admin UI, or the ERP
   master-data endpoints (step 6).
2. **Lot-tracked materials → lots with quantities** (`/erp/material-lots/import`, or
   Admin → Materials → Material Lots by hand). Importing a lot against a warehouse
   also writes the per-warehouse balance and re-derives the material's global
   quantity.
3. **Everything else → a balance snapshot** (`/erp/stock/import`). This is the right
   tool for materials that are not lot-tracked and for finished goods.
4. **Reconcile.** The global `stock_quantity` a material shows must equal the sum of
   its per-warehouse totals:

   ```bash
   docker compose exec -T postgres psql -U openmmes_user -d openmmes -c "
   SELECT m.code,
          m.stock_quantity AS global,
          COALESCE(SUM(ws.quantity), 0) AS warehouses
     FROM materials m
     LEFT JOIN warehouse_stocks ws
       ON ws.material_id = m.id AND ws.material_lot_id IS NULL
    WHERE m.deleted_at IS NULL
    GROUP BY m.id, m.code, m.stock_quantity
   HAVING ABS(m.stock_quantity - COALESCE(SUM(ws.quantity), 0)) > 0.001;"
   ```

   **Zero rows is the pass condition.** Any row means a balance was written by a path
   that skipped reconciliation — stop and investigate before going live, because the
   allocation engine and MRP read the global figure.

> A balance row whose lot column is empty is the material's **total** in that
> warehouse; rows carrying a lot are its breakdown. Never add the two together.

---

## 6. Connect the ERP (optional)

Only needed if an ERP is to feed OpenMES. Requires the **ERP integration** module in
addition to **Warehouses**.

### 6.1 Issue a key

Admin → API keys → new key. Grant only what the integration needs:

| Scope | Needed for |
|---|---|
| `erp:masterdata:write` | products, materials, lots, recipes |
| `erp:stock:write` | balance snapshots, acknowledging documents |
| `erp:stock:read` | reading balances, polling the document backlog |
| `erp:orders:import` | work orders |
| `erp:production:read` | production completions |
| `erp:quality:read` | quality / non-conformance export |

The secret is shown **once**. Store it in the ERP side's secret store, set an IP
allowlist and an expiry if the ERP has a fixed address.

### 6.2 Sync order

Run the first sync in this order — each step resolves the codes the next one uses:

1. `POST /api/v1/erp/products/import` — with `only_categories` set to the
   classifications that really are manufactured products.
2. `POST /api/v1/erp/materials/import` — with the raw-material / packaging
   classifications.
3. `POST /api/v1/erp/boms/import` — recipes; needs the products **and** a process
   template per product.
4. `POST /api/v1/erp/material-lots/import` and/or `POST /api/v1/erp/stock/import`.
5. `POST /api/v1/erp/work-orders/import`.

**Dry-run each step with two or three rows first.** Every import answers with a
per-row report; a `207` means some rows failed and tells you which:

```json
{"data":{"imported":2,"updated":0,"skipped":1,
         "errors":[{"row":3,"field":"material_code","message":"Material 'GHOST' not found"}]}}
```

Only widen to the full payload once a small batch comes back clean. Size limits:
2000 products/materials, 5000 lots or balances, 500 recipes, 1000 work orders per
request.

### 6.3 The pull side

The ERP closes the loop by booking OpenMES's documents:

```bash
# What has OpenMES posted that the ERP has not booked yet?
curl -s "https://mes.example.com/api/v1/erp/stock-documents?unsynced_only=1" \
     -H "X-Api-Key: $KEY"

# ...book it in the ERP, then hand the ERP's own number back
curl -s -X POST "https://mes.example.com/api/v1/erp/stock-documents/$ID/ack" \
     -H "X-Api-Key: $KEY" -H 'Content-Type: application/json' \
     -d '{"erp_reference":"RW-2026/00042"}'
```

Schedule master data and balances **nightly**, and the document backlog every
**5–15 minutes**. Use `?since=<last successful sync>` on the read endpoints for
incremental polling, and follow `meta.next_cursor` until it is null.

Full reference: [API documentation → ERP Integration API](API_DOCUMENTATION.md#erp-integration-api).

---

## 7. Acceptance test

Run this on the live install before handing over. It exercises every moving part and
leaves nothing behind but one work order's paperwork.

| # | Step | Pass condition |
|---|---|---|
| 1 | Open Admin → Production → Warehouses | Warehouses listed, one default per kind |
| 2 | Open Stock On Hand | Opening balances match the ERP / the physical count |
| 3 | Complete a low-volume work order | Two **draft** documents appear on Stock Documents, both linked to that order |
| 4 | Open the material release | Lines match what the order consumed (recorded lots, or BOM × produced quantity) |
| 5 | Post it | Status `posted`; the warehouse balance drops by exactly the line quantities |
| 6 | Re-run the reconciliation query from step 5 | Zero rows |
| 7 | Cancel the same document | Balances return to their pre-post values; two movements in the ledger, netting to zero |
| 8 | `GET /erp/stock-documents?unsynced_only=1` | The posted document appears with its lines |
| 9 | `POST /erp/stock-documents/{id}/ack` | Document leaves the backlog, carries the ERP reference |
| 10 | Re-run one master-data import unchanged | `updated`, not `imported`; quantities unchanged (the sync is convergent) |

Fail any step → stop, do not load the rest of the opening stock, and check
`storage/logs/laravel.log` (import failures log the full reason; the API response
deliberately does not).

---

## 8. First week of monitoring

```bash
# Import and generation failures
docker compose exec backend tail -n 200 storage/logs/laravel.log \
  | grep -E "ERP import row failed|Could not generate stock documents"
```

Watch three things:

- **The document backlog is not growing.** A rising `unsynced_only=1` count means the
  ERP stopped acknowledging — its side of the loop is broken, not ours.
- **No drift.** Run the reconciliation query from step 5 daily for the first week.
- **Drafts are being posted.** A pile of old drafts means nobody owns the posting
  step; decide whether a person or the ERP does it.

---

## 9. Rollback

Roll back in the reverse order of the rollout. Pick the shallowest level that solves
the problem.

**Level 1 — turn the feature off (seconds, no data loss).**
Settings → System → Modules → untick **Warehouses**. The pages disappear, the
`/erp/stock*` endpoints 404, automatic generation stops. Data stays. This is the
right first move for almost any problem.

**Level 2 — stop only the automatic paperwork.**
`WAREHOUSE_AUTO_DOCUMENTS=false` + `config:cache`. Keeps the UI and the API.

**Level 3 — revert the code, keep the schema.**

```bash
git checkout <previous tag>
docker compose up -d --build
docker compose exec backend php artisan optimize:clear
```

The new tables stay behind, unused and harmless. **Preferred over level 4.**

**Level 4 — drop the schema (destroys warehouse data).**

```bash
docker compose exec backend php artisan migrate:rollback --step=6 --force
```

This deletes all warehouses, balances and documents, and removes the new columns.
`stock_movements` rows written by postings survive (only their `warehouse_id` column
goes), so the material ledger stays readable. Take a fresh backup first — this is not
reversible without one.

---

## 10. Known gotchas

| Symptom | Cause | Fix |
|---|---|---|
| New endpoints 404 after deploy | Octane still serving the old boot, or the module is off | `octane:reload`; check Settings → Modules |
| Warehouse pages missing for a role | Role has no access to the `warehouse` tab | Admin → Access |
| No documents on work-order completion | Module off, `WAREHOUSE_AUTO_DOCUMENTS=false`, or no default warehouse for that kind | Step 4 |
| Import returns `207` with `Row could not be processed` | Something failed at the database level; the message is deliberately generic | `storage/logs/laravel.log` has the real reason |
| Balances look halved or doubled | A per-material total was added to its own per-lot rows | Report the lot-less row as the total; lot rows are the breakdown |
| Global stock disagrees with the warehouses | A balance was written by a path that skipped reconciliation | Reconciliation query in step 5, then re-run the stock import |
| Posting refused: "would drive below zero" | `block negative stock` is on and opening stock is short | Load the opening stock, or clear the setting during cutover |
| Consumption refused: "location does not hold enough" | `block negative stock` is on and that location's balance is short | Load its opening stock, move stock to it, or clear the setting during cutover |
| Consumption deducted from the wrong store | The location is frozen on the allocation at its first deduction | Check the picked lot's warehouse and the line's stock location; later corrections credit back the store that gave the material up |
| ERP sync keeps inflating stock | Treating the import as a delta | It is a snapshot — send the current quantity, not the change |
