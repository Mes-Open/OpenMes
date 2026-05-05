@extends('onboarding.layout', ['step' => 2])

@section('content')
<h2 class="text-2xl font-bold text-gray-800 mb-2">Add a Product Type</h2>
<p class="text-gray-600 mb-6">What product does this line produce? Define the product type.</p>

<form method="POST" action="{{ route('onboarding.step2') }}">
    @csrf
    <div class="space-y-4">
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Product Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition @error('name') border-red-500 @enderror" placeholder="e.g. Air Filter">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="code" class="block text-sm font-medium text-gray-700 mb-1">Code <span class="text-red-500">*</span></label>
            <input type="text" name="code" id="code" value="{{ old('code') }}" required
                class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition @error('code') border-red-500 @enderror" placeholder="e.g. FILTER">
            @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="unit_of_measure" class="block text-sm font-medium text-gray-700 mb-1">Unit of Measure</label>
            <input type="text" name="unit_of_measure" id="unit_of_measure" value="{{ old('unit_of_measure', 'pcs') }}"
                class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition" placeholder="pcs, kg, m...">
        </div>
    </div>
    <div class="flex justify-between mt-6">
        <a href="{{ route('onboarding.step1') }}" class="btn-touch btn-secondary">← Back</a>
        <button type="submit" class="btn-touch btn-primary">Next: Process Template →</button>
    </div>
</form>
@endsection
