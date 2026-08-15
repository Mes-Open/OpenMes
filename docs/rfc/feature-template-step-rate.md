feat(template-steps): add operation_type + flexible estimated_rate (value + unit) with conversions
Summary

Add optional operation_type and a flexible estimated rate to process template steps:
operation_type: "batch" | "continuous"
estimated_rate_value: decimal (nullable)
estimated_rate_unit: enum string (nullable), supported values: pcs/min, pcs/hr, units/min, units/hr, batches/hr, kg/hr, litre/hr, litre/min
normalized_rate_per_hour & normalized_rate_base_unit (nullable) — populated when simple normalization is possible
These fields are informational in this change; scheduling/estimators remain unchanged.
Why

Recipes today only capture estimated_duration_minutes. Some processes are better expressed as rates (pcs/min, litre/hr, batches/hr). Supporting both batch vs continuous operation modes and flexible units makes templates more expressive and enables future scheduling and reporting improvements.
What changed

DB: add nullable columns to template_steps:
operation_type (string, 'batch'|'continuous')
estimated_rate_value (decimal)
estimated_rate_unit (string)
normalized_rate_per_hour (decimal, nullable)
normalized_rate_base_unit (string, nullable)
Model: TemplateStep updated ($fillable, $casts) and helper(s) to compute/store normalization when possible.
API: Store/Update TemplateStep requests accept/validate the new fields.
UI (backend + mobile): add operation-type selector and rate input (value + unit) on onboarding and admin process-template forms; show normalized rate when available.
Factories/tests/seeders: updated to include new fields where applicable.
ShapeRegistry/sync: include new fields where steps are streamed to clients.
Docs/i18n: add labels and short UX note about units & conversions.
Supported units and normalization

Count units: pcs/min, pcs/hr, units/min, units/hr → normalized to per-hour (multiply/minute values by 60).
Volume/mass: litre/min → litre/hr; kg/hr unchanged.
Batch: batches/hr → can be normalized to pcs/hr only if batch_size (product metadata) is available.
Conversions that require context (batches ↔ pcs, kg ↔ pcs) are not attempted without product-level metadata (e.g., batch_size, unit_mass).
Migration (summary)

Add nullable columns with safe defaults; rollback drops the columns. Example: decimal(10,4) for estimated_rate_value, decimal(14,6) for normalized_rate_per_hour.
Backward compatibility

All new columns are nullable — existing templates and clients keep working.
No changes to scheduling or business logic in this PR.
Testing & verification

Run migrations and full test suite: php artisan migrate && php artisan test
API tests: create/update process-template with new fields; assert persistence and normalized_rate_per_hour where applicable.
UI smoke: confirm onboarding/admin template forms accept and display the new fields.
Mobile: confirm types/screens show/edit the fields.
Reviewer checklist

 Migration is safe (nullable, added after existing columns)
 Model $fillable / $casts updated correctly
 API validation covers allowed units and numeric constraints
 Normalization logic handles same-base conversions and leaves null if context missing
 Frontend + mobile show selector + rate input + normalized display
 Factories and tests updated and passing
 ShapeRegistry/sync includes new fields where required
 Translations/docs updated
 CI green
Open questions for reviewers

Persist normalized_rate_per_hour (denormalized) vs compute-on-read — this PR persists for quick reads; can change if preferred.
Should product-level default metadata (batch_size, unit_mass) be added in the same PR to enable cross-type conversions, or left to a follow-up?
Naming: value+unit approach used for flexibility; would reviewers prefer separate per-unit columns (e.g., estimated_pcs_per_min) instead?
Notes

This PR is intentionally non-breaking and does not change scheduling/estimation behavior — it only stores richer template metadata for later use.
