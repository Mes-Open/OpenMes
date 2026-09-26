<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Editing an account. Same field set as StoreUserRequest; only uniqueness has to
 * ignore this record, the password becomes optional, and workforce values the
 * record already holds stay acceptable.
 */
class UpdateUserRequest extends StoreUserRequest
{
    protected function moduleFieldContext(): array
    {
        return ['action' => $this->moduleFieldAction(), 'user' => $this->currentUser()];
    }

    protected function usernameUniqueness(): mixed
    {
        return Rule::unique('users', 'username')->ignore($this->currentUser()?->id);
    }

    protected function emailUniqueness(): mixed
    {
        return Rule::unique('users', 'email')->ignore($this->currentUser()?->id);
    }

    protected function workerCodeUniqueness(): mixed
    {
        // Ignores the worker row this account points at, not the account itself.
        return Rule::unique('workers', 'code')->ignore($this->currentUser()?->worker_id);
    }

    /** Left blank, the existing password stands. */
    protected function passwordRules(): array
    {
        return ['nullable', 'confirmed', Password::defaults()];
    }

    protected function currentWorkerValue(string $attribute): ?int
    {
        $value = $this->currentUser()?->worker?->{$attribute};

        return $value === null ? null : (int) $value;
    }

    private function currentUser(): ?\App\Models\User
    {
        $user = $this->route('user');

        return $user instanceof \App\Models\User ? $user : null;
    }
}
