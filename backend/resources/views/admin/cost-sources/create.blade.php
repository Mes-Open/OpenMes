@extends('layouts.app')

@section('title', 'New Cost Source')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">New Cost Source</h1>
            <p class="text-gray-600 mt-1">Define a cost item for maintenance and operations</p>
        </div>
        <a href="{{ route('admin.cost-sources.index') }}" class="btn-touch btn-secondary">← Back</a>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.cost-sources.store') }}">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Code <span class="text-red-500">*</span></label>
                    <input type="text" name="code" value="{{ old('code') }}"
                           class="form-input w-full" placeholder="e.g. MAINT-LABOR" required maxlength="50">
                    @error('code') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}"
                           class="form-input w-full" placeholder="e.g. Maintenance Labor" required maxlength="200">
                    @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Unit Cost <span class="text-red-500">*</span></label>
                    <input type="number" name="unit_cost" value="{{ old('unit_cost') }}"
                           class="form-input w-full" step="0.01" min="0" required placeholder="0.00">
                    @error('unit_cost') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Unit <span class="text-red-500">*</span></label>
                    <input type="text" name="unit" value="{{ old('unit') }}"
                           class="form-input w-full" placeholder="e.g. hour, piece, kg" required maxlength="50">
                    @error('unit') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Currency <span class="text-red-500">*</span></label>
                    <input type="text" name="currency" value="{{ old('currency', 'PLN') }}"
                           class="form-input w-full" placeholder="e.g. PLN, EUR, USD" required maxlength="10">
                    @error('currency') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="form-label">Description</label>
                    <textarea name="description" rows="3" class="form-input w-full" maxlength="2000">{{ old('description') }}</textarea>
                    @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="form-label mb-0">Active</span>
                    </label>
                </div>
            </div>

            <div class="flex gap-3 justify-end mt-6">
                <a href="{{ route('admin.cost-sources.index') }}" class="btn-touch btn-secondary">Cancel</a>
                <button type="submit" class="btn-touch btn-primary">Create Cost Source</button>
            </div>
        </form>
    </div>
</div>
@endsection
