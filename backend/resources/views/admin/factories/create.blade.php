@extends('layouts.app')

@section('title', __('New Factory'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Factories'), 'url' => route('admin.factories.index')],
    ['label' => __('New Factory'), 'url' => null],
]" />

<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">{{ __('New Factory') }}</h1>
            <p class="text-gray-600 mt-1">{{ __('Create a factory or production site') }}</p>
        </div>
        <a href="{{ route('admin.factories.index') }}" class="btn-touch btn-secondary">{{ __('← Back') }}</a>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.factories.store') }}">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">{{ __('Code') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="code" value="{{ old('code') }}"
                           class="form-input w-full" placeholder="e.g. FAC-01" required maxlength="50">
                    @error('code') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">{{ __('Name') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}"
                           class="form-input w-full" placeholder="e.g. Main Plant" required maxlength="200">
                    @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="form-label">{{ __("Description") }}</label>
                    <textarea name="description" rows="3" class="form-input w-full" maxlength="2000">{{ old('description') }}</textarea>
                    @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="form-label mb-0">{{ __('Active') }}</span>
                    </label>
                </div>
            </div>

            <div class="flex gap-3 justify-end mt-6">
                <a href="{{ route('admin.factories.index') }}" class="btn-touch btn-secondary">{{ __('Cancel') }}</a>
                <button type="submit" class="btn-touch btn-primary">{{ __('Create Factory') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
