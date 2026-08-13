<?php

namespace App\Http\Requests;

/**
 * Same rule set as creating one, with the uniqueness check scoped around the
 * row being edited and the flag that clears an existing photo.
 */
class UpdateProductTypeRequest extends StoreProductTypeRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'code' => 'required|string|max:50|unique:product_types,code,'.$this->route('product_type')->id,
            // Clear the existing photo without uploading a replacement.
            'remove_image' => 'boolean',
        ]);
    }
}
