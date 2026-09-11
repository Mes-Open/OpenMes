<?php

namespace App\Http\Requests;

use App\Support\DemoDatasetRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoadSampleDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route already carries role:Admin; this keeps the rule with the
        // request rather than relying on the route alone.
        return $this->user()?->hasRole('Admin') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $loaded = \Illuminate\Support\Facades\DB::table('system_settings')
            ->where('key', 'sample_data_loaded')
            ->exists();

        // A company is installed and the form did not confirm a replacement:
        // the controller refuses whatever it names, so demanding a valid choice
        // first would answer a stale form with "pick a company" instead of
        // "one is already loaded".
        if ($loaded && ! $this->boolean('replace')) {
            return [];
        }

        return [
            // Replacing wipes the database first, so it has to be an explicit,
            // deliberate act rather than something a stale form can trigger.
            'replace' => ['sometimes', 'boolean'],
            // Which example company to install. Constrained to the registry so
            // a hand-crafted post cannot name an arbitrary seeder class.
            'dataset' => ['required', 'string', Rule::in(DemoDatasetRegistry::keys())],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'dataset.required' => __('Choose which example company to load.'),
            'dataset.in' => __('That example company does not exist.'),
        ];
    }
}
