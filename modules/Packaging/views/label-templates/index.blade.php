@extends('layouts.app')

@section('title', 'Label Templates')

@section('content')
<x-breadcrumbs :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'Label Templates', 'url' => null],
]" />

<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Label Templates</h1>
            <p class="text-gray-600 dark:text-gray-300 mt-1">Configure label layouts for printing on PDF and Zebra (ZPL) printers.</p>
        </div>
        <a href="{{ route('packaging.label-templates.create') }}" class="btn-touch btn-primary">
            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Template
        </a>
    </div>

    <div class="card">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-slate-700">
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-slate-200">Name</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-slate-200">Type</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-slate-200">Size</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-slate-200">Barcode</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-slate-200">Fields</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-slate-200">Default</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-slate-200">Status</th>
                        <th class="text-right py-3 px-4 font-semibold text-gray-700 dark:text-slate-200">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                    @forelse($templates as $template)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800">
                            <td class="py-3 px-4 font-medium text-gray-900 dark:text-white">{{ $template->name }}</td>
                            <td class="py-3 px-4 text-gray-600 dark:text-slate-300">{{ \Modules\Packaging\Models\LabelTemplate::TYPES[$template->type] ?? $template->type }}</td>
                            <td class="py-3 px-4 font-mono text-gray-600 dark:text-slate-300">{{ $template->size }} mm</td>
                            <td class="py-3 px-4 text-gray-600 dark:text-slate-300">{{ strtoupper($template->barcode_format) }}</td>
                            <td class="py-3 px-4 text-gray-600 dark:text-slate-300">
                                @php
                                    $active = collect($template->fields_config ?? [])->filter()->keys();
                                @endphp
                                <span class="text-xs">{{ $active->count() }} / {{ count(\Modules\Packaging\Models\LabelTemplate::AVAILABLE_FIELDS) }} enabled</span>
                            </td>
                            <td class="py-3 px-4">
                                @if($template->is_default)
                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">Default</span>
                                @else
                                    <form method="POST" action="{{ route('packaging.label-templates.set-default', $template) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs text-gray-500 hover:text-blue-600 underline">Set default</button>
                                    </form>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if($template->is_active)
                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Active</span>
                                @else
                                    <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium">Inactive</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('packaging.label-templates.edit', $template) }}" class="text-blue-600 hover:text-blue-800 p-1" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    <form method="POST" action="{{ route('packaging.label-templates.destroy', $template) }}" class="inline"
                                          onsubmit="return confirm('Delete this template?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 p-1" title="Delete">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-gray-500">
                                <p class="font-medium">No label templates yet</p>
                                <a href="{{ route('packaging.label-templates.create') }}" class="inline-block mt-3 btn-touch btn-primary">Create one</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
