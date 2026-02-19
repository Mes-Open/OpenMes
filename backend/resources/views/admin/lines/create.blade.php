@extends('layouts.app')

@section('title', 'Create Production Line')

@section('content')
<x-breadcrumbs :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'Lines', 'url' => route('admin.lines.index')],
    ['label' => 'New Line', 'url' => null],
]" />

<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('admin.lines.index') }}" class="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Production Lines
        </a>
        <h1 class="text-3xl font-bold text-gray-800">Create Production Line</h1>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.lines.store') }}">
            @csrf

            <div class="mb-6">
                <label for="code" class="form-label">Line Code</label>
                <input
                    type="text"
                    id="code"
                    name="code"
                    value="{{ old('code') }}"
                    class="form-input w-full @error('code') border-red-500 @enderror"
                    placeholder="e.g., LINE-A, PROD-01"
                    required
                    autofocus
                >
                <p class="text-sm text-gray-500 mt-1">Unique identifier for this production line</p>
                @error('code')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="name" class="form-label">Line Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="{{ old('name') }}"
                    class="form-input w-full @error('name') border-red-500 @enderror"
                    placeholder="e.g., Assembly Line A, Packaging Line 1"
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
                    placeholder="Optional description of what this line produces or its purpose"
                >{{ old('description') }}</textarea>
                @error('description')
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
                        {{ old('is_active', true) ? 'checked' : '' }}
                    >
                    <span class="ml-2 text-sm text-gray-700">Active (line is ready for production)</span>
                </label>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.lines.index') }}" class="btn-touch btn-secondary">
                    Cancel
                </a>
                <button type="submit" class="btn-touch btn-primary">
                    Create Line
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
