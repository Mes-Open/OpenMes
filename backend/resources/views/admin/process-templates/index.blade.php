@extends('layouts.app')

@section('title', 'Process Templates - ' . $productType->name)

@section('content')
<x-breadcrumbs :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'Product Types', 'url' => route('admin.product-types.index')],
    ['label' => $productType->name, 'url' => route('admin.product-types.show', $productType)],
    ['label' => 'Process Templates', 'url' => null],
]" />

<div class="max-w-7xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('admin.product-types.show', $productType) }}" class="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to {{ $productType->name }}
        </a>
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-3xl font-bold text-gray-800">Process Templates</h1>
                    <span x-data="{ show: false }" class="relative inline-flex items-center" @mouseenter="show = true" @mouseleave="show = false">
                        <span class="w-5 h-5 rounded-full bg-gray-200 text-gray-500 text-xs flex items-center justify-center cursor-default select-none font-bold hover:bg-blue-100 hover:text-blue-600 transition-colors">i</span>
                        <div x-show="show" x-cloak class="absolute left-7 top-0 z-20 w-72 bg-gray-800 text-white text-xs rounded-lg p-3 shadow-xl leading-relaxed">
                            <strong class="block mb-1 text-white">Process Template</strong>
                            Defines the sequence of production steps for a given product type. Each template is a recipe for how to manufacture a product — step by step. One product type can have multiple templates (e.g. different process versions).
                        </div>
                    </span>
                </div>
                <p class="text-sm text-gray-600 mt-1">{{ $productType->name }}</p>
            </div>
            <a href="{{ route('admin.product-types.process-templates.create', $productType) }}" class="btn-touch btn-primary">
                <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Create Template
            </a>
        </div>
    </div>

    @if($templates->count() > 0)
        <div class="space-y-4">
            @foreach($templates as $template)
                <div class="card hover:shadow-lg transition-shadow">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <h3 class="text-xl font-bold text-gray-800">{{ $template->name }}</h3>
                                @if($template->is_active)
                                    <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">Active</span>
                                @else
                                    <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-sm font-medium">Inactive</span>
                                @endif
                                <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">v{{ $template->version }}</span>
                            </div>
                            <div class="flex items-center gap-4 text-sm text-gray-600">
                                <span class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                    {{ $template->steps_count }} steps
                                </span>
                                <span>Created: {{ $template->created_at->format('Y-m-d H:i') }}</span>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('admin.product-types.process-templates.show', [$productType, $template]) }}" class="btn-touch btn-secondary text-sm">
                                <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                View Steps
                            </a>
                            <a href="{{ route('admin.product-types.process-templates.edit', [$productType, $template]) }}" class="text-blue-600 hover:text-blue-800 p-2" title="Edit">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </a>
                            <form method="POST" action="{{ route('admin.product-types.process-templates.toggle-active', [$productType, $template]) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-gray-600 hover:text-gray-800 p-2" title="{{ $template->is_active ? 'Deactivate' : 'Activate' }}">
                                    @if($template->is_active)
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
                            @if($template->steps_count === 0)
                                <form method="POST" action="{{ route('admin.product-types.process-templates.destroy', [$productType, $template]) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete this template?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 p-2" title="Delete">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                            @else
                                <span class="text-gray-300 p-2" title="Cannot delete - has steps">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="card text-center py-12">
            <svg class="mx-auto h-16 w-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-lg font-medium text-gray-700">No process templates yet</p>
            <p class="text-sm text-gray-500 mt-1 mb-4">Create a template to define how this product is manufactured.</p>
            <a href="{{ route('admin.product-types.process-templates.create', $productType) }}" class="inline-block btn-touch btn-primary">
                <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Create Template
            </a>
        </div>
    @endif
</div>
@endsection
