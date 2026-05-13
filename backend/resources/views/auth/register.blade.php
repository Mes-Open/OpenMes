@extends('layouts.auth')

@section('title', __('Create Account'))

@section('content')
<div x-data="{
    name: '{{ old('name') }}',
    username: '{{ old('username') }}',
    email: '{{ old('email') }}',
    password: '',
    password_confirmation: '',
    loading: false
}">
    <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-4 text-center">{{ __('Create Account') }}</h2>

    <div class="mb-5 flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700 px-4 py-3 text-sm text-amber-800 dark:text-amber-300">
        <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span>{{ __('This is a') }} <strong>{{ __('demo account') }}</strong> — {{ __('it will be automatically deleted after') }} <strong>{{ __('3 hours') }}</strong>.</span>
    </div>

    @if (session('error'))
        <div class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-sm text-red-700 dark:text-red-400">
            {{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}" @submit="loading = true">
        @csrf

        <div class="mb-4">
            <label for="name" class="form-label">{{ __('Full Name') }}</label>
            <input type="text" id="name" name="name" x-model="name"
                value="{{ old('name') }}"
                class="form-input w-full @error('name') border-red-500 @enderror"
                autocomplete="name" autofocus required>
            @error('name')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="username" class="form-label">{{ __('Username') }}</label>
            <input type="text" id="username" name="username" x-model="username"
                value="{{ old('username') }}"
                class="form-input w-full @error('username') border-red-500 @enderror"
                autocomplete="username" required>
            @error('username')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="email" class="form-label">{{ __('Email') }}</label>
            <input type="email" id="email" name="email" x-model="email"
                value="{{ old('email') }}"
                class="form-input w-full @error('email') border-red-500 @enderror"
                autocomplete="email" required>
            @error('email')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="password" class="form-label">{{ __('Password') }}</label>
            <input type="password" id="password" name="password" x-model="password"
                class="form-input w-full @error('password') border-red-500 @enderror"
                autocomplete="new-password" required>
            @error('password')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="password_confirmation" class="form-label">{{ __('Confirm Password') }}</label>
            <input type="password" id="password_confirmation" name="password_confirmation"
                x-model="password_confirmation"
                class="form-input w-full @error('password_confirmation') border-red-500 @enderror"
                autocomplete="new-password" required>
            @error('password_confirmation')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-6">
            <label class="flex items-start gap-2 cursor-pointer">
                <input type="checkbox" name="marketing_consent" value="1"
                    {{ old('marketing_consent') ? 'checked' : '' }}
                    class="mt-1 h-4 w-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500">
                <span class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                    {{ __('I agree to receive product updates and marketing communications via email.') }}
                    <span class="text-gray-400 dark:text-gray-500">/ Wyrażam zgodę na kontakt w celach marketingowych drogą mailową.</span>
                </span>
            </label>
        </div>

        <button type="submit" class="w-full btn-touch btn-primary"
            :disabled="loading || !name || !username || !email || !password || !password_confirmation"
            :class="{ 'opacity-50 cursor-not-allowed': loading || !name || !username || !email || !password || !password_confirmation }">
            <span x-show="!loading">{{ __('Create Account') }}</span>
            <span x-show="loading" class="flex items-center justify-center">
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ __('Creating account...') }}
            </span>
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-600 dark:text-gray-400">
        {{ __('Already have an account?') }}
        <a href="{{ route('login') }}" class="text-blue-600 dark:text-blue-400 hover:underline font-medium">{{ __('Sign in') }}</a>
    </p>
</div>
@endsection
