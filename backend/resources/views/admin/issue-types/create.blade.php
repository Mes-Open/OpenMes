@extends('layouts.app')

@section('title', 'New Issue Type')

@section('content')
<x-breadcrumbs :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'Issue Types', 'url' => route('admin.issue-types.index')],
    ['label' => 'New Issue Type', 'url' => null],
]" />

<div class="max-w-xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">New Issue Type</h1>
        <a href="{{ route('admin.issue-types.index') }}" class="btn-touch btn-secondary">← Back</a>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.issue-types.store') }}">
            @csrf

            <div class="space-y-4">
                <div>
                    <label class="form-label">Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}"
                           class="form-input w-full" placeholder="e.g. Machine Breakdown" required maxlength="100">
                    @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Code <span class="text-red-500">*</span></label>
                    <input type="text" name="code" value="{{ old('code') }}"
                           class="form-input w-full font-mono" placeholder="e.g. MACHINE_BREAKDOWN" required maxlength="50">
                    <p class="text-xs text-gray-400 mt-1">Unique identifier, uppercase recommended</p>
                    @error('code') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Severity <span class="text-red-500">*</span></label>
                    <select name="severity" class="form-input w-full" required>
                        @foreach(['LOW','MEDIUM','HIGH','CRITICAL'] as $s)
                            <option value="{{ $s }}" @selected(old('severity') === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                    @error('severity') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="hidden" name="is_blocking" value="0">
                        <input type="checkbox" name="is_blocking" value="1"
                               class="rounded border-gray-300 w-5 h-5"
                               @checked(old('is_blocking'))>
                        <span class="text-sm font-medium text-gray-700">Blocking</span>
                    </label>
                    <p class="text-xs text-gray-400 mt-1 ml-8">Blocking issues will halt production and change work order status to BLOCKED</p>
                    @error('is_blocking') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex gap-3 justify-end mt-6">
                <a href="{{ route('admin.issue-types.index') }}" class="btn-touch btn-secondary">Cancel</a>
                <button type="submit" class="btn-touch btn-primary">Create Issue Type</button>
            </div>
        </form>
    </div>
</div>
@endsection
