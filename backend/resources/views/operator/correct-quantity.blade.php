@extends('layouts.app')

@section('title', __('Correct Quantity'))

@section('content')
<div class="max-w-md mx-auto mt-8">
    <div class="card">
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('operator.workstation') }}" class="text-gray-500 hover:text-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-800">{{ __('Correct Quantity') }}</h1>
                <p class="text-sm text-gray-500">{{ __('Modify a previously reported production quantity.') }}</p>
            </div>
        </div>

        <div class="space-y-3 mb-6">
            <div class="flex justify-between border-b border-gray-200 pb-2">
                <span class="text-sm text-gray-500">{{ __('Order No') }}</span>
                <span class="text-sm font-bold font-mono">{{ $workOrder->order_no }}</span>
            </div>
            <div class="flex justify-between border-b border-gray-200 pb-2">
                <span class="text-sm text-gray-500">{{ __('Product') }}</span>
                <span class="text-sm font-medium">{{ $workOrder->productType?->name ?? '—' }}</span>
            </div>
            <div class="flex justify-between border-b border-gray-200 pb-2">
                <span class="text-sm text-gray-500">{{ __('Shift') }}</span>
                <span class="text-sm font-medium">{{ $shiftEntry->shift->name ?? $shiftEntry->shift->code }}</span>
            </div>
            <div class="flex justify-between border-b border-gray-200 pb-2">
                <span class="text-sm text-gray-500">{{ __('Production Date') }}</span>
                <span class="text-sm font-medium">{{ $shiftEntry->production_date->format('Y-m-d') }}</span>
            </div>
            <div class="flex justify-between border-b border-gray-200 pb-2">
                <span class="text-sm text-gray-500">{{ __('Current Quantity') }}</span>
                <span class="text-sm font-bold text-blue-600">{{ number_format((float) $shiftEntry->quantity, 0) }}</span>
            </div>
        </div>

        <form method="POST" action="{{ route('operator.shift-entry.correct.update', $shiftEntry) }}">
            @csrf
            @method('PUT')

            <div class="mb-5">
                <label class="form-label">{{ __('New Quantity') }} <span class="text-red-500">*</span></label>
                <input type="number" name="quantity"
                       value="{{ old('quantity', (int) $shiftEntry->quantity) }}"
                       class="form-input w-full text-2xl font-bold text-center py-3 tabular-nums"
                       min="0" max="99999999" step="1" required autofocus inputmode="numeric">
                @error('quantity')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex gap-3">
                <a href="{{ route('operator.workstation') }}"
                   class="btn-touch btn-secondary flex-1 py-3 text-base text-center">{{ __('Cancel') }}</a>
                <button type="submit"
                        class="btn-touch flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg py-3 text-base">
                    {{ __('Save Correction') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
