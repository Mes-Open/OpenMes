@extends('layouts.app')

@section('title', 'Change Password')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="card">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Change Password</h1>
            <p class="text-gray-600 mt-2">Update your password to keep your account secure.</p>

            @if(auth()->user()->force_password_change)
                <div class="mt-4 p-4 bg-yellow-100 border border-yellow-400 text-yellow-800 rounded-lg">
                    <strong>Action Required:</strong> You must change your password before continuing.
                </div>
            @endif
        </div>

        <form method="POST" action="{{ route('change-password') }}" x-data="{
            currentPassword: '',
            newPassword: '',
            newPasswordConfirmation: '',
            loading: false,
            showCurrentPassword: false,
            showNewPassword: false,
            showConfirmPassword: false
        }">
            @csrf

            <!-- Current Password -->
            <div class="mb-4">
                <label for="current_password" class="form-label">Current Password</label>
                <div class="relative">
                    <input
                        :type="showCurrentPassword ? 'text' : 'password'"
                        id="current_password"
                        name="current_password"
                        x-model="currentPassword"
                        class="form-input w-full pr-12 @error('current_password') border-red-500 @enderror"
                        autocomplete="current-password"
                        required
                    >
                    <button
                        type="button"
                        @click="showCurrentPassword = !showCurrentPassword"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                    >
                        <svg x-show="!showCurrentPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg x-show="showCurrentPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                        </svg>
                    </button>
                </div>
                @error('current_password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- New Password -->
            <div class="mb-4">
                <label for="new_password" class="form-label">New Password</label>
                <div class="relative">
                    <input
                        :type="showNewPassword ? 'text' : 'password'"
                        id="new_password"
                        name="new_password"
                        x-model="newPassword"
                        class="form-input w-full pr-12 @error('new_password') border-red-500 @enderror"
                        autocomplete="new-password"
                        minlength="8"
                        required
                    >
                    <button
                        type="button"
                        @click="showNewPassword = !showNewPassword"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                    >
                        <svg x-show="!showNewPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg x-show="showNewPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                        </svg>
                    </button>
                </div>
                <p class="mt-1 text-sm text-gray-500">Minimum 8 characters</p>
                @error('new_password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Confirm New Password -->
            <div class="mb-6">
                <label for="new_password_confirmation" class="form-label">Confirm New Password</label>
                <div class="relative">
                    <input
                        :type="showConfirmPassword ? 'text' : 'password'"
                        id="new_password_confirmation"
                        name="new_password_confirmation"
                        x-model="newPasswordConfirmation"
                        class="form-input w-full pr-12"
                        autocomplete="new-password"
                        minlength="8"
                        required
                    >
                    <button
                        type="button"
                        @click="showConfirmPassword = !showConfirmPassword"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                    >
                        <svg x-show="!showConfirmPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg x-show="showConfirmPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                        </svg>
                    </button>
                </div>
                <p class="mt-1 text-sm" :class="newPassword && newPasswordConfirmation && newPassword === newPasswordConfirmation ? 'text-green-600' : 'text-gray-500'">
                    <span x-show="!newPasswordConfirmation">Re-enter your new password</span>
                    <span x-show="newPasswordConfirmation && newPassword !== newPasswordConfirmation" class="text-red-600">Passwords do not match</span>
                    <span x-show="newPassword && newPasswordConfirmation && newPassword === newPasswordConfirmation">✓ Passwords match</span>
                </p>
            </div>

            <!-- Actions -->
            <div class="flex flex-col sm:flex-row gap-3">
                <button
                    type="submit"
                    class="btn-touch btn-primary flex-1"
                    :disabled="loading || !currentPassword || !newPassword || !newPasswordConfirmation || newPassword !== newPasswordConfirmation"
                    :class="{ 'opacity-50 cursor-not-allowed': loading || !currentPassword || !newPassword || !newPasswordConfirmation || newPassword !== newPasswordConfirmation }"
                    @click="loading = true"
                >
                    <span x-show="!loading">Change Password</span>
                    <span x-show="loading">Changing...</span>
                </button>

                @if(!auth()->user()->force_password_change)
                    <a href="{{ route('operator.select-line') }}" class="btn-touch btn-secondary flex-1 text-center">
                        Cancel
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>
@endsection
