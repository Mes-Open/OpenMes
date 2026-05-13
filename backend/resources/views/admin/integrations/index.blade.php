@extends('layouts.app')

@section('title', __('Integration Configs'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Integrations'), 'url' => null],
]" />

<div class="max-w-5xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">{{ __('External Integrations') }}</h1>
        <a href="{{ route('admin.integrations.create') }}" class="btn-touch btn-primary">
            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            {{ __('Add Integration') }}
        </a>
    </div>

    @if($configs->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @foreach($configs as $config)
                <div class="card">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">{{ $config->system_name }}</h3>
                            <p class="text-sm text-gray-500 font-mono">{{ $config->system_type }}</p>
                        </div>
                        @if($config->is_active)
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">{{ __('Active') }}</span>
                        @else
                            <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium">{{ __('Inactive') }}</span>
                        @endif
                    </div>
                    <p class="text-sm text-gray-600 mb-4">{{ $config->material_sources_count }} {{ __('material(s) linked') }}</p>
                    <div class="flex gap-2">
                        <a href="{{ route('admin.integrations.edit', $config) }}" class="text-blue-600 hover:text-blue-800 text-sm">{{ __('Edit') }}</a>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="card text-center py-12">
            <p class="text-gray-500 text-lg mb-4">{{ __('No integrations configured.') }}</p>
            <a href="{{ route('admin.integrations.create') }}" class="btn-touch btn-primary">{{ __('Add Integration') }}</a>
        </div>
    @endif
</div>
@endsection
