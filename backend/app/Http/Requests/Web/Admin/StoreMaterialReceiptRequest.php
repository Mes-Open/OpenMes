<?php

namespace App\Http\Requests\Web\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Register a manual material arrival from the purchasing workbench.
 *
 * The legacy material page posts through ReceiveMaterialRequest; this request
 * exists so a purchasing clerk can register an arrival without being granted
 * the whole material-master tab.
 */
class StoreMaterialReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (
            $user->hasRole('Admin')
            || $user->can('tab:purchasing')
            || $user->can('tab:materials')
            || $user->can('tab:warehouse')
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'material_id' => ['required', 'integer', 'exists:materials,id'],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:999999999', 'decimal:0,3'],
            'reference' => ['required', 'string', 'max:120'],
        ];
    }
}
