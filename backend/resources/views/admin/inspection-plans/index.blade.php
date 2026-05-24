@extends('layouts.app')

@section('title', __('Inspection Plans'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Inspection Plans'), 'url' => null],
]" />

<div class="max-w-6xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">{{ __('Inspection Plans') }}</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">{{ __('Criteria templates used to inspect incoming material lots.') }}</p>
        </div>
        <a href="{{ route('admin.inspection-plans.create') }}" class="btn-touch btn-primary">{{ __('New plan') }}</a>
    </div>

    @if($plans->isEmpty())
        <div class="card text-center py-10 text-gray-500">{{ __('No inspection plans yet.') }}</div>
    @else
        <div class="card overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Name') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Scope') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Criteria') }}</th>
                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">{{ __('Active') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($plans as $plan)
                    <tr>
                        <td class="px-3 py-2 font-medium">{{ $plan->name }}</td>
                        <td class="px-3 py-2 text-gray-500">
                            @if($plan->material) {{ __('Material') }}: {{ $plan->material->name }}
                            @elseif($plan->materialType) {{ __('Type') }}: {{ $plan->materialType->name }}
                            @else {{ __('Generic') }}
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right font-mono">{{ count($plan->criteria) }}</td>
                        <td class="px-3 py-2 text-center">
                            @if($plan->is_active) <span class="badge badge-green">✓</span>
                            @else <span class="badge badge-gray">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right">
                            <a href="{{ route('admin.inspection-plans.edit', $plan) }}" class="text-blue-600 hover:underline mr-3">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('admin.inspection-plans.destroy', $plan) }}" class="inline"
                                  onsubmit="return confirm('{{ __('Delete this plan?') }}');">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
