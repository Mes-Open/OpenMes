@extends('layouts.app')

@section('title', __('Inbound Inspections'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Inspections'), 'url' => null],
]" />

<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">{{ __('Inbound Inspections') }}</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">{{ __('Receive material lots and verify them against an inspection plan.') }}</p>
        </div>
        <a href="{{ route('inspections.create') }}" class="btn-touch btn-primary">+ {{ __('Start inspection') }}</a>
    </div>

    {{-- Mini stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
        <div class="card text-center">
            <div class="text-xs text-gray-500 uppercase">{{ __('Pending') }}</div>
            <div class="text-2xl font-bold {{ $stats['pending'] > 0 ? 'text-amber-600' : 'text-gray-400' }}">{{ $stats['pending'] }}</div>
        </div>
        <div class="card text-center">
            <div class="text-xs text-gray-500 uppercase">{{ __('Failed (30d)') }}</div>
            <div class="text-2xl font-bold {{ $stats['recent_fail'] > 0 ? 'text-red-600' : 'text-green-600' }}">{{ $stats['recent_fail'] }}</div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex gap-1 mb-3 border-b border-gray-200 dark:border-gray-700">
        @foreach(['pending' => __('Pending'), 'recent' => __('Recent'), 'failed' => __('Failed')] as $key => $label)
            <a href="?tab={{ $key }}{{ ($selectedDisposition ?? null) ? '&disposition='.$selectedDisposition : '' }}" class="px-4 py-2 text-sm font-medium border-b-2 -mb-px {{ $tab === $key ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-800' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- Filter: disposition --}}
    <form method="GET" class="flex items-center gap-2 mb-3 text-sm">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <label for="disposition" class="text-gray-600 dark:text-gray-400">{{ __('Disposition') }}:</label>
        <select id="disposition" name="disposition" onchange="this.form.submit()" class="form-input w-48">
            <option value="">{{ __('All') }}</option>
            @foreach(['pending','accept','accept_with_deviation','rework','quarantine','scrap','reject','return_to_supplier'] as $d)
                <option value="{{ $d }}" @selected(($selectedDisposition ?? '') === $d)>{{ ucfirst(str_replace('_', ' ', $d)) }}</option>
            @endforeach
        </select>
        @if($selectedDisposition ?? null)
            <a href="?tab={{ $tab }}" class="text-xs text-gray-500 hover:underline">{{ __('Clear') }}</a>
        @endif
    </form>

    @if($inspections->isEmpty())
        <div class="card text-center py-8 text-gray-500">{{ __('No inspections in this tab.') }}</div>
    @else
        <div class="card overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Started') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Material') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Lot') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Qty') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Inspector') }}</th>
                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">{{ __('Status') }}</th>
                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">{{ __('Disposition') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($inspections as $insp)
                    @php
                        $statusClass = match($insp->status) {
                            'pass' => 'badge-green',
                            'conditional_pass' => 'badge-yellow',
                            'fail' => 'badge-red',
                            default => 'badge-gray',
                        };
                    @endphp
                    <tr>
                        <td class="px-3 py-2 font-mono text-xs">{{ $insp->started_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-3 py-2">{{ $insp->material->name }}</td>
                        <td class="px-3 py-2 font-mono">{{ $insp->lot_number }}</td>
                        <td class="px-3 py-2 text-right font-mono">{{ $insp->quantity_received !== null ? number_format($insp->quantity_received, 2) : '—' }}</td>
                        <td class="px-3 py-2 text-gray-500">{{ $insp->inspector?->name ?? '—' }}</td>
                        <td class="px-3 py-2 text-center">
                            <span class="badge {{ $statusClass }}">{{ str_replace('_', ' ', $insp->status) }}</span>
                            @if($insp->issue_id)
                                <a href="#" class="block text-xs text-red-600 mt-1">NC #{{ $insp->issue_id }}</a>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-center">
                            @php
                                $dispClass = match($insp->disposition) {
                                    'accept', 'accept_with_deviation' => 'badge-green',
                                    'rework' => 'badge-yellow',
                                    'quarantine' => 'badge-blue',
                                    'scrap', 'reject', 'return_to_supplier' => 'badge-red',
                                    default => 'badge-gray',
                                };
                            @endphp
                            <span class="badge {{ $dispClass }}">{{ str_replace('_', ' ', $insp->disposition ?? 'pending') }}</span>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <a href="{{ route('inspections.show', $insp) }}" class="text-blue-600 hover:underline">
                                {{ $insp->isPending() ? __('Perform') : __('Open') }}
                            </a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<style>
.badge { display:inline-block; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600; text-transform: capitalize; }
.badge-green  { background:#dcfce7; color:#166534; }
.badge-yellow { background:#fef9c3; color:#854d0e; }
.badge-red    { background:#fee2e2; color:#991b1b; }
.badge-blue   { background:#dbeafe; color:#1e40af; }
.badge-gray   { background:#f3f4f6; color:#4b5563; }
</style>
@endsection
