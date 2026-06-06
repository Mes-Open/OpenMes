<?php

namespace App\Rules;

use App\Services\Lot\LotPatternFormatter;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidLotPattern implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        foreach ((new LotPatternFormatter)->validate($value) as $error) {
            $fail($error);
        }
    }
}
