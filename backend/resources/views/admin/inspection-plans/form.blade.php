@extends('layouts.app')

@section('title', $plan->exists ? __('Edit Inspection Plan') : __('New Inspection Plan'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Inspection Plans'), 'url' => route('admin.inspection-plans.index')],
    ['label' => $plan->exists ? __('Edit') : __('New'), 'url' => null],
]" />

<div class="max-w-3xl mx-auto" x-data="planForm({{ json_encode($plan->criteria ?: []) }})">
    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-4">
        {{ $plan->exists ? __('Edit Inspection Plan') : __('New Inspection Plan') }}
    </h1>

    <form method="POST" action="{{ $plan->exists ? route('admin.inspection-plans.update', $plan) : route('admin.inspection-plans.store') }}">
        @csrf
        @if($plan->exists) @method('PUT') @endif

        <div class="card mb-4 space-y-3">
            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Name') }} *</label>
                <input type="text" name="name" value="{{ old('name', $plan->name) }}" required maxlength="150" class="form-input w-full">
                @error('name')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Description') }}</label>
                <textarea name="description" rows="2" class="form-input w-full">{{ old('description', $plan->description) }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium mb-2">{{ __('Scope') }} *</label>
                @php
                    $currentScope = old('scope', $plan->material_id ? 'material' : ($plan->material_type_id ? 'material_type' : 'generic'));
                @endphp
                <label class="inline-flex items-center mr-4"><input type="radio" name="scope" value="material" @checked($currentScope === 'material')> <span class="ml-1">{{ __('Specific material') }}</span></label>
                <label class="inline-flex items-center mr-4"><input type="radio" name="scope" value="material_type" @checked($currentScope === 'material_type')> <span class="ml-1">{{ __('Material type') }}</span></label>
                <label class="inline-flex items-center"><input type="radio" name="scope" value="generic" @checked($currentScope === 'generic')> <span class="ml-1">{{ __('Generic') }}</span></label>

                <div class="grid grid-cols-2 gap-3 mt-3">
                    <div>
                        <label class="text-xs text-gray-500">{{ __('Material') }}</label>
                        <select name="material_id" class="form-input w-full">
                            <option value="">—</option>
                            @foreach($materials as $m)
                                <option value="{{ $m->id }}" @selected(old('material_id', $plan->material_id) == $m->id)>{{ $m->code }} — {{ $m->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">{{ __('Material type') }}</label>
                        <select name="material_type_id" class="form-input w-full">
                            <option value="">—</option>
                            @foreach($materialTypes as $mt)
                                <option value="{{ $mt->id }}" @selected(old('material_type_id', $plan->material_type_id) == $mt->id)>{{ $mt->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div>
                <label class="inline-flex items-center">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->exists ? $plan->is_active : true))>
                    <span class="ml-2 text-sm">{{ __('Active') }}</span>
                </label>
            </div>
        </div>

        <div class="card mb-4">
            <div class="flex justify-between items-center mb-3">
                <h2 class="font-bold">{{ __('Criteria') }}</h2>
                <button type="button" @click="addCriterion" class="btn-touch btn-secondary text-sm">+ {{ __('Add criterion') }}</button>
            </div>

            <template x-for="(c, i) in criteria" :key="i">
                <div class="border border-gray-200 dark:border-gray-700 rounded p-3 mb-2 space-y-2">
                    <div class="grid grid-cols-12 gap-2">
                        <input type="text" :name="`criteria[${i}][name]`" x-model="c.name" placeholder="{{ __('Name') }}" required maxlength="150" class="form-input col-span-5">
                        <select :name="`criteria[${i}][type]`" x-model="c.type" class="form-input col-span-3">
                            <option value="pass_fail">{{ __('Pass/Fail') }}</option>
                            <option value="measurement">{{ __('Measurement') }}</option>
                            <option value="visual">{{ __('Visual') }}</option>
                            <option value="functional">{{ __('Functional') }}</option>
                        </select>
                        <input type="text" :name="`criteria[${i}][unit]`" x-model="c.unit" placeholder="{{ __('Unit') }}" class="form-input col-span-2">
                        <label class="col-span-1 inline-flex items-center justify-center text-xs">
                            <input type="checkbox" :name="`criteria[${i}][required]`" value="1" x-model="c.required" class="mr-1">
                            {{ __('Required') }}
                        </label>
                        <button type="button" @click="removeCriterion(i)" class="col-span-1 text-red-600 text-sm">✕</button>
                    </div>
                    <div x-show="c.type === 'measurement'" class="grid grid-cols-2 gap-2">
                        <input type="number" step="0.0001" :name="`criteria[${i}][spec_min]`" x-model="c.spec_min" placeholder="{{ __('Spec min') }}" class="form-input">
                        <input type="number" step="0.0001" :name="`criteria[${i}][spec_max]`" x-model="c.spec_max" placeholder="{{ __('Spec max') }}" class="form-input">
                    </div>
                </div>
            </template>

            <p x-show="criteria.length === 0" class="text-sm text-gray-500">{{ __('No criteria yet. Click "Add criterion" to start.') }}</p>
        </div>

        <div class="flex gap-2 justify-end">
            <a href="{{ route('admin.inspection-plans.index') }}" class="btn-touch btn-secondary">{{ __('Cancel') }}</a>
            <button class="btn-touch btn-primary">{{ $plan->exists ? __('Update') : __('Create') }}</button>
        </div>
    </form>
</div>

<script>
function planForm(initial) {
    return {
        criteria: initial && initial.length ? initial : [{ name: '', type: 'pass_fail', required: true, unit: '', spec_min: null, spec_max: null }],
        addCriterion() { this.criteria.push({ name: '', type: 'pass_fail', required: true, unit: '', spec_min: null, spec_max: null }); },
        removeCriterion(i) { this.criteria.splice(i, 1); },
    };
}
</script>
@endsection
