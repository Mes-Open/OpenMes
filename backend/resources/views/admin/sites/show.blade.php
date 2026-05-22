@extends('layouts.app')

@section('title', $site->name)

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Sites'), 'url' => route('admin.sites.index')],
    ['label' => $site->name, 'url' => null],
]" />

<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-start mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">{{ $site->name }}</h1>
            <p class="text-gray-500 mt-1 font-mono text-sm">{{ $site->code }}</p>
            @if($site->company)
                <p class="text-gray-600 mt-1">{{ __('Company') }}: <span class="font-medium">{{ $site->company->name }}</span></p>
            @endif
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.sites.areas.create', $site) }}" class="btn-touch btn-secondary">{{ __('Add Area') }}</a>
            <a href="{{ route('admin.sites.edit', $site) }}" class="btn-touch btn-primary">{{ __('Edit Site') }}</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <div class="card p-4">
            <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('Location') }}</p>
            <p class="text-gray-800 mt-1">
                {{ $site->address ?? '—' }}<br>
                {{ trim(($site->city ?? '') . ', ' . ($site->country ?? ''), ', ') ?: '—' }}
            </p>
            @if($site->timezone)
                <p class="text-xs text-gray-500 mt-2">{{ __('Timezone') }}: {{ $site->timezone }}</p>
            @endif
        </div>
        <div class="card p-4">
            <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('Status') }}</p>
            @if($site->is_active)
                <span class="inline-block mt-2 px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">{{ __('Active') }}</span>
            @else
                <span class="inline-block mt-2 px-2 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium">{{ __('Inactive') }}</span>
            @endif
        </div>
        <div class="card p-4">
            <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('Description') }}</p>
            <p class="text-gray-700 mt-1 text-sm">{{ $site->description ?: '—' }}</p>
        </div>
    </div>

    <div class="card mb-6">
        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
            <h2 class="font-semibold text-gray-800">{{ __('Areas') }} <span class="text-gray-500">({{ $site->areas->count() }})</span></h2>
            <a href="{{ route('admin.sites.areas.create', $site) }}" class="text-sm text-blue-600 hover:text-blue-800">{{ __('+ Add Area') }}</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left py-2 px-4 font-semibold text-gray-700">{{ __('Code') }}</th>
                        <th class="text-left py-2 px-4 font-semibold text-gray-700">{{ __('Name') }}</th>
                        <th class="text-left py-2 px-4 font-semibold text-gray-700">{{ __('Lines') }}</th>
                        <th class="text-left py-2 px-4 font-semibold text-gray-700">{{ __('Status') }}</th>
                        <th class="text-right py-2 px-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($site->areas as $area)
                        <tr class="hover:bg-gray-50">
                            <td class="py-2 px-4 font-mono text-gray-600">{{ $area->code }}</td>
                            <td class="py-2 px-4">
                                <a href="{{ route('admin.areas.show', $area) }}" class="text-blue-600 hover:text-blue-800">{{ $area->name }}</a>
                            </td>
                            <td class="py-2 px-4 text-gray-600">{{ $area->lines_count }}</td>
                            <td class="py-2 px-4">
                                @if($area->is_active)
                                    <span class="px-2 py-0.5 bg-green-100 text-green-800 rounded-full text-xs">{{ __('Active') }}</span>
                                @else
                                    <span class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full text-xs">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td class="py-2 px-4 text-right">
                                <a href="{{ route('admin.areas.edit', $area) }}" class="text-sm text-blue-600 hover:text-blue-800">{{ __('Edit') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-gray-500">{{ __('No areas defined yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="px-4 py-3 border-b border-gray-200">
            <h2 class="font-semibold text-gray-800">{{ __('Lines under this Site') }} <span class="text-gray-500">({{ $site->lines->count() }})</span></h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left py-2 px-4 font-semibold text-gray-700">{{ __('Code') }}</th>
                        <th class="text-left py-2 px-4 font-semibold text-gray-700">{{ __('Name') }}</th>
                        <th class="text-left py-2 px-4 font-semibold text-gray-700">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($site->lines as $line)
                        <tr class="hover:bg-gray-50">
                            <td class="py-2 px-4 font-mono text-gray-600">{{ $line->code }}</td>
                            <td class="py-2 px-4">
                                <a href="{{ route('admin.lines.show', $line) }}" class="text-blue-600 hover:text-blue-800">{{ $line->name }}</a>
                            </td>
                            <td class="py-2 px-4">
                                @if($line->is_active)
                                    <span class="px-2 py-0.5 bg-green-100 text-green-800 rounded-full text-xs">{{ __('Active') }}</span>
                                @else
                                    <span class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full text-xs">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-6 text-center text-gray-500">{{ __('No lines mapped under this site yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
