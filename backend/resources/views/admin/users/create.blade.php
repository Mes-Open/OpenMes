@extends('layouts.app')

@section('title', 'Create User')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('admin.users.index') }}" class="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Users
        </a>
        <h1 class="text-3xl font-bold text-gray-800">Create New User</h1>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.users.store') }}" x-data="{
            password: '',
            passwordConfirmation: '',
            showPassword: false,
            showConfirm: false,
            accountType: '{{ old('account_type', 'user') }}'
        }">
            @csrf

            <!-- Name -->
            <div class="mb-6">
                <label for="name" class="form-label">Full Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="{{ old('name') }}"
                    class="form-input w-full @error('name') border-red-500 @enderror"
                    required
                    autofocus
                >
                @error('name')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Username -->
            <div class="mb-6">
                <label for="username" class="form-label">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    value="{{ old('username') }}"
                    class="form-input w-full @error('username') border-red-500 @enderror"
                    required
                >
                <p class="text-sm text-gray-500 mt-1">Used for login</p>
                @error('username')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Email -->
            <div class="mb-6">
                <label for="email" class="form-label">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email') }}"
                    class="form-input w-full @error('email') border-red-500 @enderror"
                    required
                >
                @error('email')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Account Type -->
            <div class="mb-6">
                <label for="account_type" class="form-label">Account Type</label>
                <select
                    id="account_type"
                    name="account_type"
                    x-model="accountType"
                    class="form-input w-full @error('account_type') border-red-500 @enderror"
                    required
                >
                    <option value="user">User Account (Personal)</option>
                    <option value="workstation">Workstation Account (Shared)</option>
                </select>
                <p class="text-sm text-gray-500 mt-1">
                    <span x-show="accountType === 'user'">Personal account for individual users</span>
                    <span x-show="accountType === 'workstation'">Shared account for a specific workstation</span>
                </p>
                @error('account_type')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Workstation (only for workstation accounts) -->
            <div class="mb-6" x-show="accountType === 'workstation'" x-cloak>
                <label for="workstation_id" class="form-label">Workstation</label>
                <select
                    id="workstation_id"
                    name="workstation_id"
                    class="form-input w-full @error('workstation_id') border-red-500 @enderror"
                    :required="accountType === 'workstation'"
                >
                    <option value="">Select a workstation</option>
                    @foreach($workstations as $workstation)
                        <option value="{{ $workstation->id }}" {{ old('workstation_id') == $workstation->id ? 'selected' : '' }}>
                            {{ $workstation->name }} ({{ $workstation->line->name }})
                        </option>
                    @endforeach
                </select>
                <p class="text-sm text-gray-500 mt-1">Assign this account to a specific workstation</p>
                @error('workstation_id')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Role -->
            <div class="mb-6">
                <label for="role" class="form-label">Role</label>
                <select
                    id="role"
                    name="role"
                    class="form-input w-full @error('role') border-red-500 @enderror"
                    required
                >
                    <option value="">Select a role</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}" {{ old('role') === $role->name ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
                @error('role')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Password -->
            <div class="mb-6">
                <label for="password" class="form-label">Password</label>
                <div class="relative">
                    <input
                        :type="showPassword ? 'text' : 'password'"
                        id="password"
                        name="password"
                        x-model="password"
                        class="form-input w-full pr-12 @error('password') border-red-500 @enderror"
                        required
                        minlength="8"
                    >
                    <button
                        type="button"
                        @click="showPassword = !showPassword"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                    >
                        <svg x-show="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg x-show="showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                        </svg>
                    </button>
                </div>
                <p class="text-sm text-gray-500 mt-1">Minimum 8 characters</p>
                @error('password')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Confirm Password -->
            <div class="mb-6">
                <label for="password_confirmation" class="form-label">Confirm Password</label>
                <div class="relative">
                    <input
                        :type="showConfirm ? 'text' : 'password'"
                        id="password_confirmation"
                        name="password_confirmation"
                        x-model="passwordConfirmation"
                        class="form-input w-full pr-12"
                        required
                        minlength="8"
                    >
                    <button
                        type="button"
                        @click="showConfirm = !showConfirm"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                    >
                        <svg x-show="!showConfirm" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg x-show="showConfirm" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                        </svg>
                    </button>
                </div>
                <p class="text-sm mt-1" :class="password && passwordConfirmation && password === passwordConfirmation ? 'text-green-600' : 'text-gray-500'">
                    <span x-show="!passwordConfirmation">Re-enter password</span>
                    <span x-show="passwordConfirmation && password !== passwordConfirmation" class="text-red-600">Passwords do not match</span>
                    <span x-show="password && passwordConfirmation && password === passwordConfirmation">✓ Passwords match</span>
                </p>
            </div>

            <!-- Force Password Change -->
            <div class="mb-6">
                <label class="flex items-center">
                    <input
                        type="checkbox"
                        name="force_password_change"
                        value="1"
                        class="h-5 w-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
                        {{ old('force_password_change') ? 'checked' : '' }}
                    >
                    <span class="ml-2 text-sm text-gray-700">Force user to change password on first login</span>
                </label>
            </div>

            <!-- Buttons -->
            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.users.index') }}" class="btn-touch btn-secondary">
                    Cancel
                </a>
                <button
                    type="submit"
                    class="btn-touch btn-primary"
                    :disabled="!password || !passwordConfirmation || password !== passwordConfirmation"
                    :class="{ 'opacity-50 cursor-not-allowed': !password || !passwordConfirmation || password !== passwordConfirmation }"
                >
                    Create User
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
</style>
@endsection
