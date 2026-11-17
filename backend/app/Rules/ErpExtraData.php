<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Free-form data an ERP attaches to an imported work order (`extra_data`).
 *
 * Kept deliberately simple so it can be shown as a key–value list and read back
 * by any ERP: a JSON object of at most MAX_KEYS keys, each value a scalar, null,
 * or a list of scalars/nulls — two levels at most — and no more than MAX_BYTES
 * once encoded. An empty object is accepted.
 */
class ErpExtraData implements ValidationRule
{
    public const MAX_BYTES = 16384;

    public const MAX_KEYS = 100;

    public const MAX_KEY_LENGTH = 100;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        // A JSON list decodes to a PHP list; an empty object decodes to [] and is fine.
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            $fail(__('The :attribute must be an object of simple values.'));

            return;
        }

        if (count($value) > self::MAX_KEYS) {
            $fail(__('The :attribute may not have more than :max keys.', ['max' => self::MAX_KEYS]));

            return;
        }

        foreach ($value as $key => $item) {
            $key = (string) $key;
            if ($key === '' || mb_strlen($key) > self::MAX_KEY_LENGTH) {
                $fail(__('The :attribute must be an object of simple values.'));

                return;
            }

            if ($item === null || is_scalar($item)) {
                continue;
            }

            if (! is_array($item) || ! array_is_list($item)) {
                $fail(__('The :attribute may not be nested more than two levels.'));

                return;
            }

            foreach ($item as $element) {
                if ($element !== null && ! is_scalar($element)) {
                    $fail(__('The :attribute may not be nested more than two levels.'));

                    return;
                }
            }
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false || strlen($encoded) > self::MAX_BYTES) {
            $fail(__('The :attribute may not be larger than 16 KB.'));
        }
    }
}
