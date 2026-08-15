# feat(template-steps): add operation_type + flexible estimated_rate (value + unit) with conversions

## Summary

Add optional operation_type and a flexible estimated rate to process template steps:
- `operation_type`: "batch" | "continuous"
- `estimated_rate_value`: decimal (nullable)
- `estimated_rate_unit`: enum string (nullable), supported values: pcs/min, pcs/hr, units/min, units/hr, batches/hr, kg/hr, litre/hr, litre/min
- `normalized_rate_per_hour` & `normalized_rate_base_unit` (nullable) — populated when simple normalization is possible

These fields are informational in this change; scheduling/estimators remain unchanged.

## Why

Recipes today only capture `estimated_duration_minutes`. Some processes are better expressed as rates (pcs/min, litre/hr, batches/hr). Supporting both batch vs continuous operation modes and flexible units makes templates more expressive and enables future scheduling and reporting improvements.

## What changed

### DB
Add nullable columns to `template_steps`:
- `operation_type` (string, 'batch'|'continuous')
- `estimated_rate_value` (decimal, precision 10, scale 4)
- `estimated_rate_unit` (string)
- `normalized_rate_per_hour` (decimal, precision 14, scale 6, nullable)
- `normalized_rate_base_unit` (string, nullable)

### Model
`TemplateStep` updated ($fillable, $casts) and helper(s) to compute/store normalization when possible.

### API
Store/Update TemplateStep requests accept/validate the new fields with cross-field rules and numeric constraints.

### UI (backend + mobile)
Add operation-type selector and rate input (value + unit) on onboarding and admin process-template forms; show normalized rate when available.

### Factories/tests/seeders
Updated to include new fields where applicable.

### ShapeRegistry/sync
Include new fields where steps are streamed to clients.

### Docs/i18n
Add labels and short UX note about units & conversions.

## Cross-field Validation Rules

Valid combinations of `operation_type` and `estimated_rate_unit`:

