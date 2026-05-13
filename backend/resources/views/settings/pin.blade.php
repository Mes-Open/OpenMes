@extends('layouts.app')

@section('title', __('PIN Setup'))

@section('content')
<div class="max-w-lg mx-auto">

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('settings.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">{{ __('Quick PIN Login') }}</h1>
            <p class="text-gray-500 dark:text-gray-400 text-sm mt-0.5">{{ __('Set a 4–6 digit PIN for fast sign-in') }}</p>
        </div>
    </div>

    @if($hasPin)
        {{-- Current PIN status --}}
        <div class="card mb-6 border-l-4 border-green-400">
            <div class="flex items-center gap-3">
                <div class="bg-green-100 dark:bg-green-900/30 rounded-full p-2">
                    <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div>
                    <p class="font-medium text-gray-800 dark:text-gray-100">{{ __('PIN is active') }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('You can log in using your username and PIN.') }}</p>
                </div>
            </div>
        </div>

        {{-- Remove PIN --}}
        <div class="card mb-6" x-data="{ showRemove: false }">
            <button @click="showRemove = !showRemove"
                    class="text-sm text-red-600 dark:text-red-400 hover:underline font-medium">
                {{ __('Remove PIN') }}
            </button>
            <form x-show="showRemove" x-cloak method="POST" action="{{ route('settings.remove-pin') }}" class="mt-4 space-y-4">
                @csrf
                @method('DELETE')
                <div>
                    <label for="rm_password" class="form-label">{{ __('Confirm your password') }}</label>
                    <input type="password" id="rm_password" name="current_password"
                           class="form-input w-full @error('current_password') border-red-500 @enderror" required>
                    @error('current_password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="btn-touch px-4 py-2 text-sm font-medium rounded-lg bg-red-600 text-white hover:bg-red-700">
                    {{ __('Remove PIN') }}
                </button>
            </form>
        </div>
    @endif

    {{-- Set / Change PIN form --}}
    <div class="card">
        <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">
            {{ $hasPin ? __('Change PIN') : __('Set PIN') }}
        </h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
            {{ __('Enter your current account password and choose a 4–6 digit numeric PIN.') }}
        </p>

        <form method="POST" action="{{ route('settings.update-pin') }}" class="space-y-4"
              x-data="{ pin: '', pinConfirm: '' }">
            @csrf

            <div>
                <label for="current_password" class="form-label">{{ __('Current Password') }}</label>
                <input type="password" id="current_password" name="current_password"
                       class="form-input w-full @error('current_password') border-red-500 @enderror"
                       required autocomplete="current-password">
                @error('current_password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="pin" class="form-label">{{ __('PIN (4–6 digits)') }}</label>
                <input type="password" id="pin" name="pin" inputmode="numeric" pattern="[0-9]*"
                       maxlength="6" x-model="pin"
                       class="form-input w-full text-center text-2xl tracking-[0.5em] @error('pin') border-red-500 @enderror"
                       required placeholder="----">
                @error('pin')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="pin_confirmation" class="form-label">{{ __('Confirm PIN') }}</label>
                <input type="password" id="pin_confirmation" name="pin_confirmation" inputmode="numeric" pattern="[0-9]*"
                       maxlength="6" x-model="pinConfirm"
                       class="form-input w-full text-center text-2xl tracking-[0.5em]"
                       required placeholder="----">
            </div>

            <div class="pt-2">
                <button type="submit"
                        class="w-full btn-touch btn-primary"
                        :disabled="pin.length < 4 || pin !== pinConfirm"
                        :class="{ 'opacity-50 cursor-not-allowed': pin.length < 4 || pin !== pinConfirm }">
                    {{ $hasPin ? __('Change PIN') : __('Set PIN') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
