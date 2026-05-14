@extends('layouts.auth')

@section('title', __('Login'))

@section('content')
@php
    $pinRow = \Illuminate\Support\Facades\DB::table('system_settings')->where('key','pin_login_enabled')->first();
    $pinEnabled = json_decode($pinRow->value ?? 'false', true) === true;
@endphp

<div x-data="{
    tab: '{{ $pinEnabled ? 'password' : 'password' }}',
    username: '',
    password: '',
    pin: '',
    remember: false,
    loading: false
}">
    <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">{{ __('Sign In') }}</h2>

    @if($errors->has('session'))
        <div class="mb-4 p-3 bg-amber-50 border border-amber-300 text-amber-800 rounded-lg text-sm">
            {{ $errors->first('session') }}
        </div>
    @endif

    @if($pinEnabled)
    {{-- Tab switcher --}}
    <div class="flex rounded-lg bg-gray-100 p-1 mb-6">
        <button type="button" @click="tab = 'password'; loading = false"
                class="flex-1 py-2 text-sm font-medium rounded-md transition-colors"
                :class="tab === 'password' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
            {{ __('Password') }}
        </button>
        <button type="button" @click="tab = 'pin'; loading = false"
                class="flex-1 py-2 text-sm font-medium rounded-md transition-colors"
                :class="tab === 'pin' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
            {{ __('Quick PIN') }}
        </button>
    </div>
    @endif

    {{-- Password login form --}}
    <form x-show="tab === 'password'" method="POST" action="{{ route('login') }}" @submit="loading = true">
        @csrf

        <!-- Username -->
        <div class="mb-4">
            <label for="username" class="form-label">{{ __('Username') }}</label>
            <input
                type="text"
                id="username"
                name="username"
                x-model="username"
                class="form-input w-full @error('username') border-red-500 @enderror"
                autocomplete="username"
                autofocus
                required
            >
            @error('username')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Password -->
        <div class="mb-4">
            <label for="password" class="form-label">{{ __('Password') }}</label>
            <input
                type="password"
                id="password"
                name="password"
                x-model="password"
                class="form-input w-full @error('password') border-red-500 @enderror"
                autocomplete="current-password"
                required
            >
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Remember Me -->
        <div class="mb-6 flex items-center">
            <input
                type="checkbox"
                id="remember"
                name="remember"
                x-model="remember"
                class="h-5 w-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
            >
            <label for="remember" class="ml-2 text-sm text-gray-700">
                {{ __('Remember me') }}
            </label>
        </div>

        <!-- Submit Button -->
        <button
            type="submit"
            class="w-full btn-touch btn-primary"
            :disabled="loading || !username || !password"
            :class="{ 'opacity-50 cursor-not-allowed': loading || !username || !password }"
        >
            <span x-show="!loading">{{ __('Sign In') }}</span>
            <span x-show="loading" class="flex items-center justify-center">
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ __('Signing in...') }}
            </span>
        </button>
    </form>

    @if($pinEnabled)
    {{-- PIN login form --}}
    <form x-show="tab === 'pin'" x-cloak method="POST" action="{{ route('login.pin') }}" @submit="loading = true">
        @csrf

        <!-- Username -->
        <div class="mb-4">
            <label for="pin_username" class="form-label">{{ __('Username') }}</label>
            <input
                type="text"
                id="pin_username"
                name="username"
                x-model="username"
                class="form-input w-full @error('username') border-red-500 @enderror"
                autocomplete="username"
                required
            >
            @error('username')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- PIN -->
        <div class="mb-6">
            <label for="pin_input" class="form-label">{{ __('PIN') }}</label>
            <input
                type="password"
                id="pin_input"
                name="pin"
                x-model="pin"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="6"
                class="form-input w-full text-center text-2xl tracking-[0.5em] @error('pin') border-red-500 @enderror"
                autocomplete="off"
                placeholder="----"
                required
            >
            @error('pin')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-gray-500">{{ __('Enter your 4–6 digit PIN') }}</p>
        </div>

        <!-- Submit Button -->
        <button
            type="submit"
            class="w-full btn-touch btn-primary"
            :disabled="loading || !username || pin.length < 4"
            :class="{ 'opacity-50 cursor-not-allowed': loading || !username || pin.length < 4 }"
        >
            <span x-show="!loading">{{ __('Sign In') }} {{ __('with PIN') }}</span>
            <span x-show="loading" class="flex items-center justify-center">
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ __('Signing in...') }}
            </span>
        </button>

        <p class="mt-4 text-center text-xs text-gray-500">
            {{ __('No PIN yet? Log in with password first, then set your PIN in Settings.') }}
        </p>
    </form>
    @endif

    @php
        $regRow = \Illuminate\Support\Facades\DB::table('system_settings')->where('key','allow_registration')->first();
        $regEnabled = json_decode($regRow->value ?? 'false', true) === true;
    @endphp
    @if($regEnabled)
    <p class="mt-6 text-center text-sm text-gray-600 dark:text-gray-400">
        {{ __("Don't have an account?") }}
        <a href="{{ route('register') }}" class="text-blue-600 dark:text-blue-400 hover:underline font-medium">{{ __('Create account') }}</a>
    </p>
    @endif
</div>
@endsection
