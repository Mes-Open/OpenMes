<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesWorkforceIds;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Creating an account, and optionally the worker record behind it.
 *
 * One form writes to two tables: the account itself, and the personnel record
 * addressed through `worker_*` keys. The controller does that mapping; the rules
 * for both live here, because a Form Request is where this project keeps
 * validation and the controller had two near-identical copies of this set.
 *
 * UpdateUserRequest extends this and overrides only what differs on an edit.
 */
class StoreUserRequest extends FormRequest
{
    use ValidatesWorkforceIds;

    public function authorize(): bool
    {
        // Route is gated by TabAccessMiddleware (`tab.access`).
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[\p{L}\p{N}\s\.\-\']+$/u'],
            'username' => ['required', 'string', 'max:255', $this->usernameUniqueness()],
            'email' => ['required', 'string', 'email', 'max:255', $this->emailUniqueness()],
            'password' => $this->passwordRules(),
            'role' => ['required_if:account_type,user', 'nullable', 'exists:roles,name'],
            'account_type' => ['required', 'in:user,workstation'],
            'workstation_id' => ['nullable', 'exists:workstations,id', 'required_if:account_type,workstation'],
            'worker_code' => ['nullable', 'string', 'max:50', $this->workerCodeUniqueness()],
            'worker_phone' => ['nullable', 'string', 'max:50'],
            'worker_crew_id' => ['nullable', 'integer', Rule::in($this->offeredWorkforceIds('crewOptions', $this->currentWorkerValue('crew_id')))],
            'worker_wage_group_id' => ['nullable', 'integer', Rule::in($this->offeredWorkforceIds('wageGroupOptions', $this->currentWorkerValue('wage_group_id')))],
            'skills' => ['nullable', 'array'],
            'skills.*.id' => ['required', Rule::in($this->offeredWorkforceIds('skillOptions'))],
            'skills.*.level' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => __('Name may only contain letters, numbers, spaces, dots, hyphens, and apostrophes.'),
        ];
    }

    protected function usernameUniqueness(): mixed
    {
        return Rule::unique('users', 'username');
    }

    protected function emailUniqueness(): mixed
    {
        return Rule::unique('users', 'email');
    }

    protected function workerCodeUniqueness(): mixed
    {
        return Rule::unique('workers', 'code');
    }

    /** A new account has no password yet, so one must be set. */
    protected function passwordRules(): array
    {
        return ['required', 'confirmed', Password::defaults()];
    }

    /**
     * The worker value this account already holds, if any.
     *
     * Option lists can be narrower than the table — crewOptions() serves only
     * active crews — so an edit of somebody in a since-deactivated crew has to
     * keep validating. There is no such record on a create, hence null.
     */
    protected function currentWorkerValue(string $attribute): ?int
    {
        return null;
    }
}
