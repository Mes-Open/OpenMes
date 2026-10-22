<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AllowsModuleFields;
use App\Http\Requests\Concerns\ValidatesWorkforceIds;
use App\Models\Worker;
use App\Support\InspectionPermissions;
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
 * The worker side is one of two things: `worker_code` creates a new worker,
 * `worker_id` links one that already exists in HR. Never both, and a worker
 * holds at most one live account.
 *
 * UpdateUserRequest extends this and overrides only what differs on an edit.
 */
class StoreUserRequest extends FormRequest
{
    use AllowsModuleFields;
    use ValidatesWorkforceIds;

    public function authorize(): bool
    {
        // Route is gated by TabAccessMiddleware (`tab.access`).
        return true;
    }

    public function rules(): array
    {
        return $this->withModuleFields([
            'name' => ['required', 'string', 'max:255', 'regex:/^[\p{L}\p{N}\s\.\-\']+$/u'],
            'username' => ['required', 'string', 'max:255', $this->usernameUniqueness()],
            'email' => ['required', 'string', 'email', 'max:255', $this->emailUniqueness()],
            'password' => $this->passwordRules(),
            'role' => ['required_if:account_type,user', 'nullable', 'exists:roles,name'],
            'account_type' => ['required', 'in:user,workstation'],
            'workstation_id' => ['nullable', 'exists:workstations,id', 'required_if:account_type,workstation'],
            'worker_id' => ['bail', 'nullable', 'integer', 'prohibits:worker_code', $this->linkableWorker(), $this->workerAccountUniqueness()],
            'worker_code' => ['nullable', 'string', 'max:50', $this->workerCodeUniqueness()],
            'worker_phone' => ['nullable', 'string', 'max:50'],
            'worker_crew_id' => ['nullable', 'integer', Rule::in($this->offeredWorkforceIds('crewOptions', $this->currentWorkerValue('crew_id')))],
            'worker_wage_group_id' => ['nullable', 'integer', Rule::in($this->offeredWorkforceIds('wageGroupOptions', $this->currentWorkerValue('wage_group_id')))],
            'skills' => ['nullable', 'array'],
            'skills.*.id' => ['required', Rule::in($this->offeredWorkforceIds('skillOptions'))],
            'skills.*.level' => ['nullable', 'integer', 'min:1', 'max:5'],
            // Permissions granted to this one person on top of their role — only
            // the ones this form offers (InspectionPermissions), nothing else.
            // Never to a shared workstation login: these rights are for a named
            // person who answers for the decision.
            'permissions' => ['nullable', 'array', 'prohibited_if:account_type,workstation'],
            'permissions.*' => ['string', 'distinct', Rule::in(InspectionPermissions::ALL)],
        ]);
    }

    public function messages(): array
    {
        return [
            'name.regex' => __('Name may only contain letters, numbers, spaces, dots, hyphens, and apostrophes.'),
            'worker_id.unique' => __('This worker already has a user account.'),
            'permissions.prohibited_if' => __('Quality permissions can only be granted to a personal account, not a shared workstation login.'),
            'worker_id.prohibits' => __('Choose either a new worker code or an existing worker, not both.'),
        ];
    }

    protected function moduleFieldFilter(): string
    {
        return 'validation.admin.users';
    }

    protected function moduleFieldContext(): array
    {
        return ['action' => $this->moduleFieldAction(), 'user' => null];
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

    /** A live account per worker; a trashed one no longer holds it. */
    protected function workerAccountUniqueness(): mixed
    {
        return Rule::unique('users', 'worker_id')->whereNull('deleted_at');
    }

    /**
     * The worker must be one this user can see — through Eloquent, so the
     * tenant scope and soft deletes apply, and a foreign or trashed id is a
     * 422 rather than a link to somebody else's record.
     */
    protected function linkableWorker(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! Worker::whereKey($value)->exists()) {
                $fail(__('The selected worker does not exist.'));
            }
        };
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
