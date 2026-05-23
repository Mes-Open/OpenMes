@extends('layouts.app')

@section('title', __('Label Templates'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Label Templates'), 'url' => null],
]" />

<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">{{ __('Label Templates') }}</h1>
            <p class="text-gray-600 mt-1">{{ __('Configure label layouts for printing on PDF and Zebra (ZPL) printers.') }}</p>
        </div>
        <a href="{{ route('packaging.label-templates.create') }}" class="btn-touch btn-primary">
            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            {{ __('New Template') }}
        </a>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Name') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Type') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Size') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Barcode') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Fields') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Default') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($templates as $template)
                        <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('packaging.label-templates.edit', $template) }}'">
                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $template->name }}</td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600">{{ __(\App\Models\LabelTemplate::TYPES[$template->type] ?? $template->type) }}</td>
                            <td class="px-4 py-4 whitespace-nowrap font-mono text-sm text-gray-600">{{ $template->size }} mm</td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600">{{ strtoupper($template->barcode_format) }}</td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600">
                                @php
                                    $active = collect($template->fields_config ?? [])->filter()->keys();
                                @endphp
                                {{ __(':count / :total enabled', ['count' => $active->count(), 'total' => count(\App\Models\LabelTemplate::AVAILABLE_FIELDS)]) }}
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap" onclick="event.stopPropagation()">
                                @if($template->is_default)
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">{{ __('Default') }}</span>
                                @else
                                    <form method="POST" action="{{ route('packaging.label-templates.set-default', $template) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs text-gray-500 hover:text-blue-600 underline">{{ __('Set default') }}</button>
                                    </form>
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                @if($template->is_active)
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">{{ __('Active') }}</span>
                                @else
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-600">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-right text-sm" onclick="event.stopPropagation()">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('packaging.label-templates.edit', $template) }}"
                                       class="text-blue-600 hover:text-blue-800 p-1" title="{{ __('Edit') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    <form method="POST" action="{{ route('packaging.label-templates.destroy', $template) }}" class="inline"
                                          onsubmit="return confirm('{{ __('Delete this template?') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 p-1" title="{{ __('Delete') }}">
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
                            <td colspan="8" class="px-4 py-12 text-center text-gray-400">
                                <p class="font-medium text-gray-500">{{ __('No label templates yet') }}</p>
                                <a href="{{ route('packaging.label-templates.create') }}" class="inline-block mt-3 btn-touch btn-primary">{{ __('Create one') }}</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
