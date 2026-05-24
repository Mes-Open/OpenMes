@extends('layouts.app')

@section('title', __('Process Segment') . ' — ' . $segment->name)

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Process Segments'), 'url' => route('admin.process-segments.index')],
    ['label' => $segment->name, 'url' => null],
]" />

@php
    $typeColors = [
        'production'  => 'bg-blue-100 text-blue-800',
        'inspection'  => 'bg-amber-100 text-amber-800',
        'maintenance' => 'bg-orange-100 text-orange-800',
        'setup'       => 'bg-gray-100 text-gray-700',
        'cleaning'    => 'bg-green-100 text-green-800',
        'transport'   => 'bg-purple-100 text-purple-800',
        'other'       => 'bg-gray-100 text-gray-700',
    ];
    $typeColor = $typeColors[$segment->segment_type] ?? 'bg-gray-100 text-gray-700';
    $usageCount = $usingSteps->count();
@endphp

<div class="max-w-5xl mx-auto">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
        <div>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="font-mono text-sm text-gray-500">{{ $segment->code }}</span>
                <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $typeColor }}">{{ ucfirst($segment->segment_type) }}</span>
                @if($segment->is_active)
                    <span class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800">{{ __('Active') }}</span>
                @else
                    <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{ __('Inactive') }}</span>
                @endif
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mt-1">{{ $segment->name }}</h1>
            @if($segment->description)
                <p class="text-gray-600 mt-1">{{ $segment->description }}</p>
            @endif
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.process-segments.edit', $segment) }}" class="btn-touch btn-secondary">
                {{ __('Edit') }}
            </a>
            <form method="POST" action="{{ route('admin.process-segments.destroy', $segment) }}" class="inline"
                  onsubmit="return confirm('{{ __('Delete this process segment?') }}');">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="btn-touch {{ $usageCount > 0 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-red-50 text-red-700 hover:bg-red-100' }}"
                        @disabled($usageCount > 0)>
                    {{ __('Delete') }}
                </button>
            </form>
        </div>
    </div>

    {{-- Session notices --}}
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-4">

            {{-- 1. Definition --}}
            <section class="card">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">{{ __('Definition') }}</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Code') }}</dt>
                        <dd class="mt-1 font-mono text-gray-800">{{ $segment->code }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Type') }}</dt>
                        <dd class="mt-1">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $typeColor }}">
                                {{ ucfirst($segment->segment_type) }}
                            </span>
                        </dd>
                    </div>
                    @if($segment->description)
                        <div class="sm:col-span-2">
                            <dt class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Description') }}</dt>
                            <dd class="mt-1 text-gray-700 whitespace-pre-wrap">{{ $segment->description }}</dd>
                        </div>
                    @endif
                    @if($segment->standard_instruction)
                        <div class="sm:col-span-2">
                            <dt class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Standard instruction') }}</dt>
                            <dd class="mt-1 text-gray-700 whitespace-pre-wrap p-3 bg-gray-50 rounded">{{ $segment->standard_instruction }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            {{-- 2. Execution --}}
            <section class="card">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">{{ __('Execution') }}</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <dt class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Workstation type') }}</dt>
                        <dd class="mt-1 text-gray-800">{{ $segment->workstationType?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Estimated duration') }}</dt>
                        <dd class="mt-1 text-gray-800">
                            {{ $segment->estimated_duration_minutes ? $segment->estimated_duration_minutes . ' ' . __('min') : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Required operators') }}</dt>
                        <dd class="mt-1 text-gray-800">{{ $segment->required_operators }}</dd>
                    </div>

                    <div class="sm:col-span-3">
                        <dt class="text-xs text-gray-500 uppercase tracking-wide mb-1">{{ __('Required skills') }}</dt>
                        <dd>
                            @if($requiredSkills->isEmpty())
                                <span class="text-sm text-gray-400">{{ __('— None —') }}</span>
                            @else
                                <div class="flex flex-wrap gap-1">
                                    @foreach($requiredSkills as $skill)
                                        <span class="px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 text-xs font-medium">
                                            {{ $skill->code }} · {{ $skill->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </dd>
                    </div>

                    <div class="sm:col-span-3">
                        <dt class="text-xs text-gray-500 uppercase tracking-wide mb-1">{{ __('Parameters') }}</dt>
                        <dd>
                            @if(empty($segment->parameters))
                                <span class="text-sm text-gray-400">{{ __('— None —') }}</span>
                            @else
                                <pre class="text-xs bg-gray-900 text-gray-100 p-3 rounded overflow-x-auto">{{ json_encode($segment->parameters, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            @endif
                        </dd>
                    </div>
                </dl>
            </section>

            {{-- 3. Usage --}}
            <section class="card">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('Usage') }}</h2>
                <p class="text-xs text-gray-500 mb-3">
                    {{ __('Template steps that reference this segment.') }}
                </p>
                @if($usingSteps->isEmpty())
                    <p class="text-sm text-gray-500 italic">{{ __('Not used by any process template yet.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-gray-500 border-b">
                                    <th class="py-2 pr-3">{{ __('Product') }}</th>
                                    <th class="py-2 pr-3">{{ __('Template') }}</th>
                                    <th class="py-2 pr-3 text-right">{{ __('Step #') }}</th>
                                    <th class="py-2 pr-3">{{ __('Step name') }}</th>
                                    <th class="py-2 pr-3">{{ __('Workstation') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($usingSteps as $step)
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-2 pr-3 text-gray-600">{{ $step->processTemplate?->productType?->name ?? '—' }}</td>
                                        <td class="py-2 pr-3">
                                            @if($step->processTemplate && $step->processTemplate->productType)
                                                <a class="text-blue-600 hover:underline"
                                                   href="{{ route('admin.product-types.process-templates.show', [$step->processTemplate->productType, $step->processTemplate]) }}">
                                                    {{ $step->processTemplate->name }}
                                                </a>
                                            @else
                                                <span class="text-gray-500">{{ $step->processTemplate?->name ?? '—' }}</span>
                                            @endif
                                        </td>
                                        <td class="py-2 pr-3 text-right text-gray-700">{{ $step->step_number }}</td>
                                        <td class="py-2 pr-3 text-gray-800">{{ $step->name }}</td>
                                        <td class="py-2 pr-3 text-gray-600">{{ $step->workstation?->name ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        {{-- Side panel --}}
        <div class="space-y-4">
            <section class="card">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('Metadata') }}</h2>
                <ul class="space-y-2 text-sm">
                    <li class="flex justify-between gap-2">
                        <span class="text-gray-500">{{ __('Used by') }}</span>
                        <span class="font-medium text-gray-800">{{ $usageCount }} {{ __('step(s)') }}</span>
                    </li>
                    <li class="flex justify-between gap-2">
                        <span class="text-gray-500">{{ __('Created') }}</span>
                        <span class="text-gray-700">{{ $segment->created_at?->translatedFormat('d M Y') }}</span>
                    </li>
                    @if($segment->createdBy)
                        <li class="flex justify-between gap-2">
                            <span class="text-gray-500">{{ __('Created by') }}</span>
                            <span class="text-gray-700">{{ $segment->createdBy->name }}</span>
                        </li>
                    @endif
                    <li class="flex justify-between gap-2">
                        <span class="text-gray-500">{{ __('Updated') }}</span>
                        <span class="text-gray-700">{{ $segment->updated_at?->diffForHumans() }}</span>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</div>
@endsection
