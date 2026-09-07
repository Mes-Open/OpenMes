# Import sample files

Files to try **Admin → Import** with. They fit the print-shop demo data and each other, so
load them in order:

| # | File | Entity | Notes |
|---|------|--------|-------|
| 1 | `01-product-types.csv` | Product types | Comma-separated, UTF-8, English headers = field keys. Last row has no code and lands in the error list. |
| 2 | `02-materials.csv` | Materials | **Semicolon-separated with Polish headers** (`Kod`, `Nazwa`, `JM`, `Cena`…) and decimal commas (`24,50`, `1 250`) — exercises auto-detection of the separator and column names. Material types reference the demo codes (`GARMENT`, `THREAD`, `raw_material`…). Last row has neither a code nor an external code. |
| 3 | `03-work-orders.csv` / `.xlsx` | Work orders | Uses demo product types (`TSHIRT`, `POLO`…) and lines (`DTG`, `SITO`, `HAFT`, `TRANSFER`). `Klient` and `Kolor` are not fields — map them as **custom:klient** / **custom:kolor** to keep them on the order. Last row names a line that does not exist. |
| 4 | `04-boms.csv` | Bills of materials | Recipes for the demo products `TSHIRT` and `POLO` (use **Merge** to keep their demo components) plus one for `LANYARD` from file 1, which needs a **process template** first — without one that recipe is reported and skipped. The last row names an unknown material, so the `MUG` recipe is rejected whole. |

Each file ends with one deliberately bad row, so every run finishes as "completed with
errors" and shows what the error list looks like. Re-running a file with the default
**Insert or update** strategy updates the rows already imported instead of duplicating them.

`03-work-orders.xlsx` holds the same rows as the CSV, for testing the Excel path.
