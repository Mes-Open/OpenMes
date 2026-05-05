@extends('onboarding.layout', ['step' => 3])

@section('content')
<h2 class="text-2xl font-bold text-gray-800 mb-2">Define Process Template</h2>
<p class="text-gray-600 mb-6">A process template defines the production steps (recipe) for your product.</p>

<form method="POST" action="{{ route('onboarding.step3') }}" x-data="{
    steps: [{ name: '', estimated_duration_minutes: '' }],
    addStep() { this.steps.push({ name: '', estimated_duration_minutes: '' }) },
    removeStep(i) { if (this.steps.length > 1) this.steps.splice(i, 1) }
}">
    @csrf
    <div class="space-y-4">
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Template Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition @error('name') border-red-500 @enderror" placeholder="e.g. Filter Assembly Process">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Production Steps <span class="text-red-500">*</span></label>
            @error('steps') <p class="mb-2 text-sm text-red-600">{{ $message }}</p> @enderror

            <template x-for="(step, index) in steps" :key="index">
                <div class="flex gap-2 mb-2">
                    <span class="flex items-center text-sm text-gray-400 w-6" x-text="index + 1 + '.'"></span>
                    <input type="text" :name="'steps[' + index + '][name]'" x-model="step.name" required
                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition flex-1" placeholder="Step name (e.g. Injection, Assembly, Packaging)">
                    <input type="number" :name="'steps[' + index + '][estimated_duration_minutes]'" x-model="step.estimated_duration_minutes"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition w-24" placeholder="Min." min="0">
                    <button type="button" @click="removeStep(index)" class="text-red-400 hover:text-red-600 px-2"
                        x-show="steps.length > 1">&times;</button>
                </div>
            </template>

            <button type="button" @click="addStep()" class="text-sm text-blue-600 hover:text-blue-800 mt-2">+ Add step</button>
        </div>
    </div>
    <div class="flex justify-between mt-6">
        <a href="{{ route('onboarding.step2') }}" class="btn-touch btn-secondary">← Back</a>
        <button type="submit" class="btn-touch btn-primary">Next: Work Order →</button>
    </div>
</form>
@endsection
