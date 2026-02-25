@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
<x-breadcrumbs :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'Users', 'url' => route('admin.users.index')],
    ['label' => 'Edit User', 'url' => null],
]" />

<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('admin.users.index') }}" class="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Users
        </a>
        <h1 class="text-3xl font-bold text-gray-800">Edit User</h1>
    </div>

    @php
        $existingWorker = $user->worker;
        $workerSkillIds = $existingWorker ? $existingWorker->skills->pluck('id')->toArray() : [];
    @endphp

    <form method="POST" action="{{ route('admin.users.update', $user) }}" x-data="{
        password: '',
        passwordConfirmation: '',
        showPassword: false,
        showConfirm: false,
        accountType: '{{ old('account_type', $user->account_type) }}',
        workerEnabled: {{ ($existingWorker || old('worker_code')) ? 'true' : 'false' }},
        skillRows: @js($skills->map(fn($s) => [
            'id'      => $s->id,
            'code'    => $s->code,
            'name'    => $s->name,
            'enabled' => in_array($s->id, $workerSkillIds),
            'level'   => $existingWorker?->skills->firstWhere('id', $s->id)?->pivot->level ?? 1,
        ]))
    }" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- ── Login Account ── --}}
        <div class="card">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Login Account</h2>

            <div class="mb-4">
                <label class="form-label">Full Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}"
                       class="form-input w-full @error('name') border-red-500 @enderror"
                       required autofocus>
                @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">Username <span class="text-red-500">*</span></label>
                <input type="text" name="username" value="{{ old('username', $user->username) }}"
                       class="form-input w-full @error('username') border-red-500 @enderror" required>
                <p class="text-xs text-gray-500 mt-1">Used for login</p>
                @error('username') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">Email <span class="text-red-500">*</span></label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}"
                       class="form-input w-full @error('email') border-red-500 @enderror" required>
                @error('email') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="form-label">Account Type <span class="text-red-500">*</span></label>
                    <select name="account_type" x-model="accountType"
                            class="form-input w-full @error('account_type') border-red-500 @enderror" required>
                        <option value="user">User (Personal)</option>
                        <option value="workstation">Workstation (Shared)</option>
                    </select>
                    @error('account_type') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Role <span class="text-red-500">*</span></label>
                    <select name="role" class="form-input w-full @error('role') border-red-500 @enderror" required>
                        <option value="">Select a role</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}"
                                    {{ old('role', $user->roles->first()?->name) === $role->name ? 'selected' : '' }}>
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('role') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mb-4" x-show="accountType === 'workstation'" x-cloak>
                <label class="form-label">Workstation <span class="text-red-500">*</span></label>
                <select name="workstation_id"
                        class="form-input w-full @error('workstation_id') border-red-500 @enderror"
                        :required="accountType === 'workstation'">
                    <option value="">Select a workstation</option>
                    @foreach($workstations as $ws)
                        <option value="{{ $ws->id }}"
                                {{ old('workstation_id', $user->workstation_id) == $ws->id ? 'selected' : '' }}>
                            {{ $ws->name }} ({{ $ws->line->name }})
                        </option>
                    @endforeach
                </select>
                @error('workstation_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="form-label">New Password <span class="text-gray-400 font-normal">(leave blank to keep)</span></label>
                    <div class="relative">
                        <input :type="showPassword ? 'text' : 'password'" name="password"
                               x-model="password"
                               class="form-input w-full pr-10 @error('password') border-red-500 @enderror"
                               minlength="8">
                        <button type="button" @click="showPassword = !showPassword"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                    @error('password') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Confirm New Password</label>
                    <div class="relative">
                        <input :type="showConfirm ? 'text' : 'password'" name="password_confirmation"
                               x-model="passwordConfirmation"
                               class="form-input w-full pr-10" minlength="8">
                        <button type="button" @click="showConfirm = !showConfirm"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <svg x-show="!showConfirm" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showConfirm" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                    <p class="text-xs mt-1"
                       :class="password && passwordConfirmation && password === passwordConfirmation ? 'text-green-600' : 'text-gray-400'">
                        <span x-show="password && passwordConfirmation && password === passwordConfirmation">✓ Passwords match</span>
                        <span x-show="password && passwordConfirmation && password !== passwordConfirmation" class="text-red-500">Passwords do not match</span>
                        <span x-show="!password">Leave blank to keep current password</span>
                    </p>
                </div>
            </div>

            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="force_password_change" value="1"
                       class="rounded border-gray-300 text-blue-600"
                       {{ old('force_password_change', $user->force_password_change) ? 'checked' : '' }}>
                <span class="text-sm text-gray-700">Force password change on next login</span>
            </label>
        </div>

        {{-- ── Worker Profile ── --}}
        <div x-show="accountType === 'user'" x-cloak class="card">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h2 class="text-lg font-bold text-gray-800">Worker Profile</h2>
                    @if($existingWorker)
                        <p class="text-xs text-green-600 mt-0.5">
                            Linked to worker: <strong>{{ $existingWorker->code }}</strong>
                        </p>
                    @else
                        <p class="text-xs text-gray-500 mt-0.5">Optionally link this account to a production worker record.</p>
                    @endif
                </div>
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" x-model="workerEnabled"
                           class="rounded border-gray-300 text-blue-600">
                    <span class="text-sm font-medium text-gray-700">
                        {{ $existingWorker ? 'Worker profile active' : 'Create worker profile' }}
                    </span>
                </label>
            </div>

            <div x-show="workerEnabled" x-cloak class="space-y-4 border-t border-gray-100 pt-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Worker Code <span class="text-red-500">*</span></label>
                        <input type="text" name="worker_code"
                               value="{{ old('worker_code', $existingWorker?->code) }}"
                               :required="workerEnabled"
                               class="form-input w-full @error('worker_code') border-red-500 @enderror"
                               placeholder="e.g. WRK-001" maxlength="50">
                        @error('worker_code') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Phone</label>
                        <input type="text" name="worker_phone"
                               value="{{ old('worker_phone', $existingWorker?->phone) }}"
                               class="form-input w-full @error('worker_phone') border-red-500 @enderror"
                               placeholder="+48 123 456 789" maxlength="50">
                        @error('worker_phone') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Crew</label>
                        <select name="worker_crew_id" class="form-input w-full">
                            <option value="">— No crew —</option>
                            @foreach($crews as $crew)
                                <option value="{{ $crew->id }}"
                                        {{ old('worker_crew_id', $existingWorker?->crew_id) == $crew->id ? 'selected' : '' }}>
                                    {{ $crew->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('worker_crew_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Wage Group</label>
                        <select name="worker_wage_group_id" class="form-input w-full">
                            <option value="">— No wage group —</option>
                            @foreach($wageGroups as $wg)
                                <option value="{{ $wg->id }}"
                                        {{ old('worker_wage_group_id', $existingWorker?->wage_group_id) == $wg->id ? 'selected' : '' }}>
                                    {{ $wg->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('worker_wage_group_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Skills --}}
                @if($skills->isNotEmpty())
                <div class="border-t border-gray-100 pt-3">
                    <p class="text-sm font-medium text-gray-700 mb-2">Skills</p>
                    <div class="divide-y divide-gray-100">
                        <template x-for="(row, index) in skillRows" :key="row.id">
                            <div class="flex items-center gap-3 py-2">
                                <label class="flex items-center gap-2 flex-1 cursor-pointer">
                                    <input type="checkbox"
                                           :name="row.enabled ? 'skills[' + index + '][id]' : ''"
                                           :value="row.id"
                                           x-model="row.enabled"
                                           class="rounded border-gray-300 text-blue-600">
                                    <span class="text-sm font-medium text-gray-800" x-text="row.name"></span>
                                    <span class="text-xs text-gray-400 font-mono" x-text="row.code"></span>
                                </label>
                                <template x-if="row.enabled">
                                    <div class="flex items-center gap-2">
                                        <input type="hidden" :name="'skills[' + index + '][id]'" :value="row.id">
                                        <select :name="'skills[' + index + '][level]'" x-model="row.level"
                                                class="form-input py-1 text-sm">
                                            <option value="1">Basic</option>
                                            <option value="2">Intermediate</option>
                                            <option value="3">Expert</option>
                                        </select>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
                @endif
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.users.index') }}" class="btn-touch btn-secondary">Cancel</a>
            <button type="submit" class="btn-touch btn-primary"
                    :disabled="password && (!passwordConfirmation || password !== passwordConfirmation)"
                    :class="{ 'opacity-50 cursor-not-allowed': password && (!passwordConfirmation || password !== passwordConfirmation) }">
                Update User
            </button>
        </div>
    </form>
</div>

<style>[x-cloak]{display:none!important}</style>
@endsection
