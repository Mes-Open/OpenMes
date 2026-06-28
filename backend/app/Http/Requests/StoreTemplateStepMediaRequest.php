<?php

namespace App\Http\Requests;

use App\Models\TemplateStepMedia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * Attach a rich work-instruction media (image / PDF / video) to a template
 * step. Admin-only (route-gated). The file rule is type-specific; images are
 * additionally re-encoded by ImageSanitizer in the controller.
 */
class StoreTemplateStepMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route sits behind auth + role:Admin middleware.
    }

    public function rules(): array
    {
        $fileRule = match ($this->input('media_type')) {
            TemplateStepMedia::TYPE_IMAGE => File::types(['jpg', 'jpeg', 'png', 'webp'])->max(10 * 1024),
            TemplateStepMedia::TYPE_PDF => File::types(['pdf'])->max(25 * 1024),
            TemplateStepMedia::TYPE_VIDEO => File::types(['mp4', 'webm'])->max(200 * 1024),
            default => File::default()->max(10 * 1024),
        };

        return [
            'media_type' => ['required', Rule::in(TemplateStepMedia::TYPES)],
            'template_step_id' => ['nullable', 'integer', 'exists:template_steps,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'file' => ['required', $fileRule],
        ];
    }
}
