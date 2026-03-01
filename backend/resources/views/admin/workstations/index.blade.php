@extends('layouts.app')

@section('title', 'Workstations - ' . $line->name)

@section('content')
<x-breadcrumbs :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'Lines', 'url' => route('admin.lines.index')],
    ['label' => $line->name, 'url' => route('admin.lines.show', $line)],
    ['label' => 'Workstations', 'url' => null],
]" />

<div class="max-w-7xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('admin.lines.show', $line) }}" class="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to {{ $line->name }}
        </a>
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Workstations</h1>
                <p class="text-sm text-gray-600 mt-1">{{ $line->name }}</p>
            </div>
            <a href="{{ route('admin.lines.workstations.create', $line) }}" class="btn-touch btn-primary">
                <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Workstation
            </a>
        </div>
    </div>

    @if($workstations->count() > 0)
        <!-- Workstations Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($workstations as $workstation)
                <div class="card hover:shadow-lg transition-shadow">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <h3 class="text-lg font-bold text-gray-800">{{ $workstation->name }}</h3>
                                @if($workstation->is_active)
                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Active</span>
                                @else
                                    <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium">Inactive</span>
                                @endif
                            </div>
                            <p class="text-sm text-gray-500 font-mono">{{ $workstation->code }}</p>
                            @if($workstation->workstation_type)
                                <p class="text-xs text-gray-600 mt-1">Type: {{ $workstation->workstation_type }}</p>
                            @endif
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="mb-4 p-3 bg-gray-50 rounded-lg grid grid-cols-2 gap-3">
                        <div class="text-center">
                            <p class="text-2xl font-bold text-purple-600">{{ $workstation->template_steps_count }}</p>
                            <p class="text-xs text-gray-600">Template Steps</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold text-blue-600">{{ $workstation->workers_count }}</p>
                            <p class="text-xs text-gray-600">Workers</p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-2 pt-4 border-t border-gray-200">
                        <a href="{{ route('admin.lines.workstations.edit', [$line, $workstation]) }}" class="flex-1 btn-touch btn-secondary text-center text-sm">
                            Edit
                        </a>
                        <form method="POST" action="{{ route('admin.lines.workstations.toggle-active', [$line, $workstation]) }}" class="inline">
                            @csrf
                            <button type="submit" class="text-gray-600 hover:text-gray-800 p-2" title="{{ $workstation->is_active ? 'Deactivate' : 'Activate' }}">
                                @if($workstation->is_active)
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                    </svg>
                                @else
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                @endif
                            </button>
                        </form>
                        @if($workstation->template_steps_count === 0)
                            <form method="POST" action="{{ route('admin.lines.workstations.destroy', [$line, $workstation]) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete this workstation?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800 p-2" title="Delete">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                        @else
                            <span class="text-gray-300 p-2" title="Cannot delete - has template steps">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <!-- Empty State -->
        <div class="card text-center py-12">
            <svg class="mx-auto h-16 w-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
            <p class="text-lg font-medium text-gray-700">No workstations yet</p>
            <p class="text-sm text-gray-500 mt-1 mb-4">Get started by creating your first workstation for this line.</p>
            <a href="{{ route('admin.lines.workstations.create', $line) }}" class="inline-block btn-touch btn-primary">
                <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Create Workstation
            </a>
        </div>
    @endif
</div>
@endsection
