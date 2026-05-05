@extends('onboarding.layout', ['currentStep' => 2])

@section('content')
<h2 class="text-2xl font-bold text-gray-800 mb-2">Add a product type</h2>
<p class="text-gray-500 mb-6">What do you produce on <strong>{{ $line->name }}</strong>? Define your first product type.</p>

<form method="POST" action="{{ route('onboarding.step2') }}">
    @csrf

    <div class="space-y-4">
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Product Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" id="name" value="{{ old('name') }}"
                   class="form-input w-full" placeholder="e.g. Standard Widget" required>
            @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="code" class="block text-sm font-medium text-gray-700 mb-1">Code <span class="text-red-500">*</span></label>
            <input type="text" name="code" id="code" value="{{ old('code') }}"
                   class="form-input w-full" placeholder="e.g. WIDGET" required>
            <p class="text-xs text-gray-400 mt-1">Short unique identifier for this product type</p>
            @error('code') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="unit_of_measure" class="block text-sm font-medium text-gray-700 mb-1">Unit of Measure</label>
            <input type="text" name="unit_of_measure" id="unit_of_measure" value="{{ old('unit_of_measure', 'pcs') }}"
                   class="form-input w-full" placeholder="pcs">
            <p class="text-xs text-gray-400 mt-1">How you count production output (pcs, kg, m, etc.)</p>
            @error('unit_of_measure') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="flex justify-between mt-8">
        <a href="{{ route('onboarding.step1') }}" class="text-gray-500 hover:text-gray-700 text-sm flex items-center">
            ← Back
        </a>
        <button type="submit" class="btn-touch btn-primary">
            Next: Process Template →
        </button>
    </div>
</form>
@endsection
