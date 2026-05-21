@extends('layouts.app')

@section('title', __('Start Inspection'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Inspections'), 'url' => route('inspections.index')],
    ['label' => __('Start'), 'url' => null],
]" />

<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold mb-4 text-gray-800 dark:text-gray-100">{{ __('Start Inspection') }}</h1>

    <form method="POST" action="{{ route('inspections.store') }}" class="card space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium mb-1">{{ __('Material') }} *</label>
            <select name="material_id" required class="form-input w-full">
                <option value="">{{ __('-- choose --') }}</option>
                @foreach($materials as $m)
                    <option value="{{ $m->id }}" @selected(old('material_id') == $m->id)>{{ $m->code }} — {{ $m->name }}</option>
                @endforeach
            </select>
            @error('material_id')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Lot number') }} *</label>
                <input type="text" name="lot_number" required maxlength="100" value="{{ old('lot_number') }}" class="form-input w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Supplier lot ref') }}</label>
                <input type="text" name="supplier_lot_ref" maxlength="100" value="{{ old('supplier_lot_ref') }}" class="form-input w-full">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">{{ __('Quantity received') }}</label>
            <input type="number" step="0.001" min="0" name="quantity_received" value="{{ old('quantity_received') }}" class="form-input w-full">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">{{ __('Inspection plan') }}</label>
            <select name="inspection_plan_id" class="form-input w-full">
                <option value="">{{ __('— no plan (ad-hoc) —') }}</option>
                @foreach($plans as $plan)
                    <option value="{{ $plan->id }}" @selected(old('inspection_plan_id') == $plan->id)>
                        {{ $plan->name }}
                        @if($plan->material) ({{ $plan->material->name }})
                        @elseif($plan->materialType) ({{ $plan->materialType->name }})
                        @endif
                    </option>
                @endforeach
            </select>
            <p class="text-xs text-gray-500 mt-1">{{ __('If no plan is selected, you can still record results but no criteria will be pre-filled.') }}</p>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('inspections.index') }}" class="btn-touch btn-secondary">{{ __('Cancel') }}</a>
            <button class="btn-touch btn-primary">{{ __('Start') }}</button>
        </div>
    </form>
</div>
@endsection
