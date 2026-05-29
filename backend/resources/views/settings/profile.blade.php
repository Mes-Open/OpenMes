@extends('layouts.app')

@section('title', __('Profile'))

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('settings.index') }}" class="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            {{ __('Back') }}
        </a>
        <h1 class="text-3xl font-bold text-gray-800">{{ __('Profile') }}</h1>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('settings.update-profile') }}">
            @csrf

            <!-- Profile Picture Placeholder -->
            <div class="mb-6 flex items-center gap-4">
                <div class="flex-shrink-0 h-20 w-20 bg-blue-100 rounded-full flex items-center justify-center">
                    <span class="text-blue-600 font-bold text-3xl">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </span>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-800">{{ auth()->user()->username }}</h3>
                    <p class="text-sm text-gray-500">{{ auth()->user()->roles->first()->name ?? 'User' }}</p>
                </div>
            </div>

            <!-- Name -->
            <div class="mb-6">
                <label for="name" class="form-label">{{ __('Name') }}</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="{{ old('name', auth()->user()->name) }}"
                    class="form-input w-full @error('name') border-red-500 @enderror"
                    required
                    autofocus
                >
                @error('name')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Email -->
            <div class="mb-6">
                <label for="email" class="form-label">{{ __('Email') }}</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email', auth()->user()->email) }}"
                    class="form-input w-full @error('email') border-red-500 @enderror"
                    required
                >
                @error('email')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Read-only fields -->
            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('Account Information') }}</h3>
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">{{ __('Username') }}:</span>
                        <span class="font-medium text-gray-800">{{ auth()->user()->username }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">{{ __('Role') }}:</span>
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-medium">
                            {{ auth()->user()->roles->first()->name ?? 'User' }}
                        </span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">{{ __('Member Since') }}:</span>
                        <span class="font-medium text-gray-800">{{ auth()->user()->created_at->translatedFormat('F d, Y') }}</span>
                    </div>
                </div>
            </div>

            <!-- Info Note -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <div class="flex gap-3">
                    <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="text-sm text-blue-800">
                        <p class="font-semibold mb-1">{{ __('Note') }}:</p>
                        <p>{{ __('To change your username or role, contact an administrator.') }}</p>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end gap-3">
                <a href="{{ route('settings.index') }}" class="btn-touch btn-secondary">
                    Cancel
                </a>
                <button type="submit" class="btn-touch btn-primary">
                    {{ __('Save') }}
                </button>
            </div>
        </form>
    </div>

    {{-- Two-Factor Authentication --}}
    <div class="card mt-6">
        <h2 class="text-xl font-bold text-gray-800 mb-2">{{ __('Two-Factor Authentication') }}</h2>
        <p class="text-sm text-gray-500 mb-4">{{ __('Add an extra layer of security to your account using a TOTP authenticator app.') }}</p>

        @if(auth()->user()->two_factor_enabled)
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    <div>
                        <p class="font-semibold text-green-800">{{ __('2FA is enabled') }}</p>
                        <p class="text-sm text-green-700">{{ __('Enabled') }} {{ auth()->user()->two_factor_confirmed_at?->diffForHumans() }}</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                {{-- Regenerate recovery codes --}}
                <div x-data="{ open: false }">
                    <button type="button" @click="open = true" class="btn-touch btn-secondary text-sm">
                        {{ __('Regenerate Recovery Codes') }}
                    </button>
                    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                        <div class="fixed inset-0 bg-black/50" @click="open = false"></div>
                        <div class="relative bg-white rounded-xl shadow-2xl max-w-sm w-full p-6" @click.stop>
                            <h3 class="text-lg font-bold mb-3">{{ __('Regenerate Recovery Codes') }}</h3>
                            <p class="text-sm text-gray-600 mb-4">{{ __('This will invalidate your existing recovery codes. Enter your password to continue.') }}</p>
                            <form method="POST" action="{{ route('settings.two-factor.recovery-codes') }}">
                                @csrf
                                <input type="password" name="password" class="form-input w-full mb-4" placeholder="{{ __('Current password') }}" required>
                                @error('password')<p class="text-red-600 text-sm mb-2">{{ $message }}</p>@enderror
                                <div class="flex gap-3">
                                    <button type="button" @click="open = false" class="btn-touch btn-secondary flex-1">{{ __('Cancel') }}</button>
                                    <button type="submit" class="btn-touch btn-primary flex-1">{{ __('Regenerate') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Disable 2FA --}}
                <div x-data="{ open: false }">
                    <button type="button" @click="open = true" class="btn-touch text-sm text-red-600 border border-red-200 hover:bg-red-50 rounded-lg px-4 py-2">
                        {{ __('Disable 2FA') }}
                    </button>
                    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                        <div class="fixed inset-0 bg-black/50" @click="open = false"></div>
                        <div class="relative bg-white rounded-xl shadow-2xl max-w-sm w-full p-6" @click.stop>
                            <h3 class="text-lg font-bold text-red-700 mb-3">{{ __('Disable Two-Factor Authentication') }}</h3>
                            <p class="text-sm text-gray-600 mb-4">{{ __('Your account will be less secure. Enter your password to confirm.') }}</p>
                            <form method="POST" action="{{ route('settings.two-factor.disable') }}">
                                @csrf
                                <input type="password" name="password" class="form-input w-full mb-4" placeholder="{{ __('Current password') }}" required>
                                @error('password')<p class="text-red-600 text-sm mb-2">{{ $message }}</p>@enderror
                                <div class="flex gap-3">
                                    <button type="button" @click="open = false" class="btn-touch btn-secondary flex-1">{{ __('Cancel') }}</button>
                                    <button type="submit" class="btn-touch flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg">{{ __('Disable') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    <div>
                        <p class="font-semibold text-gray-700">{{ __('2FA is not enabled') }}</p>
                        <p class="text-sm text-gray-500">{{ __('Protect your account with a TOTP authenticator app.') }}</p>
                    </div>
                </div>
            </div>
            <a href="{{ route('settings.two-factor.enable') }}" class="btn-touch btn-primary inline-flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                {{ __('Enable Two-Factor Authentication') }}
            </a>
        @endif
    </div>
</div>
@endsection
