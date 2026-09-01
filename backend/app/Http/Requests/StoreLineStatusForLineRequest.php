<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A status belonging to one line, added from that line's detail page.
 *
 * Same shape as the global one minus `is_default`: "the status a new work order
 * starts in" is a decision for the global set, and a line-specific status that
 * claimed it would silently override every line's default.
 */
class StoreLineStatusForLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route sits behind auth + the admin tab-access matrix.
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
