@extends('layouts.app')

@section('title', __('Recovery Codes'))

@section('content')
<div class="max-w-lg mx-auto">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">{{ __('Recovery Codes') }}</h1>
        <p class="text-gray-600 mt-2">{{ __('Two-factor authentication is now enabled.') }}</p>
    </div>

    <div class="card">
        {{-- Warning --}}
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6">
            <div class="flex gap-3">
                <svg class="w-6 h-6 text-amber-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.75-2.97L12.75 4.97a2 2 0 00-3.5 0l-7 12A2 2 0 005.07 19z"/>
                </svg>
                <div class="text-sm text-amber-800">
                    <p class="font-bold mb-1">{{ __('Save these codes in a safe place!') }}</p>
                    <p>{{ __('Each recovery code can only be used once. If you lose your authenticator device, use one of these codes to log in.') }}</p>
                </div>
            </div>
        </div>

        {{-- Codes grid --}}
        <div id="recovery-codes" class="grid grid-cols-2 gap-3 mb-6">
            @foreach($recoveryCodes as $code)
            <div class="bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 text-center">
                <code class="font-mono text-sm font-semibold text-gray-800 tracking-wider">{{ $code }}</code>
            </div>
            @endforeach
        </div>

        {{-- Actions --}}
        <div class="flex gap-3 mb-6">
            <button type="button" onclick="downloadCodes()" class="btn-touch btn-secondary flex-1">
                <svg class="w-4 h-4 inline mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                {{ __('Download') }}
            </button>
            <button type="button" onclick="copyCodes(this)" class="btn-touch btn-secondary flex-1">
                <svg class="w-4 h-4 inline mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                </svg>
                {{ __('Copy') }}
            </button>
        </div>

        <a href="{{ route('settings.profile') }}" class="btn-touch btn-primary w-full text-center block">
            {{ __('Done') }}
        </a>
    </div>
</div>

<script>
const codes = @json($recoveryCodes->toArray());

function downloadCodes() {
    const text = "OpenMES Recovery Codes\n" +
                 "Generated: {{ now()->format('Y-m-d H:i') }}\n" +
                 "=========================\n\n" +
                 codes.join("\n") +
                 "\n\nEach code can only be used once.";
    const blob = new Blob([text], { type: 'text/plain' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'openmes-recovery-codes.txt';
    a.click();
}

function copyCodes(btn) {
    navigator.clipboard.writeText(codes.join("\n")).then(() => {
        btn.textContent = '{{ __("Copied!") }}';
        setTimeout(() => btn.innerHTML = '<svg class="w-4 h-4 inline mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg> {{ __("Copy") }}', 2000);
    });
}
</script>
@endsection
