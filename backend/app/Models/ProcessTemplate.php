<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use App\Models\Concerns\KeepsRecordedExecution;
use App\Models\Concerns\SoftDeletesWithAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessTemplate extends Model
{
    use HasFactory, HasTenant;
    use KeepsRecordedExecution;
    use SoftDeletesWithAudit;

    protected $fillable = [
        'product_type_id',
        'name',
        'version',
        'ideal_cycle_minutes',
        'is_active',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'version' => 'integer',
            'ideal_cycle_minutes' => 'decimal:4',
        ];
    }

    /**
     * Get the product type that owns this process template.
     */
    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    /**
     * Get the template steps for this process template.
     */
    public function steps(): HasMany
    {
        return $this->hasMany(TemplateStep::class)->orderBy('step_number');
    }

    /**
     * Get the BOM items for this process template.
     */
    public function bomItems(): HasMany
    {
        return $this->hasMany(BomItem::class)->orderBy('sort_order');
    }

    public function qualityCheckTemplates(): HasMany
    {
        return $this->hasMany(QualityCheckTemplate::class);
    }

    /**
     * Reference photos (work instructions) for this template.
     */
    public function photos(): HasMany
    {
        return $this->hasMany(ProcessTemplatePhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Rich work-instruction media (images, PDFs, videos) across this template's steps. */
    public function stepMedia(): HasMany
    {
        return $this->hasMany(TemplateStepMedia::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Checklist items across this template's steps. */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(TemplateStepChecklistItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Typed operator-output definitions across this template's steps. */
    public function outputs(): HasMany
    {
        return $this->hasMany(TemplateStepOutput::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Generate a JSON snapshot of this template for work order storage.
     * This ensures work orders are immune to template changes.
     */
    public function toSnapshot(): array
    {
        return [
            'template_id' => $this->id,
            'template_name' => $this->name,
            'template_version' => $this->version,
            'steps' => $this->steps->loadMissing(['checklistItems', 'outputs', 'media'])->map(function ($step) {
                return [
                    // Recorded ticks / values point at template rows; the id ties a
                    // snapshot step back to the template step it was frozen from.
                    'template_step_id' => $step->id,
                    'step_number' => $step->step_number,
                    'name' => $step->name,
                    'kind' => $step->kind ?? TemplateStep::KIND_PRODUCTION,
                    'config' => $step->config,
                    'instruction' => $step->instruction,
                    'requires_confirmation' => (bool) $step->requires_confirmation,
                    'quantity_entry' => $step->quantity_entry ?? TemplateStep::QUANTITY_ENTRY_QUANTITIES,
                    'estimated_duration_minutes' => $step->estimated_duration_minutes,
                    'setup_time_minutes' => $step->setup_time_minutes,
                    'run_time_per_unit_minutes' => $step->run_time_per_unit_minutes,
                    'required_operators' => $step->effectiveRequiredOperators(),
                    // Frozen like the rest of the routing, so a segment edited after
                    // release cannot change who is qualified to run this order.
                    'required_skill_ids' => $step->effectiveRequiredSkillIds(),
                    'workstation_id' => $step->workstation_id,
                    'workstation_name' => $step->workstation?->name,
                    'workstation_type_id' => $step->effectiveWorkstationType(),
                    'parameters' => $step->effectiveParameters(),
                    'is_optional' => (bool) $step->is_optional,
                    'variant_group' => $step->variant_group,
                    'is_default_variant' => (bool) $step->is_default_variant,
                    // Work instructions are part of what the order was released
                    // with: an order in progress keeps these even when the
                    // template is edited (orders not started yet are refreshed,
                    // see ProcessTemplateChangePropagator).
                    'checklist_items' => $step->checklistItems->map->toStepDefinition()->values()->all(),
                    'outputs' => $step->outputs->map->toStepDefinition()->values()->all(),
                    'media' => $step->media->map->toStepDefinition()->values()->all(),
                ];
            })->toArray(),
            'bom' => $this->bomItems->map(function ($item) {
                // Product-type lines are sub-assembly references (no material).
                if ($item->component_kind === 'product_type') {
                    return [
                        'component_kind' => 'product_type',
                        'material_id' => null,
                        'product_type_id' => $item->product_type_id,
                        'material_code' => $item->productType?->code,
                        'material_name' => $item->productType?->name,
                        'material_type' => 'product_type',
                        'tracking_type' => null,
                        'unit_of_measure' => $item->productType?->unit_of_measure,
                        'quantity_per_unit' => (float) $item->quantity_per_unit,
                        'scrap_percentage' => (float) $item->scrap_percentage,
                        'consumed_at' => $item->consumed_at,
                        ...$item->snapshotStep(),
                        'external_code' => null,
                        'external_system' => null,
                    ];
                }

                return [
                    'component_kind' => 'material',
                    'material_id' => $item->material_id,
                    'product_type_id' => null,
                    'material_code' => $item->material->code,
                    'material_name' => $item->material->name,
                    'material_type' => $item->material->materialType?->code,
                    'tracking_type' => $item->material->tracking_type,
                    'unit_of_measure' => $item->material->unit_of_measure,
                    // Per finished unit whatever the line was typed per: the packing
                    // step's capacities are folded in here, once, at freeze time.
                    'quantity_per_unit' => $item->perUnitQuantity(),
                    'per' => $item->per ?? BomItem::PER_UNIT,
                    'quantity_per_basis' => (float) $item->quantity_per_unit,
                    'scrap_percentage' => (float) $item->scrap_percentage,
                    'consumed_at' => $item->consumed_at,
                    ...$item->snapshotStep(),
                    'external_code' => $item->material->external_code,
                    'external_system' => $item->material->external_system,
                ];
            })->toArray(),
        ];
    }

    /**
     * Scope to get only active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Children soft-deleted/restored together with this model (mirrors DB FK cascades). */
    public function softDeleteCascades(): array
    {
        return [
            [\App\Models\TemplateStep::class, 'process_template_id'],
            [\App\Models\BomItem::class, 'process_template_id'],
            [\App\Models\QualityCheckTemplate::class, 'process_template_id'],
            [\App\Models\ProcessTemplatePhoto::class, 'process_template_id'],
            [\App\Models\TemplateStepMedia::class, 'process_template_id'],
            [\App\Models\TemplateStepChecklistItem::class, 'process_template_id'],
        ];
    }

    protected function recordedExecutionScope(): array
    {
        return [
            'checklist' => TemplateStepChecklistItem::query()->where('process_template_id', $this->id),
            'outputs' => TemplateStepOutput::query()->where('process_template_id', $this->id),
        ];
    }
}
