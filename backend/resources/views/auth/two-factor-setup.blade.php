@extends('layouts.app')

@section('title', __('Enable Two-Factor Authentication'))

@section('content')
<div class="max-w-lg mx-auto">
    <div class="mb-6">
        <a href="{{ route('settings.profile') }}" class="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            {{ __('Back to Profile') }}
        </a>
        <h1 class="text-3xl font-bold text-gray-800">{{ __('Enable Two-Factor Authentication') }}</h1>
        <p class="text-gray-600 mt-2">{{ __('Scan the QR code below with your authenticator app (Google Authenticator, Authy, Microsoft Authenticator).') }}</p>
    </div>

    <div class="card">
        {{-- QR Code --}}
        <div class="text-center mb-6">
            <img src="{{ $qrCodeDataUri }}" alt="QR Code" class="mx-auto rounded-lg shadow-sm border border-gray-200">
        </div>

        {{-- Manual entry key --}}
        <div class="mb-6 bg-gray-50 rounded-lg p-4">
            <p class="text-sm text-gray-600 mb-2">{{ __("Can't scan? Enter this key manually:") }}</p>
            <div class="flex items-center gap-2">
                <code class="flex-1 bg-white border border-gray-200 rounded px-3 py-2 text-sm font-mono tracking-widest text-gray-800 select-all">{{ $secret }}</code>
                <button type="button" onclick="navigator.clipboard.writeText('{{ $secret }}').then(() => this.textContent = 'Copied!')"
                        class="px-3 py-2 text-sm font-medium text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                    {{ __('Copy') }}
                </button>
            </div>
        </div>

        {{-- Confirmation form --}}
        <form method="POST" action="{{ route('settings.two-factor.confirm') }}">
            @csrf
            <div class="mb-4">
                <label for="code" class="form-label">{{ __('Verification Code') }}</label>
                <p class="text-sm text-gray-500 mb-2">{{ __('Enter the 6-digit code from your authenticator app to verify setup.') }}</p>
                <input type="text" id="code" name="code"
                       class="form-input w-full text-center text-2xl font-mono tracking-[0.5em] @error('code') border-red-500 @enderror"
                       placeholder="000000"
                       maxlength="6" inputmode="numeric" pattern="[0-9]{6}"
                       autocomplete="one-time-code" autofocus required>
                @error('code')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="btn-touch btn-primary w-full">
                {{ __('Verify & Enable') }}
            </button>
        </form>
    </div>
</div>

<script>
document.getElementById('code')?.addEventListener('input', function(e) {
    this.value = this.value.replace(/\D/g, '').slice(0, 6);
    if (this.value.length === 6) {
        this.closest('form').submit();
    }
});
</script>
@endsection
