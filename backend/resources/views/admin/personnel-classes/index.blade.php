@extends('layouts.app')

@section('title', __('Personnel Classes'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Personnel Classes'), 'url' => null],
]" />

<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">{{ __('Personnel Classes') }}</h1>
            <p class="text-gray-600 mt-1">
                {{ __('ISA-95 competency templates — required skills and minimum certification levels.') }}
            </p>
        </div>
        <a href="{{ route('admin.personnel-classes.create') }}"
           class="btn-touch btn-primary inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            {{ __('Define class') }}
        </a>
    </div>

    @if(session('success'))
        <div class="card mb-4 border-l-4 border-green-400 bg-green-50">
            <p class="text-sm text-green-700">{{ session('success') }}</p>
        </div>
    @endif
    @if(session('error'))
        <div class="card mb-4 border-l-4 border-red-400 bg-red-50">
            <p class="text-sm text-red-700">{{ session('error') }}</p>
        </div>
    @endif

    <form method="GET" action="{{ route('admin.personnel-classes.index') }}" class="card mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="sm:col-span-2">
                <label class="form-label">{{ __('Search') }}</label>
                <input type="text" name="search" value="{{ request('search') }}"
                       class="form-input w-full"
                       placeholder="{{ __('Search code or name…') }}">
            </div>
            <div>
                <label class="form-label">{{ __('Active') }}</label>
                <select name="is_active" class="form-input w-full">
                    <option value="">{{ __('All') }}</option>
                    <option value="1" @selected(request('is_active') === '1')>{{ __('Active') }}</option>
                    <option value="0" @selected(request('is_active') === '0')>{{ __('Inactive') }}</option>
                </select>
            </div>
        </div>
        <div class="flex gap-2 mt-4">
            <button type="submit" class="btn-touch btn-primary text-sm">{{ __('Filter') }}</button>
            <a href="{{ route('admin.personnel-classes.index') }}" class="btn-touch btn-secondary text-sm">{{ __('Clear') }}</a>
        </div>
    </form>

    @if($personnelClasses->isEmpty())
        <div class="card text-center py-16">
            <p class="text-gray-500 text-lg mb-2">{{ __('No personnel classes yet.') }}</p>
            <p class="text-gray-400 text-sm mb-4">{{ __('Define competency templates to standardise role qualification across workers.') }}</p>
            <a href="{{ route('admin.personnel-classes.create') }}" class="btn-touch btn-primary inline-flex items-center gap-2">
                {{ __('Define your first personnel class') }}
            </a>
        </div>
    @else
        <div class="card overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-gray-500 border-b">
                        <th class="py-2 pr-3">{{ __('Code') }}</th>
                        <th class="py-2 pr-3">{{ __('Name') }}</th>
                        <th class="py-2 pr-3 text-right">{{ __('Required skills') }}</th>
                        <th class="py-2 pr-3 text-right">{{ __('Workers') }}</th>
                        <th class="py-2 pr-3">{{ __('Status') }}</th>
                        <th class="py-2 pr-3 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($personnelClasses as $pc)
                        <tr class="hover:bg-gray-50">
                            <td class="py-2 pr-3 font-mono text-xs text-gray-700">{{ $pc->code }}</td>
                            <td class="py-2 pr-3">
                                <a href="{{ route('admin.personnel-classes.show', $pc) }}" class="text-blue-600 hover:underline font-medium">
                                    {{ $pc->name }}
                                </a>
                            </td>
                            <td class="py-2 pr-3 text-right text-gray-700">
                                {{ is_array($pc->required_skill_ids) ? count($pc->required_skill_ids) : 0 }}
                            </td>
                            <td class="py-2 pr-3 text-right text-gray-700">{{ $pc->workers_count }}</td>
                            <td class="py-2 pr-3">
                                @if($pc->is_active)
                                    <span class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800">{{ __('Active') }}</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td class="py-2 pr-3 text-right">
                                <div class="inline-flex gap-1">
                                    <a href="{{ route('admin.personnel-classes.show', $pc) }}"
                                       class="text-blue-600 hover:text-blue-800 p-1" title="{{ __('View') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>
                                    <a href="{{ route('admin.personnel-classes.edit', $pc) }}"
                                       class="text-gray-600 hover:text-gray-800 p-1" title="{{ __('Edit') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    <form method="POST" action="{{ route('admin.personnel-classes.destroy', $pc) }}"
                                          class="inline"
                                          onsubmit="return confirm('{{ __('Delete this personnel class?') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="p-1 text-red-600 hover:text-red-800"
                                                title="{{ __('Delete') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $personnelClasses->withQueryString()->links() }}</div>
    @endif
</div>
@endsection
