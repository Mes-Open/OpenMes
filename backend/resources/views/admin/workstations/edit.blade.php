@extends('layouts.app')

@section('title', 'Edit Workstation')

@section('content')
<x-breadcrumbs :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'Lines', 'url' => route('admin.lines.index')],
    ['label' => $line->name, 'url' => route('admin.lines.show', $line)],
    ['label' => 'Workstations', 'url' => route('admin.lines.workstations.index', $line)],
    ['label' => $workstation->name, 'url' => null],
]" />

<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('admin.lines.workstations.index', $line) }}" class="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Workstations
        </a>
        <h1 class="text-3xl font-bold text-gray-800">Edit Workstation</h1>
        <p class="text-sm text-gray-600 mt-1">{{ $line->name }}</p>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.lines.workstations.update', [$line, $workstation]) }}">
            @csrf
            @method('PUT')

            <div class="mb-6">
                <label for="code" class="form-label">Workstation Code</label>
                <input
                    type="text"
                    id="code"
                    name="code"
                    value="{{ old('code', $workstation->code) }}"
                    class="form-input w-full @error('code') border-red-500 @enderror"
                    placeholder="e.g., WS-A01, ASSEMBLY-1"
                    required
                    autofocus
                >
                <p class="text-sm text-gray-500 mt-1">Unique identifier for this workstation</p>
                @error('code')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="name" class="form-label">Workstation Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="{{ old('name', $workstation->name) }}"
                    class="form-input w-full @error('name') border-red-500 @enderror"
                    placeholder="e.g., Assembly Station 1, Quality Check Point"
                    required
                >
                @error('name')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="workstation_type" class="form-label">Workstation Type</label>
                <input
                    type="text"
                    id="workstation_type"
                    name="workstation_type"
                    value="{{ old('workstation_type', $workstation->workstation_type) }}"
                    class="form-input w-full @error('workstation_type') border-red-500 @enderror"
                    placeholder="e.g., Assembly, Quality Control, Packaging (optional)"
                >
                <p class="text-sm text-gray-500 mt-1">Optional classification for this workstation</p>
                @error('workstation_type')
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
                        {{ old('is_active', $workstation->is_active) ? 'checked' : '' }}
                    >
                    <span class="ml-2 text-sm text-gray-700">Active (workstation is ready for use)</span>
                </label>
            </div>

            {{-- Assigned Workers --}}
            <div class="mb-6 border-t border-gray-100 pt-6">
                <h2 class="text-base font-semibold text-gray-800 mb-1">Assigned Workers</h2>
                <p class="text-sm text-gray-500 mb-3">Workers regularly operating at this workstation.</p>

                @if($workers->isEmpty())
                    <p class="text-sm text-gray-400 italic">No active workers in the system.</p>
                @else
                    <div class="divide-y divide-gray-100 border border-gray-200 rounded-lg overflow-hidden">
                        @foreach($workers as $worker)
                            @php $isAssigned = $worker->workstation_id === $workstation->id; @endphp
                            <label class="flex items-center gap-3 px-4 py-2.5 cursor-pointer hover:bg-gray-50 {{ $isAssigned ? 'bg-blue-50' : '' }}">
                                <input type="checkbox" name="worker_ids[]" value="{{ $worker->id }}"
                                       class="rounded border-gray-300 text-blue-600"
                                       {{ $isAssigned ? 'checked' : '' }}>
                                <div class="flex-1 min-w-0">
                                    <span class="text-sm font-medium text-gray-800">{{ $worker->name }}</span>
                                    <span class="text-xs text-gray-400 font-mono ml-2">{{ $worker->code }}</span>
                                    @if($worker->workstation_id && !$isAssigned)
                                        <span class="text-xs text-orange-500 ml-2">(currently at: {{ $worker->workstation->name }})</span>
                                    @endif
                                </div>
                                @if($worker->crew)
                                    <span class="text-xs text-gray-400 shrink-0">{{ $worker->crew->name }}</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.lines.workstations.index', $line) }}" class="btn-touch btn-secondary">
                    Cancel
                </a>
                <button type="submit" class="btn-touch btn-primary">
                    Update Workstation
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