| operation_type | allowed units | not allowed | notes |
|---|---|---|---|
| `"batch"` | batches/hr | pcs/*, units/*, kg/hr, litre/* | Batch operations use batch counts |
| `"continuous"` | pcs/min, pcs/hr, units/min, units/hr, kg/hr, litre/min, litre/hr | batches/hr | Continuous operations use physical units only |
| null (unspecified) | any | none | No validation applied if operation_type not set |

**Enforcement:** These rules must be enforced consistently in API request validators, UI validators, and normalization service. Invalid combinations are rejected at create/update time.

## Rate Field Constraints

### `estimated_rate_value` (decimal(10,4))
- **Range**: Non-negative (≥ 0)
- **Minimum non-zero**: 0.0001 (scale 4)
- **Maximum**: 999999.9999
- **Null handling**: Nullable; when null, normalized fields must also be null
- **Validation rule**: `required_if:estimated_rate_unit,*|numeric|min:0|max:999999.9999|regex:/^\d+(\.\d{1,4})?$/`

### `estimated_rate_unit` (enum string)
- **Allowed values**: pcs/min, pcs/hr, units/min, units/hr, batches/hr, kg/hr, litre/hr, litre/min
- **Validation rule**: `required_if:estimated_rate_value,*|in:pcs/min,pcs/hr,units/min,units/hr,batches/hr,kg/hr,litre/hr,litre/min`
- **Cross-field**: Must comply with operation_type rules (see above)

### `normalized_rate_per_hour` (decimal(14,6), computed)
- **Range**: Non-negative (≥ 0)
- **Precision**: 14 digits, scale 6 (e.g., 9999999.999999)
- **Rounding**: HALF_UP for all conversions
- **Null handling**: Nullable; populated only when normalization succeeds
- **Server-computed**: Do not accept client submissions unless explicitly supported

### `normalized_rate_base_unit` (string, computed)
- **Values**: null | "pcs/hr" | "litre/hr" | "kg/hr" | "batches/hr"
- **Null handling**: Set to null if normalization not possible or source is cleared
- **Server-computed**: Derived from estimated_rate_unit normalization rules

## Supported units and normalization

### Count units
- pcs/min, pcs/hr, units/min, units/hr → **normalized to per-hour**
  - Formula: value * 60 (for /min) or value (for /hr)
  - `normalized_rate_base_unit` = "pcs/hr"
  - Always succeeds, result cannot be null

### Volume/mass units
- litre/min → litre/hr
  - Formula: value * 60
  - `normalized_rate_base_unit` = "litre/hr"
- kg/hr → unchanged
  - Formula: value (no conversion needed)
  - `normalized_rate_base_unit` = "kg/hr"

### Batch units
- batches/hr → pcs/hr (only with batch_size context)
  - **Requires**: Product-level metadata (`batch_size`) available
  - Formula: value * batch_size
  - `normalized_rate_base_unit` = "pcs/hr"
  - **When unavailable**: Both normalized fields remain `null`
  - Do not attempt without product context

### Conversions requiring product context
- Conversions between batches ↔ pcs, kg ↔ pcs are **not attempted** without product-level metadata
- If context becomes unavailable during update, clear normalized fields

## Migration (summary)

Add nullable columns with safe defaults; rollback drops the columns. Example: decimal(10,4) for estimated_rate_value, decimal(14,6) for normalized_rate_per_hour.

## Normalization Invariant & Field Invalidation

### Create / Update Flow
1. **Input validation**: Validate estimated_rate_value, estimated_rate_unit, and cross-field rules
2. **Normalization**: Compute normalized_rate_per_hour and normalized_rate_base_unit if conversion succeeds
3. **Persist**: Store all fields (nullable normalized fields if conversion not possible)

### Invalidation Rules
- **When estimated_rate_value or estimated_rate_unit changes**: Recompute normalized fields
- **When estimated_rate_value is cleared (set to null)**: Clear both normalized fields
- **When conversion context becomes unavailable** (e.g., product batch_size removed): Clear normalized fields and log reason
- **When operation_type changes**: Revalidate cross-field rules; reject if new type incompatible with current unit

### API Consistency
- API responses must never expose stale normalized values
- Add tests covering: create, update, clear, context-change scenarios

## Backward compatibility

All new columns are nullable — existing templates and clients keep working.

No changes to scheduling or business logic in this PR.

## Testing & verification

- Run migrations and full test suite: `php artisan migrate && php artisan test`
- API tests:
  - Create/update process-template with valid field combinations; assert persistence
  - Assert normalized_rate_per_hour populated correctly for all count/volume/mass units
  - Assert normalized fields null when batch_size unavailable
  - Assert invalid cross-field combinations rejected (e.g., continuous + batches/hr)
  - Assert invalidation: recompute on value/unit change, clear on null, clear on context loss
- UI smoke: confirm onboarding/admin template forms accept and display the new fields
- Mobile: confirm types/screens show/edit the fields and respect cross-field validation

## Reviewer checklist

- [ ] Migration is safe (nullable, decimal precision/scale correct)
- [ ] Model $fillable / $casts updated correctly with decimal casts
- [ ] API validation: operation_type, estimated_rate_value, estimated_rate_unit rules implemented
- [ ] Cross-field validation: operation_type ↔ estimated_rate_unit combinations enforced
- [ ] Rate constraints: non-negative, precision/scale, rounding (HALF_UP) enforced
- [ ] Normalization logic handles same-base conversions and leaves null if context missing
- [ ] Normalized field invalidation: recompute on input changes, clear on null/context loss
- [ ] Frontend + mobile show selector + rate input + normalized display + cross-field validation
- [ ] Factories and tests updated and passing (including invalidation scenarios)
- [ ] ShapeRegistry/sync includes new fields where required
- [ ] Translations/docs updated with new labels and constraint docs
- [ ] CI green

## Open questions for reviewers

1. Persist `normalized_rate_per_hour` (denormalized) vs compute-on-read — this PR persists for quick reads; can change if preferred.
2. Should product-level default metadata (batch_size, unit_mass) be added in the same PR to enable cross-type conversions, or left to a follow-up?
3. Naming: value+unit approach used for flexibility; would reviewers prefer separate per-unit columns (e.g., estimated_pcs_per_min) instead?
4. Rounding mode: Should HALF_UP be configurable per-deployment, or is it always fixed?

## Notes

This PR is intentionally non-breaking and does not change scheduling/estimation behavior — it only stores richer template metadata for later use.
