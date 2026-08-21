<?php

namespace App\Http\Requests\Concerns;

use Closure;

/**
 * Shared rule for step "equipment parameters": they must be a flat key:value map
 * (`{"temperature_c":"250"}`), never a positional list (`["250"]`). The `array`
 * rule alone accepts both, and a list would silently survive into the equipment
 * snapshot with meaningless integer keys.
 */
trait ValidatesEquipmentParameters
{
    protected static function keyValueMapRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (is_array($value) && $value !== [] && array_is_list($value)) {
                $fail(__('The :attribute field must be a key:value map, not a list.', [
                    'attribute' => $attribute,
                ]));
            }
        };
    }
}
