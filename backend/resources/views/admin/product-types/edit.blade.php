@extends('layouts.app')

@section('title', 'Edit Product Type')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('admin.product-types.index') }}" class="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Product Types
        </a>
        <h1 class="text-3xl font-bold text-gray-800">Edit Product Type</h1>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.product-types.update', $productType) }}">
            @csrf
            @method('PUT')

            <div class="mb-6">
                <label for="code" class="form-label">Product Code</label>
                <input
                    type="text"
                    id="code"
                    name="code"
                    value="{{ old('code', $productType->code) }}"
                    class="form-input w-full @error('code') border-red-500 @enderror"
                    placeholder="e.g., WIDGET-A, PROD-001"
                    required
                    autofocus
                >
                <p class="text-sm text-gray-500 mt-1">Unique identifier for this product type</p>
                @error('code')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="name" class="form-label">Product Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="{{ old('name', $productType->name) }}"
                    class="form-input w-full @error('name') border-red-500 @enderror"
                    placeholder="e.g., Widget Type A, Standard Component"
                    required
                >
                @error('name')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="description" class="form-label">Description</label>
                <textarea
                    id="description"
                    name="description"
                    rows="3"
                    class="form-input w-full @error('description') border-red-500 @enderror"
                    placeholder="Optional description of this product type"
                >{{ old('description', $productType->description) }}</textarea>
                @error('description')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="unit_of_measure" class="form-label">Unit of Measure</label>
                <input
                    type="text"
                    id="unit_of_measure"
                    name="unit_of_measure"
                    value="{{ old('unit_of_measure', $productType->unit_of_measure) }}"
                    class="form-input w-full @error('unit_of_measure') border-red-500 @enderror"
                    placeholder="e.g., pcs, kg, m (optional)"
                >
                <p class="text-sm text-gray-500 mt-1">How this product is counted or measured</p>
                @error('unit_of_measure')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label class="flex items-center">
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        class="h-5 w-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
                        {{ old('is_active', $productType->is_active) ? 'checked' : '' }}
                    >
                    <span class="ml-2 text-sm text-gray-700">Active (product type is ready for production)</span>
                </label>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.product-types.index') }}" class="btn-touch btn-secondary">
                    Cancel
                </a>
                <button type="submit" class="btn-touch btn-primary">
                    Update Product Type
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
