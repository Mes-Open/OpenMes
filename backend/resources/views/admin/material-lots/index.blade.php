@extends('layouts.app')

@section('title', __('Material Lots'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Material Lots'), 'url' => null],
]" />

@php
    $statusColors = [
        'received'   => 'bg-blue-100 text-blue-800',
        'quarantine' => 'bg-amber-100 text-amber-800',
        'released'   => 'bg-green-100 text-green-800',
        'consumed'   => 'bg-gray-100 text-gray-600',
        'expired'    => 'bg-red-100 text-red-800',
        'rejected'   => 'bg-red-200 text-red-900',
    ];
    $hasFilters = request()->hasAny(['search', 'material_id', 'status', 'expiry', 'supplier']);
@endphp

<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">{{ __('Material Lots') }}</h1>
            <p class="text-gray-600 mt-1">
                {{ __('Showing :n of :total', ['n' => $lots->count(), 'total' => $lots->total()]) }}
            </p>
        </div>
        <a href="{{ route('admin.material-lots.create') }}" class="btn-touch btn-primary inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            {{ __('Register lot') }}
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

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.material-lots.index') }}" class="card mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="lg:col-span-2">
                <label class="form-label">{{ __('Search') }}</label>
                <input type="text" name="search" value="{{ request('search') }}"
                       class="form-input w-full"
                       placeholder="{{ __('Lot number, supplier reference…') }}">
            </div>
            <div>
                <label class="form-label">{{ __('Material') }}</label>
                <select name="material_id" class="form-input w-full">
                    <option value="">{{ __('All materials') }}</option>
                    @foreach($materials as $m)
                        <option value="{{ $m->id }}" @selected(request('material_id') == $m->id)>
                            {{ $m->code }} — {{ $m->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">{{ __('Status') }}</label>
                <select name="status" class="form-input w-full">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach($statuses as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">{{ __('Expiry') }}</label>
                <select name="expiry" class="form-input w-full">
                    <option value="">{{ __('Any') }}</option>
                    <option value="expired" @selected(request('expiry') === 'expired')>{{ __('Expired') }}</option>
                    <option value="soon" @selected(request('expiry') === 'soon')>{{ __('Expiring within 30 days') }}</option>
                </select>
            </div>
        </div>
        <div class="flex gap-2 mt-4">
            <button type="submit" class="btn-touch btn-primary text-sm">{{ __('Filter') }}</button>
            <a href="{{ route('admin.material-lots.index') }}" class="btn-touch btn-secondary text-sm">{{ __('Clear') }}</a>
        </div>
    </form>

    @if($lots->isEmpty())
        <div class="card text-center py-16">
            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
            </svg>
            @if($hasFilters)
                <p class="text-gray-500 text-lg mb-2">{{ __('No lots match your filters.') }}</p>
                <a href="{{ route('admin.material-lots.index') }}" class="btn-touch btn-secondary inline-flex items-center gap-2">{{ __('Clear filters') }}</a>
            @else
                <p class="text-gray-500 text-lg mb-2">{{ __('No material lots yet.') }}</p>
                <p class="text-gray-400 text-sm mb-4">{{ __('Register your first lot to start traceable consumption.') }}</p>
                <a href="{{ route('admin.material-lots.create') }}" class="btn-touch btn-primary inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    {{ __('Register first lot') }}
                </a>
            @endif
        </div>
    @else
        <div class="card overflow-hidden p-0">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Lot') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Material') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Available / Received') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Received') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Expiry') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Supplier') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($lots as $lot)
                        @php $color = $statusColors[$lot->status] ?? 'bg-gray-100 text-gray-700'; @endphp
                        <tr>
                            <td class="px-4 py-3 font-mono text-sm">
                                <a href="{{ route('admin.material-lots.show', $lot) }}" class="text-blue-700 hover:underline">{{ $lot->lot_number }}</a>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if($lot->material)
                                    <span class="font-medium text-gray-900">{{ $lot->material->name }}</span>
                                    <span class="text-xs text-gray-500 block">{{ $lot->material->code }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-mono">
                                {{ rtrim(rtrim(number_format((float) $lot->quantity_available, 4, '.', ''), '0'), '.') }}
                                <span class="text-gray-400">/</span>
                                {{ rtrim(rtrim(number_format((float) $lot->quantity_received, 4, '.', ''), '0'), '.') }}
                                <span class="text-xs text-gray-500">{{ $lot->unit_of_measure }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $lot->received_at?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if($lot->expiry_date)
                                    <span class="{{ $lot->expiry_date->isPast() ? 'text-red-600 font-semibold' : ($lot->expiry_date->lte(now()->addDays(30)) ? 'text-amber-700' : 'text-gray-600') }}">
                                        {{ $lot->expiry_date->format('Y-m-d') }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $lot->supplier_lot_no ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $color }}">{{ ucfirst($lot->status) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('admin.material-lots.show', $lot) }}"
                                   class="inline-flex items-center justify-center w-8 h-8 rounded-md text-blue-600 hover:bg-blue-50">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                <a href="{{ route('admin.material-lots.edit', $lot) }}"
                                   class="inline-flex items-center justify-center w-8 h-8 rounded-md text-gray-500 hover:bg-gray-100">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                <form method="POST" action="{{ route('admin.material-lots.destroy', $lot) }}" class="inline"
                                      onsubmit="return confirm('{{ __('Delete this lot?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-md text-red-500 hover:bg-red-50">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/>
                                        </svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $lots->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
