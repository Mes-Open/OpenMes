@extends('onboarding.layout', ['step' => 5])

@section('content')
<div class="text-center py-8">
    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
    </div>
    <h2 class="text-2xl font-bold text-gray-800 mb-2">Setup Complete!</h2>
    <p class="text-gray-600 mb-6">Your production line, product type, process template, and first work order have been created.</p>

    <div class="space-y-3">
        <a href="{{ route('admin.dashboard') }}" class="btn-touch btn-primary block">Go to Dashboard</a>
        <a href="{{ route('operator.select-line') }}" class="btn-touch btn-secondary block">Start as Operator</a>
    </div>
</div>
@endsection
