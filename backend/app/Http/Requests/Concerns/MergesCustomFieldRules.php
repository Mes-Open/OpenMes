<?php

namespace App\Http\Requests\Concerns;

use App\Services\CustomFieldService;

/**
 * Lets a Form Request fold admin-defined custom-field rules into its own
 * rule set and hand back coerced/pruned values for storage. The host request
 * names its entity-type; everything else is shared.
 *
 *   public function rules(): array
 *   {
 *       return array_merge([...static...], $this->customFieldRules());
 *   }
 *
 * Then in the controller: $data['custom_fields'] = $request->customFields();
 */
trait MergesCustomFieldRules
{
    /** The custom-field entity-type alias (see config/custom_fields.php). */
    abstract protected function customFieldEntityType(): string;

    /** Validation rules for the request's `custom_fields.*` keys. */
    protected function customFieldRules(): array
    {
        return app(CustomFieldService::class)->rules($this->customFieldEntityType());
    }

    /** Submitted custom values, coerced to their stored shapes and pruned. */
    public function customFields(): array
    {
        return app(CustomFieldService::class)->cast(
            (array) $this->input('custom_fields', []),
            $this->customFieldEntityType(),
        );
    }

    /**
     * Map each custom field to its label so validation messages read
     * "The Colour field is required." rather than exposing the raw
     * `custom_fields.color` key. A host request that needs its own attributes
     * should merge these in.
     */
    public function attributes(): array
    {
        $attributes = [];
        foreach (app(CustomFieldService::class)->definitionsFor($this->customFieldEntityType()) as $def) {
            $attributes["custom_fields.{$def->key}"] = $def->label;
        }

        return $attributes;
    }
}
