@extends('layouts.app')

@section('title', __('Inspection') . ' #' . $inspection->id)

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Inspections'), 'url' => route('inspections.index')],
    ['label' => '#' . $inspection->id, 'url' => null],
]" />

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-start mb-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">
                {{ __('Inspection') }} #{{ $inspection->id }} — {{ $inspection->material->name }}
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">
                {{ __('Lot') }}: <span class="font-mono">{{ $inspection->lot_number }}</span>
                @if($inspection->quantity_received !== null)
                    · {{ __('Qty') }}: {{ number_format($inspection->quantity_received, 2) }}
                @endif
                · {{ __('Inspector') }}: {{ $inspection->inspector?->name ?? '—' }}
                · {{ __('Started') }}: {{ $inspection->started_at?->format('Y-m-d H:i') }}
            </p>
        </div>
        @php
            $statusClass = match($inspection->status) {
                'pass' => 'badge-green',
                'conditional_pass' => 'badge-yellow',
                'fail' => 'badge-red',
                default => 'badge-gray',
            };
        @endphp
        <span class="badge {{ $statusClass }} text-base">{{ str_replace('_', ' ', $inspection->status) }}</span>
    </div>

    {{-- ISA-95 Quality Disposition --}}
    <div class="card mb-4">
        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('Disposition') }}</h3>

        @if($inspection->hasDecision())
            @php
                $dColors = [
                    'accept' => 'bg-green-100 text-green-700',
                    'accept_with_deviation' => 'bg-green-100 text-green-800',
                    'rework' => 'bg-amber-100 text-amber-700',
                    'quarantine' => 'bg-blue-100 text-blue-700',
                    'scrap' => 'bg-red-100 text-red-700',
                    'reject' => 'bg-red-100 text-red-800',
                    'return_to_supplier' => 'bg-purple-100 text-purple-700',
                ];
            @endphp
            <div class="flex items-center gap-3">
                <span class="px-3 py-1 rounded text-sm font-medium {{ $dColors[$inspection->disposition] ?? 'bg-gray-100 text-gray-700' }}">
                    {{ ucfirst(str_replace('_', ' ', $inspection->disposition)) }}
                </span>
                <span class="text-sm text-gray-500">
                    {{ __('by') }} {{ $inspection->dispositionBy?->name ?? '—' }}
                    @if($inspection->disposition_at)
                        · {{ $inspection->disposition_at->format('Y-m-d H:i') }}
                    @endif
                </span>
            </div>
            @if($inspection->disposition_notes)
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $inspection->disposition_notes }}</p>
            @endif
        @else
            <p class="text-sm text-gray-500">{{ __('No disposition recorded yet.') }}</p>
            @auth
                @if(auth()->user()->hasAnyRole(['Admin','Supervisor']))
                    <div x-data="{ open: false }" class="mt-3">
                        <button type="button" @click="open = true" class="btn-touch btn-primary text-sm">{{ __('Record Disposition') }}</button>
                        <div x-show="open" x-cloak @click.outside="open = false" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-xl max-w-lg w-full p-6">
                                <h3 class="text-lg font-semibold mb-3">{{ __('Record Disposition') }}</h3>
                                <form method="POST" action="{{ route('inspections.disposition', $inspection) }}">
                                    @csrf
                                    <div class="space-y-2 mb-4">
                                        @foreach([
                                            'accept' => __('Accept — pass to production'),
                                            'accept_with_deviation' => __('Accept with deviation — minor issue, documented'),
                                            'rework' => __('Rework — fix and re-inspect'),
                                            'quarantine' => __('Quarantine — hold pending decision'),
                                            'scrap' => __('Scrap — discard'),
                                            'return_to_supplier' => __('Return to supplier'),
                                            'reject' => __('Reject (no further action)'),
                                        ] as $val => $desc)
                                            <label class="flex items-start gap-2 p-2 rounded border hover:bg-gray-50 dark:hover:bg-slate-700 cursor-pointer">
                                                <input type="radio" name="disposition" value="{{ $val }}" required class="mt-1">
                                                <div>
                                                    <div class="font-medium text-sm">{{ ucfirst(str_replace('_', ' ', $val)) }}</div>
                                                    <div class="text-xs text-gray-500">{{ $desc }}</div>
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                    <textarea name="notes" rows="3" class="form-input w-full text-sm" placeholder="{{ __('Notes (optional)') }}"></textarea>
                                    <div class="flex justify-end gap-2 mt-3">
                                        <button type="button" @click="open = false" class="btn-touch btn-secondary text-sm">{{ __('Cancel') }}</button>
                                        <button type="submit" class="btn-touch btn-primary text-sm">{{ __('Save') }}</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif
            @endauth
        @endif
    </div>

    @if($inspection->issue_id)
        <div class="card mb-4 border-l-4 border-red-500 bg-red-50 dark:bg-red-900/20">
            <strong>{{ __('Non-conformance created') }}: Issue #{{ $inspection->issue_id }}</strong>
            <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">
                {{ __('A non-conformance issue was auto-generated because this inspection failed.') }}
            </p>
        </div>
    @endif

    @if($inspection->isPending() && $inspection->results->isNotEmpty())
        {{-- Pending — show editable form --}}
        <form method="POST" action="{{ route('inspections.record-result', $inspection) }}" class="card mb-4">
            @csrf
            <h2 class="text-lg font-bold mb-3">{{ __('Record measurements') }}</h2>

            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-slate-700">
                    <tr>
                        <th class="text-left p-2">{{ __('Criterion') }}</th>
                        <th class="text-left p-2">{{ __('Type') }}</th>
                        <th class="text-left p-2">{{ __('Spec') }}</th>
                        <th class="text-left p-2">{{ __('Value') }}</th>
                        <th class="text-left p-2">{{ __('Notes') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($inspection->results as $i => $result)
                    <tr class="border-b border-gray-100 dark:border-gray-700">
                        <td class="p-2 font-medium">
                            {{ $result->criterion_name }}
                            @if($result->required)<span class="text-red-500" title="{{ __('Required') }}">*</span>@endif
                        </td>
                        <td class="p-2 text-gray-500">{{ $result->criterion_type }}</td>
                        <td class="p-2 text-gray-500 font-mono text-xs">
                            @if($result->criterion_type === 'measurement')
                                {{ $result->spec_min ?? '−∞' }} … {{ $result->spec_max ?? '+∞' }} {{ $result->unit }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="p-2">
                            <input type="hidden" name="results[{{ $i }}][id]" value="{{ $result->id }}">
                            @if($result->criterion_type === 'measurement')
                                <input type="number" step="0.0001" name="results[{{ $i }}][value_numeric]" value="{{ $result->value_numeric }}" class="form-input w-32">
                            @else
                                <select name="results[{{ $i }}][value_boolean]" class="form-input w-28">
                                    <option value="">—</option>
                                    <option value="1" @selected($result->value_boolean === true)>{{ __('Pass') }}</option>
                                    <option value="0" @selected($result->value_boolean === false)>{{ __('Fail') }}</option>
                                </select>
                            @endif
                        </td>
                        <td class="p-2"><input type="text" name="results[{{ $i }}][notes]" value="{{ $result->notes }}" maxlength="1000" class="form-input w-full"></td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div class="flex gap-2 justify-end mt-3">
                <button class="btn-touch btn-secondary">{{ __('Save progress') }}</button>
            </div>
        </form>

        <form method="POST" action="{{ route('inspections.complete', $inspection) }}" class="card">
            @csrf
            <h2 class="text-lg font-bold mb-3">{{ __('Complete inspection') }}</h2>
            <p class="text-sm text-gray-600 mb-2">
                {{ __('Pass/fail is computed from the recorded results above. If any required criterion fails, a non-conformance issue is created automatically.') }}
            </p>
            <textarea name="notes" rows="2" placeholder="{{ __('Optional notes…') }}" class="form-input w-full mb-3">{{ $inspection->notes }}</textarea>
            <div class="text-right">
                <button class="btn-touch btn-primary"
                        onclick="return confirm('{{ __('Complete this inspection? It cannot be edited afterwards.') }}');">
                    {{ __('Complete') }}
                </button>
            </div>
        </form>
    @else
        {{-- Completed — read-only summary --}}
        <div class="card">
            <h2 class="text-lg font-bold mb-3">{{ __('Results') }}</h2>
            @if($inspection->results->isEmpty())
                <p class="text-gray-500">{{ __('No results recorded.') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-slate-700">
                        <tr>
                            <th class="text-left p-2">{{ __('Criterion') }}</th>
                            <th class="text-left p-2">{{ __('Type') }}</th>
                            <th class="text-left p-2">{{ __('Spec') }}</th>
                            <th class="text-left p-2">{{ __('Value') }}</th>
                            <th class="text-left p-2">{{ __('Result') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($inspection->results as $result)
                        <tr class="border-b border-gray-100 dark:border-gray-700">
                            <td class="p-2 font-medium">{{ $result->criterion_name }}</td>
                            <td class="p-2 text-gray-500">{{ $result->criterion_type }}</td>
                            <td class="p-2 text-gray-500 font-mono text-xs">
                                @if($result->criterion_type === 'measurement')
                                    {{ $result->spec_min ?? '−∞' }} … {{ $result->spec_max ?? '+∞' }} {{ $result->unit }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="p-2 font-mono">
                                {{ $result->value_numeric ?? ($result->value_boolean !== null ? ($result->value_boolean ? 'pass' : 'fail') : ($result->value_text ?? '—')) }}
                            </td>
                            <td class="p-2">
                                @if($result->is_passed === true) <span class="badge badge-green">✓ {{ __('Pass') }}</span>
                                @elseif($result->is_passed === false) <span class="badge badge-red">✗ {{ __('Fail') }}</span>
                                @else <span class="badge badge-gray">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
            @if($inspection->notes)
                <div class="mt-3 text-sm text-gray-700 dark:text-gray-300">
                    <strong>{{ __('Notes') }}:</strong> {{ $inspection->notes }}
                </div>
            @endif
            <div class="text-right mt-3">
                <a href="{{ route('inspections.index') }}" class="btn-touch btn-secondary">{{ __('Back') }}</a>
            </div>
        </div>
    @endif
</div>

<style>
.badge { display:inline-block; padding:3px 8px; border-radius:4px; font-size:11px; font-weight:600; text-transform: capitalize; }
.badge-green  { background:#dcfce7; color:#166534; }
.badge-yellow { background:#fef9c3; color:#854d0e; }
.badge-red    { background:#fee2e2; color:#991b1b; }
.badge-gray   { background:#f3f4f6; color:#4b5563; }
</style>
@endsection
