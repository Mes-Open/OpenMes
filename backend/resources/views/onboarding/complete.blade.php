@extends('onboarding.layout', ['currentStep' => 5])

@section('content')
<div class="text-center">
    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
    </div>

    <h2 class="text-2xl font-bold text-gray-800 mb-2">You're all set!</h2>
    <p class="text-gray-500 mb-6">Your manufacturing system is ready to use.</p>

    <div class="bg-gray-50 rounded-lg p-4 text-left mb-6 space-y-2">
        <h3 class="font-semibold text-gray-700 mb-2">What was created:</h3>
        <ul class="text-sm text-gray-600 space-y-1">
            @if($line)
                <li class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    Production line: <strong>{{ $line->name }}</strong>
                </li>
            @endif
            @if($productType)
                <li class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    Product type: <strong>{{ $productType->name }}</strong>
                </li>
            @endif
            <li class="flex items-center gap-2">
                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                Process template with production steps
            </li>
            <li class="flex items-center gap-2">
                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                First work order (PENDING)
            </li>
        </ul>
    </div>

    <div class="flex flex-col sm:flex-row gap-3 justify-center">
        <a href="{{ route('admin.dashboard') }}" class="btn-touch btn-primary">
            Go to Dashboard
        </a>
        <a href="{{ route('admin.work-orders.index') }}" class="btn-touch bg-gray-100 text-gray-700 hover:bg-gray-200">
            View Work Orders
        </a>
    </div>
</div>
@endsection
