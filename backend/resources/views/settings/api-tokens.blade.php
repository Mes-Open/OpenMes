@extends('layouts.app')

@section('title', __('API Tokens'))

@section('content')
<div class="max-w-4xl mx-auto">

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('settings.index') }}" class="text-gray-500 hover:text-gray-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-800">{{ __('API Tokens') }}</h1>
            <p class="text-gray-500 text-sm mt-0.5">{{ __('Manage personal access tokens for external integrations') }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if(session('new_token'))
        <div class="mb-6 p-4 bg-yellow-50 border border-yellow-300 rounded-lg" x-data="{ copied: false }">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-yellow-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                <div class="flex-1">
                    <p class="font-semibold text-yellow-800 mb-1">Token created: {{ session('new_token_name') }}</p>
                    <p class="text-yellow-700 text-sm mb-3">Copy this token now — it will not be shown again.</p>
                    <div class="flex items-center gap-2">
                        <code id="new-token" class="flex-1 bg-white border border-yellow-300 rounded px-3 py-2 text-sm font-mono break-all text-gray-800">{{ session('new_token') }}</code>
                        <button type="button"
                            x-on:click="navigator.clipboard.writeText('{{ session('new_token') }}'); copied = true; setTimeout(() => copied = false, 2000)"
                            class="btn-secondary text-sm flex-shrink-0 flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    {{-- Create Token --}}
    <div class="card mb-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">{{ __('Generate New Token') }}</h2>
        <form method="POST" action="{{ route('settings.api-tokens.create') }}" class="flex items-end gap-3">
            @csrf
            <div class="flex-1">
                <label class="form-label" for="name">{{ __('Token Name') }}</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}"
                    class="form-input" placeholder="e.g. PrestaShop Integration" required>
            </div>
            <button type="submit" class="btn-touch btn-primary">{{ __('Generate Token') }}</button>
        </form>
    </div>

    {{-- Token List --}}
    <div class="card">
        <h2 class="text-lg font-bold text-gray-800 mb-4">{{ __('Active Tokens') }}</h2>

        @if($tokens->isEmpty())
            <p class="text-gray-500 text-sm py-4 text-center">No tokens generated yet.</p>
        @else
            <div class="space-y-3">
                @foreach($tokens as $token)
                    <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                        <div class="flex-1">
                            <p class="font-medium text-gray-800">{{ $token->name }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ __('Created by') }} {{ $token->tokenable->name ?? 'Unknown' }}
                                &middot; {{ $token->created_at->translatedFormat('d M Y, H:i') }}
                                @if($token->last_used_at)
                                    &middot; {{ __('Last used') }} {{ $token->last_used_at->diffForHumans() }}
                                @else
                                    &middot; Never used
                                @endif
                            </p>
                        </div>
                        <form method="POST" action="{{ route('settings.api-tokens.revoke', $token) }}"
                            onsubmit="return confirm('Revoke token \'{{ $token->name }}\'? This cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium">
                                Revoke
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Usage Info --}}
    <div class="card mt-6">
        <h2 class="text-lg font-bold text-gray-800 mb-3">How to use</h2>
        <p class="text-sm text-gray-600 mb-3">Include the token in the <code class="bg-gray-100 px-1 rounded">Authorization</code> header for all API requests:</p>
        <pre class="bg-gray-800 text-green-400 text-sm rounded-lg p-4 overflow-x-auto">Authorization: Bearer &lt;your-token&gt;

# Example — create a work order:
POST {{ config('app.url') }}/api/v1/work-orders
Content-Type: application/json
Authorization: Bearer &lt;your-token&gt;

{
  "order_no": "PS-0001234",
  "planned_qty": 5,
  "description": "From PrestaShop order #1234"
}</pre>
    </div>

</div>
@endsection
