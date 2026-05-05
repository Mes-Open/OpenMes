@extends('onboarding.layout', ['step' => 3])

@section('content')
<h2 class="text-2xl font-bold text-gray-800 mb-2">Define Process Template</h2>
<p class="text-gray-600 mb-6">A process template defines the production steps (recipe) for your product. Add each step in the order they happen during production.</p>

<form method="POST" action="{{ route('onboarding.step3') }}" x-data="{
    steps: [{ name: '', estimated_duration_minutes: '' }],
    addStep() { this.steps.push({ name: '', estimated_duration_minutes: '' }) },
    removeStep(i) { if (this.steps.length > 1) this.steps.splice(i, 1) }
}">
    @csrf
    <div class="space-y-5">
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Template Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition @error('name') border-red-500 @enderror" placeholder="e.g. Filter Assembly Process">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Production Steps <span class="text-red-500">*</span></label>
            <p class="text-xs text-gray-500 mb-3">Add each production step in order. For each step, enter a name and optionally how long it takes (in minutes).</p>
            @error('steps') <p class="mb-2 text-sm text-red-600">{{ $message }}</p> @enderror

            {{-- Column headers --}}
            <div class="flex gap-2 mb-2 text-xs font-medium text-gray-500 uppercase tracking-wider">
                <span class="w-6"></span>
                <span class="flex-1">Step Name</span>
                <span class="w-28 text-center">Duration (min)</span>
                <span class="w-8"></span>
            </div>

            <template x-for="(step, index) in steps" :key="index">
                <div class="flex gap-2 mb-2 items-center">
                    <span class="flex items-center justify-center text-sm text-gray-400 w-6 font-medium" x-text="index + 1 + '.'"></span>
                    <input type="text" :name="'steps[' + index + '][name]'" x-model="step.name" required
                        class="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition"
                        placeholder="e.g. Assembly, Injection, Packaging...">
                    <input type="number" :name="'steps[' + index + '][estimated_duration_minutes]'" x-model="step.estimated_duration_minutes"
                        class="w-28 rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition text-center"
                        placeholder="min" min="0">
                    <button type="button" @click="removeStep(index)" class="w-8 text-red-400 hover:text-red-600 text-lg"
                        x-show="steps.length > 1">&times;</button>
                    <span x-show="steps.length <= 1" class="w-8"></span>
                </div>
            </template>

            <button type="button" @click="addStep()" class="text-sm text-blue-600 hover:text-blue-800 mt-2 font-medium">+ Add another step</button>
        </div>
    </div>
    <div class="flex justify-between mt-6">
        <a href="{{ route('onboarding.step2') }}" class="btn-touch btn-secondary">← Back</a>
        <button type="submit" class="btn-touch btn-primary">Next: Work Order →</button>
    </div>
</form>
@endsection
