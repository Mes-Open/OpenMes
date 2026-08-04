# Pantheon connector (OpenMES module)

Connects OpenMES to **Datalab Pantheon** over the **PAWS** REST API
(`https://paws.datalab.eu`). Every Pantheon-specific detail lives in this module —
view names, `acClassif` codes, document types, column mapping — so core stays
ERP-agnostic and every other ERP keeps working unchanged.

Status: **skeleton**. The connection layer, the product sync and the document
push-back are in place; the remaining entities follow the same pattern (see below).

---

## How it plugs in

The module runs **in-process**, so it does not call the OpenMES REST API over HTTP.
It injects the same import services the `/api/v1/erp/*` endpoints use, which means
identical validation, identical per-row reports, no API key, no self-HTTP:

| Pantheon | Module | Core service it feeds |
|---|---|---|
| `tHE_SetItem` + `acClassif` | `Sync\SyncProducts` | `App\Services\Erp\ProductImportService` |
| `tHE_SetItem` + `acClassif` | *to do* `SyncMaterials` | `MaterialImportService` |
| `vHE_SerialNoFree` | *to do* `SyncLots` | `MaterialLotImportService` |
| `tHF_SetPrst` | *to do* `SyncRecipes` | `BomImportService` |
| `vHF_WOExSetsItem` | *to do* `SyncWorkOrders` | `WorkOrderImportService::importErp` |
| `POST /api/Stock` | *to do* `SyncStock` | `StockImportService` |
| `POST /api/Move/*` | `Sync\PushStockDocuments` | `StockDocumentService::acknowledge` |

Adding an entity = one class extending `Sync\Sync` (a `run()` that reads and a
`map()` that renames columns) plus an entry in `PantheonSyncCommand::SYNCS`.

## Install

```bash
cd backend/modules
git clone <this repo> Pantheon
cd ../.. && php artisan migrate          # creates pantheon_sync_runs
```

Then **Admin → Modules → enable "Datalab Pantheon connector"**. On a production
install with cached routes, follow with `php artisan route:cache` — module routes
are skipped while the cache is stale.

## Configure

Configuration lives in the core `integration_configs` row with
`system_type = 'pantheon'` (Admin → Integrations). Its `api_config` column is an
**encrypted array**, so the PAWS password is never stored in plaintext:

```json
{
    "base_url": "https://paws.klient.local",
    "username": "openmes",
    "password": "…",
    "company_db": "PANTHEON_DEMO",
    "product_classifications": ["FINISHED"],
    "material_classifications": ["RAW", "PACKAGING"],
    "document_types": {
        "material_issue": "RW",
        "product_receipt": "PW",
        "posted_status": "P"
    },
    "warehouse_map": { "RAW-1": "0100", "FG-1": "0200" },
    "page_size": 500
}
```

Discover the customer's own values rather than assuming these:

- **classification codes** — `acClassif` in `tHE_SetItem`,
- **document types** — PAWS `Move/getdoctypes`; they are configurable per Pantheon
  installation,
- **warehouse codes** — only needed where an OpenMES warehouse code differs from
  Pantheon's; otherwise the warehouse's own `ERP code` field is used.

## Run

```bash
php artisan pantheon:sync                      # everything, in dependency order
php artisan pantheon:sync --only=products      # one entity
```

The scheduler (already running inside the primary container) does master data
nightly at 02:10 and the document backlog every ten minutes, both
`withoutOverlapping`.

**Status page:** Admin → Pantheon (`/modules/pantheon`) — whether it is configured,
the last twenty runs, their counts and their row errors.

## What is verified and what is not

- The PAWS surface was read from the published `swagger.json` (53 endpoints):
  `Users/authwithtoken`, `DBObjects/selecttables` (generic paged SELECT — this is
  what makes reading their views possible **without** direct MSSQL access),
  `Stock`, `Move/insert`, `Move/getdoctypes`, `Move/changedocstatus`.
- **Not yet verified against a live instance:** the exact response envelope of
  each endpoint, the operator vocabulary of `customConditions`, and the real column
  names in the customer's views. `PawsClient::rows()` and
  `PawsClient::conditions()` are the two methods most likely to need a tweak on
  first contact — deliberately isolated for that reason.
- Column mapping in each `map()` follows the customer's spec from discussion #212
  and must be confirmed against their data.

## Boundary

This module **reads and writes Pantheon**. It does not own warehousing, stock
documents or the ERP contract — those are core features usable by any ERP. If a
change seems to require editing a core file, it almost certainly belongs in the
canonical contract instead, and should go in as a core change so every other ERP
gets it too.
