@extends('onboarding.layout', ['currentStep' => 4])

@section('content')
<h2 class="text-2xl font-bold text-gray-800 mb-2">Create your first work order</h2>
<p class="text-gray-500 mb-6">A work order tells the shop floor what to produce. It will be assigned to <strong>{{ $line->name }}</strong> for <strong>{{ $productType->name }}</strong>.</p>

<form method="POST" action="{{ route('onboarding.step4') }}">
    @csrf

    <div class="space-y-4">
        <div>
            <label for="order_no" class="block text-sm font-medium text-gray-700 mb-1">Order Number <span class="text-red-500">*</span></label>
            <input type="text" name="order_no" id="order_no" value="{{ old('order_no', 'WO-' . date('Y') . '-001') }}"
                   class="form-input w-full" placeholder="e.g. WO-2026-001" required>
            <p class="text-xs text-gray-400 mt-1">Unique identifier for this work order</p>
            @error('order_no') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="planned_qty" class="block text-sm font-medium text-gray-700 mb-1">Planned Quantity <span class="text-red-500">*</span></label>
            <div class="flex items-center gap-2">
                <input type="number" name="planned_qty" id="planned_qty" value="{{ old('planned_qty', 10) }}"
                       class="form-input w-full" min="0.01" step="0.01" required>
                <span class="text-gray-500 text-sm flex-shrink-0">{{ $productType->unit_of_measure }}</span>
            </div>
            @error('planned_qty') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" id="description" rows="2"
                      class="form-input w-full" placeholder="Optional notes about this order...">{{ old('description') }}</textarea>
            @error('description') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mt-6">
        <p class="text-sm text-blue-800">
            <strong>Summary:</strong> This will create a work order on <strong>{{ $line->name }}</strong>
            for <strong>{{ $productType->name }}</strong> using the process template you just defined.
        </p>
    </div>

    <div class="flex justify-between mt-8">
        <a href="{{ route('onboarding.step3') }}" class="text-gray-500 hover:text-gray-700 text-sm flex items-center">
            ← Back
        </a>
        <button type="submit" class="btn-touch btn-primary">
            Finish Setup ✓
        </button>
    </div>
</form>
@endsection
