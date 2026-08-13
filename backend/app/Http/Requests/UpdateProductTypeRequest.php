<?php

namespace App\Http\Requests;

use App\Services\CustomFieldService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UpdateProductTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route sits behind auth + role:Admin middleware
    }

    public function rules(): array
    {
        $id = $this->route('product_type')->id;

        return array_merge([
            'code' => 'required|string|max:50|unique:product_types,code,'.$id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit_of_measure' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            // See StoreProductTypeRequest — same two-layer image handling.
            'image' => [
                'nullable',
                File::types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(StoreProductTypeRequest::MAX_IMAGE_KB),
            ],
            // Clear the existing photo without uploading a replacement.
            'remove_image' => 'boolean',
        ], app(CustomFieldService::class)->rules('product_type'));
    }

    public function attributes(): array
    {
        return app(CustomFieldService::class)->attributeNames('product_type');
    }
}
