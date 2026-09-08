<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A global kanban status (line_id = null). Store and update take the same
 * shape, so they share one request.
 *
 * `color` is a hex string because it is rendered as a swatch and written
 * straight into an inline `background-color` — anything looser than
 * `#rrggbb` would reach the browser as an attacker-chosen style value.
 */
class LineStatusRequest extends FormRequest
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
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    /** Normalised once here so both controller actions can write it straight through. */
    public function values(): array
    {
        $validated = $this->validated();

        return [
            ...$validated,
            'is_default' => (bool) ($validated['is_default'] ?? false),
            // Left null on purpose: `placeGlobalAt(null)` appends. Coercing to 0
            // made it `max(0, min(count, -1))` = position 1, so an API call that
            // only changed a name shoved that status to the top of the board.
            'sort_order' => $validated['sort_order'] ?? null,
        ];
    }
}
