<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreProcessTemplatePhotoRequest extends FormRequest
{
    /** Hard cap on photos per template (DoS / storage abuse guard). */
    public const MAX_PHOTOS_PER_TEMPLATE = 20;

    public function authorize(): bool
    {
        return true; // Route sits behind auth + role:Admin middleware
    }

    public function rules(): array
    {
        return [
            // First validation layer (cheap). The authoritative content check
            // is the GD re-encode in ImageSanitizer — this rule only filters
            // the obvious junk early. SVG is intentionally NOT accepted.
            'photo' => [
                'required',
                File::types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(10 * 1024), // 10 MB
            ],
            'caption' => ['nullable', 'string', 'max:255'],
        ];
    }
}
