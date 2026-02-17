@extends('layouts.app')

@section('title', 'Change Password')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('settings.index') }}" class="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Settings
        </a>
        <h1 class="text-3xl font-bold text-gray-800">Change Password</h1>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('settings.update-password') }}" x-data="{
            currentPassword: '',
            password: '',
            passwordConfirmation: '',
            showCurrent: false,
            showNew: false,
            showConfirm: false
        }">
            @csrf

            <!-- Current Password -->
            <div class="mb-6">
                <label for="current_password" class="form-label">Current Password</label>
                <div class="relative">
                    <input
                        :type="showCurrent ? 'text' : 'password'"
                        id="current_password"
                        name="current_password"
                        x-model="currentPassword"
                        class="form-input w-full pr-12 @error('current_password') border-red-500 @enderror"
                        required
                        autocomplete="current-password"
                    >
                    <button
                        type="button"
                        @click="showCurrent = !showCurrent"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                    >
                        <svg x-show="!showCurrent" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg x-show="showCurrent" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                        </svg>
                    </button>
                </div>
                @error('current_password')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- New Password -->
            <div class="mb-6">
                <label for="password" class="form-label">New Password</label>
                <div class="relative">
                    <input
                        :type="showNew ? 'text' : 'password'"
                        id="password"
                        name="password"
                        x-model="password"
                        class="form-input w-full pr-12 @error('password') border-red-500 @enderror"
                        required
                        autocomplete="new-password"
                        minlength="8"
                    >
                    <button
                        type="button"
                        @click="showNew = !showNew"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                    >
                        <svg x-show="!showNew" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg x-show="showNew" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                <label for="password_confirmation" class="form-label">Confirm New Password</label>
                <div class="relative">
                    <input
                        :type="showConfirm ? 'text' : 'password'"
                        id="password_confirmation"
                        name="password_confirmation"
                        x-model="passwordConfirmation"
                        class="form-input w-full pr-12"
                        required
                        autocomplete="new-password"
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
                    <span x-show="!passwordConfirmation">Re-enter your password</span>
                    <span x-show="passwordConfirmation && password !== passwordConfirmation" class="text-red-600">Passwords do not match</span>
                    <span x-show="password && passwordConfirmation && password === passwordConfirmation">✓ Passwords match</span>
                </p>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end gap-3">
                <a href="{{ route('settings.index') }}" class="btn-touch btn-secondary">
                    Cancel
                </a>
                <button
                    type="submit"
                    class="btn-touch btn-primary"
                    :disabled="!currentPassword || !password || !passwordConfirmation || password !== passwordConfirmation"
                    :class="{ 'opacity-50 cursor-not-allowed': !currentPassword || !password || !passwordConfirmation || password !== passwordConfirmation }"
                >
                    Change Password
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
