@extends('layouts.auth')

@section('title', __('Two-Factor Authentication'))

@section('content')
<div x-data="{ mode: 'code', loading: false }">
    <h2 class="text-2xl font-bold text-gray-800 mb-2 text-center">{{ __('Two-Factor Authentication') }}</h2>
    <p class="text-gray-500 text-sm text-center mb-6" x-show="mode === 'code'">
        {{ __('Enter the 6-digit code from your authenticator app.') }}
    </p>
    <p class="text-gray-500 text-sm text-center mb-6" x-show="mode === 'recovery'" x-cloak>
        {{ __('Enter one of your recovery codes.') }}
    </p>

    {{-- TOTP Code form --}}
    <form method="POST" action="{{ route('two-factor.verify') }}"
          x-show="mode === 'code'"
          @submit="loading = true">
        @csrf
        <div class="mb-4">
            <input type="text" name="code" id="totp-code"
                   class="form-input w-full text-center text-3xl font-mono tracking-[0.5em] py-4
                          @error('code') border-red-500 @enderror"
                   placeholder="000000"
                   maxlength="6" inputmode="numeric" pattern="[0-9]{6}"
                   autocomplete="one-time-code" autofocus required>
            @error('code')
                <p class="text-red-600 text-sm mt-2 text-center">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn-touch btn-primary w-full py-3 text-base"
                :disabled="loading"
                :class="loading ? 'opacity-60 cursor-wait' : ''">
            <span x-show="!loading">{{ __('Verify') }}</span>
            <span x-show="loading" x-cloak>{{ __('Verifying...') }}</span>
        </button>
    </form>

    {{-- Recovery Code form --}}
    <form method="POST" action="{{ route('two-factor.verify') }}"
          x-show="mode === 'recovery'" x-cloak
          @submit="loading = true">
        @csrf
        <div class="mb-4">
            <input type="text" name="recovery_code"
                   class="form-input w-full text-center text-lg font-mono py-3
                          @error('recovery_code') border-red-500 @enderror"
                   placeholder="{{ __('Recovery code') }}"
                   autocomplete="off" required>
            @error('recovery_code')
                <p class="text-red-600 text-sm mt-2 text-center">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn-touch btn-primary w-full py-3 text-base"
                :disabled="loading"
                :class="loading ? 'opacity-60 cursor-wait' : ''">
            <span x-show="!loading">{{ __('Verify') }}</span>
            <span x-show="loading" x-cloak>{{ __('Verifying...') }}</span>
        </button>
    </form>

    {{-- Toggle link --}}
    <div class="mt-4 text-center">
        <button type="button" @click="mode = mode === 'code' ? 'recovery' : 'code'"
                class="text-sm text-blue-600 hover:text-blue-800 hover:underline">
            <span x-show="mode === 'code'">{{ __('Use a recovery code instead') }}</span>
            <span x-show="mode === 'recovery'" x-cloak>{{ __('Use authenticator code instead') }}</span>
        </button>
    </div>

    {{-- Back to login --}}
    <div class="mt-3 text-center">
        <a href="{{ route('login') }}" class="text-sm text-gray-500 hover:text-gray-700">
            {{ __('Back to login') }}
        </a>
    </div>
</div>

<script>
document.getElementById('totp-code')?.addEventListener('input', function(e) {
    this.value = this.value.replace(/\D/g, '').slice(0, 6);
    if (this.value.length === 6) {
        this.closest('form').submit();
    }
});
</script>
@endsection
