@extends('layouts.app')

@section('title', 'View Templates')

@section('content')
<div class="max-w-4xl mx-auto">

    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['label' => 'View Templates', 'url' => null],
    ]" />

    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">View Templates</h1>
            <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">Define column layouts for the operator workstation view</p>
        </div>
        <a href="{{ route('admin.view-templates.create') }}" class="btn-touch btn-primary text-sm">+ New Template</a>
    </div>

    @if($templates->isEmpty())
        <div class="card text-center py-16">
            <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
            </svg>
            <p class="text-gray-500 text-lg font-medium">No view templates yet</p>
            <p class="text-gray-400 text-sm mt-1">Create a template to define which columns operators see in the workstation view.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($templates as $tpl)
            <div class="card flex flex-col sm:flex-row items-start sm:items-center gap-4">
                <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-gray-800 dark:text-white">{{ $tpl->name }}</h3>
                    @if($tpl->description)
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $tpl->description }}</p>
                    @endif
                    <div class="flex flex-wrap gap-1 mt-2">
                        @foreach($tpl->columns as $col)
                            <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 px-2 py-0.5 rounded">
                                {{ $col['label'] }}
                            </span>
                        @endforeach
                    </div>
                    @if($tpl->lines_count > 0)
                        <p class="text-xs text-blue-500 mt-1">Used by {{ $tpl->lines_count }} line(s)</p>
                    @endif
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a href="{{ route('admin.view-templates.edit', $tpl) }}" class="btn-touch btn-secondary text-sm">Edit</a>
                    @if($tpl->lines_count === 0)
                    <form method="POST" action="{{ route('admin.view-templates.destroy', $tpl) }}"
                          onsubmit="return confirm('Delete template \'{{ addslashes($tpl->name) }}\'?')">
                        @csrf @method('DELETE')
                        <button class="btn-touch btn-secondary text-sm text-red-500 hover:text-red-700">Delete</button>
                    </form>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
