<?php

namespace App\Models\Concerns;

use App\Services\CustomFieldService;
use Illuminate\Support\Str;

/**
 * Gives a model an admin-defined `custom_fields` JSON column. The entity-type
 * alias defaults to the snake_case class basename (WorkOrder -> work_order),
 * matching the keys in config/custom_fields.php; override with a static
 * $customFieldEntityType property if a model needs a different alias.
 */
trait HasCustomFields
{
    /**
     * Laravel calls initialize{Trait} on every instance — merge the cast and
     * fillable here so host models need no manual wiring.
     */
    public function initializeHasCustomFields(): void
    {
        $this->mergeCasts(['custom_fields' => 'array']);
        $this->mergeFillable(['custom_fields']);
    }

    public function customFieldEntityType(): string
    {
        return property_exists(static::class, 'customFieldEntityType')
            ? static::$customFieldEntityType
            : Str::snake(class_basename(static::class));
    }

    /** Active definitions for this model's entity-type, ordered for display. */
    public function customFieldDefinitions()
    {
        return app(CustomFieldService::class)->definitionsFor($this->customFieldEntityType());
    }

    /** Read a single custom value (dot-paths supported), with a default. */
    public function cf(string $key, mixed $default = null): mixed
    {
        return data_get($this->custom_fields, $key, $default);
    }

    /** Set a single custom value without clobbering the rest of the column.
     *  Uses data_set so dot-paths nest consistently with the cf() getter. */
    public function setCustomField(string $key, mixed $value): static
    {
        $data = $this->custom_fields ?? [];
        data_set($data, $key, $value);
        $this->custom_fields = $data;

        return $this;
    }
}
