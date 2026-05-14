@extends('layouts.app')

@section('title', __('Create Process Template'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Product Types'), 'url' => route('admin.product-types.index')],
    ['label' => $productType->name, 'url' => route('admin.product-types.show', $productType)],
    ['label' => __('Process Templates'), 'url' => route('admin.product-types.process-templates.index', $productType)],
    ['label' => __('New Template'), 'url' => null],
]" />

<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('admin.product-types.process-templates.index', $productType) }}" class="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            {{ __('Back to Templates') }}
        </a>
        <h1 class="text-3xl font-bold text-gray-800">{{ __('Create Process Template') }}</h1>
        <p class="text-sm text-gray-600 mt-1">{{ $productType->name }}</p>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.product-types.process-templates.store', $productType) }}">
            @csrf

            <div class="mb-6">
                <label for="name" class="form-label">{{ __('Template Name') }}</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="{{ old('name') }}"
                    class="form-input w-full @error('name') border-red-500 @enderror"
                    placeholder="e.g., Standard Assembly Process, Quality Inspection v2"
                    required
                    autofocus
                >
                <p class="text-sm text-gray-500 mt-1">{{ __('Descriptive name for this manufacturing process') }}</p>
                @error('name')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-sm text-blue-800">
                    <strong>{{ __('Note:') }}</strong> {{ __("Version number will be assigned automatically. After creating the template, you'll be able to add production steps.") }}
                </p>
            </div>

            <div class="mb-6">
                <label class="flex items-center">
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        class="h-5 w-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
                        {{ old('is_active', true) ? 'checked' : '' }}
                    >
                    <span class="ml-2 text-sm text-gray-700">{{ __('Active (template is ready for use in work orders)') }}</span>
                </label>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.product-types.process-templates.index', $productType) }}" class="btn-touch btn-secondary">
                    {{ __('Cancel') }}
                </a>
                <button type="submit" class="btn-touch btn-primary">
                    {{ __('Create Template') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
