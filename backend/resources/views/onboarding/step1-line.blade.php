@extends('onboarding.layout', ['currentStep' => 1])

@section('content')
<h2 class="text-2xl font-bold text-gray-800 mb-2">Create your first production line</h2>
<p class="text-gray-500 mb-6">A production line represents a physical or logical area where manufacturing happens.</p>

<form method="POST" action="{{ route('onboarding.step1') }}">
    @csrf

    <div class="space-y-4">
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Line Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" id="name" value="{{ old('name') }}"
                   class="form-input w-full" placeholder="e.g. Assembly Line 1" required>
            @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="code" class="block text-sm font-medium text-gray-700 mb-1">Code <span class="text-red-500">*</span></label>
            <input type="text" name="code" id="code" value="{{ old('code') }}"
                   class="form-input w-full" placeholder="e.g. LINE-1" required>
            <p class="text-xs text-gray-400 mt-1">Short unique identifier (used in reports and API)</p>
            @error('code') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" id="description" rows="2"
                      class="form-input w-full" placeholder="Optional description...">{{ old('description') }}</textarea>
            @error('description') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="flex justify-end mt-8">
        <button type="submit" class="btn-touch btn-primary">
            Next: Product Type →
        </button>
    </div>
</form>
@endsection
