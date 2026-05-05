@extends('onboarding.layout', ['currentStep' => 3])

@section('content')
<h2 class="text-2xl font-bold text-gray-800 mb-2">Define a process template</h2>
<p class="text-gray-500 mb-6">What steps are needed to produce <strong>{{ $productType->name }}</strong>? Add them in order.</p>

<form method="POST" action="{{ route('onboarding.step3') }}" x-data="stepManager()">
    @csrf

    <div class="space-y-4 mb-6">
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Template Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" id="name" value="{{ old('name', $productType->name . ' — Standard Process') }}"
                   class="form-input w-full" required>
            @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="border-t pt-4">
        <h3 class="font-semibold text-gray-700 mb-3">Production Steps</h3>
        @error('steps') <p class="text-red-500 text-sm mb-2">{{ $message }}</p> @enderror

        <div class="space-y-3">
            <template x-for="(step, index) in steps" :key="index">
                <div class="flex items-start gap-3 bg-gray-50 rounded-lg p-3">
                    <span class="bg-blue-100 text-blue-700 rounded-full w-7 h-7 flex items-center justify-center text-sm font-bold flex-shrink-0 mt-1" x-text="index + 1"></span>
                    <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <div class="sm:col-span-2">
                            <input type="text" :name="'steps[' + index + '][name]'" x-model="step.name"
                                   class="form-input w-full text-sm" placeholder="Step name" required>
                        </div>
                        <div>
                            <input type="number" :name="'steps[' + index + '][estimated_duration_minutes]'" x-model="step.duration"
                                   class="form-input w-full text-sm" placeholder="Minutes" min="1">
                        </div>
                    </div>
                    <button type="button" @click="removeStep(index)" x-show="steps.length > 1"
                            class="text-red-400 hover:text-red-600 mt-1 flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </template>
        </div>

        <button type="button" @click="addStep()"
                class="mt-3 text-sm text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add step
        </button>
    </div>

    <div class="flex justify-between mt-8">
        <a href="{{ route('onboarding.step2') }}" class="text-gray-500 hover:text-gray-700 text-sm flex items-center">
            ← Back
        </a>
        <button type="submit" class="btn-touch btn-primary">
            Next: Work Order →
        </button>
    </div>
</form>

<script>
function stepManager() {
    return {
        steps: [
            { name: '', duration: '' },
            { name: '', duration: '' },
            { name: '', duration: '' },
        ],
        addStep() {
            this.steps.push({ name: '', duration: '' });
        },
        removeStep(index) {
            this.steps.splice(index, 1);
        }
    }
}
</script>
@endsection
