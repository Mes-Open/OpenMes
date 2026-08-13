<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MergesCustomFieldRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreProductTypeRequest extends FormRequest
{
    use MergesCustomFieldRules;

    /** Max upload size for the product photo, in KB. */
    public const MAX_IMAGE_KB = 5 * 1024; // 5 MB

    public function authorize(): bool
    {
        return true; // Route sits behind auth + role:Admin middleware
    }

    public function rules(): array
    {
        return array_merge([
            'code' => 'required|string|max:50|unique:product_types',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit_of_measure' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            // Optional product photo. First (cheap) layer only — the
            // authoritative content check is the GD re-encode in ImageSanitizer.
            // SVG is intentionally NOT accepted.
            'image' => [
                'nullable',
                File::types(['jpg', 'jpeg', 'png', 'webp'])->max(self::MAX_IMAGE_KB),
            ],
        ], $this->customFieldRules());
    }

    protected function customFieldEntityType(): string
    {
        return 'product_type';
    }
}
