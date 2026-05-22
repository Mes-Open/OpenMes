@extends('layouts.app')

@section('title', __('Worker') . ' — ' . $worker->name)

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Workers'), 'url' => route('admin.workers.index')],
    ['label' => $worker->name, 'url' => null],
]" />

@php
    $today = now()->startOfDay();
    $soonCut = now()->copy()->addDays(30)->startOfDay();
@endphp

<div class="max-w-5xl mx-auto" x-data="{ showCertModal: false }">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
        <div>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="font-mono text-sm text-gray-500">{{ $worker->code }}</span>
                @if($worker->is_active)
                    <span class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800">{{ __('Active') }}</span>
                @else
                    <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{ __('Inactive') }}</span>
                @endif
                @if($worker->personnelClass)
                    <span class="px-2 py-0.5 rounded-full text-xs bg-indigo-50 text-indigo-700">
                        {{ $worker->personnelClass->name }}
                    </span>
                @endif
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mt-1">{{ $worker->name }}</h1>
            <p class="text-gray-600 mt-1 text-sm">
                @if($worker->crew) {{ __('Crew') }}: {{ $worker->crew->name }} · @endif
                @if($worker->wageGroup) {{ __('Wage group') }}: {{ $worker->wageGroup->name }} · @endif
                @if($worker->email) {{ $worker->email }} @endif
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.workers.edit', $worker) }}" class="btn-touch btn-secondary">{{ __('Edit') }}</a>
        </div>
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

    <section class="card">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-2">
            <div>
                <h2 class="text-lg font-semibold text-gray-700">{{ __('Certifications') }}</h2>
                <p class="text-xs text-gray-500">{{ __('ISA-95 Personnel Capability — issued skill certifications with validity windows.') }}</p>
            </div>
            <button type="button" @click="showCertModal = true" class="btn-touch btn-primary inline-flex items-center gap-2 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ __('Add certification') }}
            </button>
        </div>

        @if($worker->skills->isEmpty())
            <p class="text-sm text-gray-500 italic">{{ __('No certifications recorded.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 border-b">
                            <th class="py-2 pr-3">{{ __('Skill') }}</th>
                            <th class="py-2 pr-3">{{ __('Cert level') }}</th>
                            <th class="py-2 pr-3">{{ __('From') }}</th>
                            <th class="py-2 pr-3">{{ __('Until') }}</th>
                            <th class="py-2 pr-3">{{ __('Status') }}</th>
                            <th class="py-2 pr-3">{{ __('Notes') }}</th>
                            <th class="py-2 pr-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($worker->skills as $skill)
                            @php
                                $until = $skill->pivot->certified_until
                                    ? \Carbon\Carbon::parse($skill->pivot->certified_until)
                                    : null;
                                $status = 'valid';
                                if ($until && $until->lt($today)) {
                                    $status = 'expired';
                                } elseif ($until && $until->lte($soonCut)) {
                                    $status = 'expiring';
                                }
                            @endphp
                            <tr>
                                <td class="py-2 pr-3">
                                    <div class="font-medium text-gray-800">{{ $skill->name }}</div>
                                    <div class="text-xs font-mono text-gray-500">{{ $skill->code }}</div>
                                </td>
                                <td class="py-2 pr-3">
                                    <span class="px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 text-xs font-medium">
                                        {{ ucfirst($skill->pivot->cert_level ?? 'operator') }}
                                    </span>
                                </td>
                                <td class="py-2 pr-3 text-gray-700">{{ $skill->pivot->certified_from ?? '—' }}</td>
                                <td class="py-2 pr-3 text-gray-700">{{ $skill->pivot->certified_until ?? __('Never') }}</td>
                                <td class="py-2 pr-3">
                                    @if($status === 'valid')
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800">{{ __('Valid') }}</span>
                                    @elseif($status === 'expiring')
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-800">{{ __('Expires soon') }}</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-800">{{ __('Expired') }}</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-3 text-xs text-gray-500">{{ $skill->pivot->cert_notes }}</td>
                                <td class="py-2 pr-3 text-right">
                                    <form method="POST" action="{{ route('admin.workers.skills.detach', [$worker, $skill]) }}"
                                          onsubmit="return confirm('{{ __('Remove this certification?') }}');"
                                          class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 p-1" title="{{ __('Remove') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <!-- Add certification modal -->
    <div x-show="showCertModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
         @keydown.escape.window="showCertModal = false">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4" @click.outside="showCertModal = false">
            <form method="POST" action="{{ route('admin.workers.skills.attach', $worker) }}">
                @csrf
                <div class="p-5 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">{{ __('Add certification') }}</h3>
                    <p class="text-xs text-gray-500 mt-1">{{ __('Record a skill certification for') }} {{ $worker->name }}.</p>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <label class="form-label">{{ __('Skill') }} <span class="text-red-500">*</span></label>
                        <select name="skill_id" class="form-input w-full" required>
                            <option value="">{{ __('— Select —') }}</option>
                            @foreach($skills as $skill)
                                <option value="{{ $skill->id }}">{{ $skill->name }} ({{ $skill->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">{{ __('Cert level') }} <span class="text-red-500">*</span></label>
                        <select name="cert_level" class="form-input w-full" required>
                            @foreach($levels as $lvl)
                                <option value="{{ $lvl }}" @selected($lvl === 'operator')>{{ ucfirst($lvl) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">{{ __('Certified from') }}</label>
                            <input type="date" name="certified_from" class="form-input w-full">
                        </div>
                        <div>
                            <label class="form-label">{{ __('Certified until') }}</label>
                            <input type="date" name="certified_until" class="form-input w-full">
                            <p class="text-xs text-gray-400 mt-1">{{ __('Leave blank for no expiry.') }}</p>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">{{ __('Notes') }}</label>
                        <textarea name="cert_notes" rows="2" class="form-input w-full" maxlength="1000"></textarea>
                    </div>
                </div>
                <div class="p-5 border-t border-gray-200 flex justify-end gap-2">
                    <button type="button" @click="showCertModal = false" class="btn-touch btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn-touch btn-primary">{{ __('Save certification') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
