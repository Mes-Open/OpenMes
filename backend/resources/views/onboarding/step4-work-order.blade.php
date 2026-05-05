@extends('onboarding.layout', ['step' => 4])

@section('content')
<h2 class="text-2xl font-bold text-gray-800 mb-2">Create First Work Order</h2>
<p class="text-gray-600 mb-6">A work order represents a production batch to manufacture. Create your first one.</p>

<form method="POST" action="{{ route('onboarding.step4') }}">
    @csrf
    <div class="space-y-4">
        <div>
            <label for="order_no" class="block text-sm font-medium text-gray-700 mb-1">Order Number <span class="text-red-500">*</span></label>
            <input type="text" name="order_no" id="order_no" value="{{ old('order_no', 'WO-'.now()->format('Y').'-001') }}" required
                class="input-field @error('order_no') border-red-500 @enderror">
            @error('order_no') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="planned_qty" class="block text-sm font-medium text-gray-700 mb-1">Planned Quantity <span class="text-red-500">*</span></label>
            <input type="number" name="planned_qty" id="planned_qty" value="{{ old('planned_qty', 100) }}" required
                step="0.01" min="0.01" class="input-field @error('planned_qty') border-red-500 @enderror">
            @error('planned_qty') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" id="description" rows="2" class="input-field" placeholder="Optional notes">{{ old('description') }}</textarea>
        </div>
    </div>
    <div class="flex justify-between mt-6">
        <a href="{{ route('onboarding.step3') }}" class="btn-touch btn-secondary">← Back</a>
        <button type="submit" class="btn-touch btn-primary">Complete Setup</button>
    </div>
</form>
@endsection
