@extends('onboarding.layout', ['step' => 1])

@section('content')
<h2 class="text-2xl font-bold text-gray-800 mb-2">Create a Production Line</h2>
<p class="text-gray-600 mb-6">A production line is a physical area where manufacturing happens. Start by creating your first one.</p>

<form method="POST" action="{{ route('onboarding.step1') }}">
    @csrf
    <div class="space-y-4">
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Line Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                class="input-field @error('name') border-red-500 @enderror" placeholder="e.g. Injection Line 1">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="code" class="block text-sm font-medium text-gray-700 mb-1">Code <span class="text-red-500">*</span></label>
            <input type="text" name="code" id="code" value="{{ old('code') }}" required
                class="input-field @error('code') border-red-500 @enderror" placeholder="e.g. INJ-01">
            @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" id="description" rows="2" class="input-field" placeholder="Optional description">{{ old('description') }}</textarea>
        </div>
    </div>
    <div class="flex justify-end mt-6">
        <button type="submit" class="btn-touch btn-primary">Next: Product Type →</button>
    </div>
</form>
@endsection
