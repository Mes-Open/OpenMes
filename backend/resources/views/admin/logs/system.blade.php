@extends('layouts.app')

@section('title', __('System Logs'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('System Logs'), 'url' => null],
]" />

<div class="max-w-7xl mx-auto">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">{{ __('System Logs') }}</h1>
        <p class="text-gray-600 mt-1">{{ __('Application errors, failed jobs, and deployment events — for diagnostics.') }}</p>
    </div>

    {{-- Tabs --}}
    @php
        $tabs = [
            'app' => __('Application log'),
            'failed_jobs' => __('Failed jobs'),
            'deployments' => __('Deployments'),
        ];
    @endphp
    <div class="border-b mb-4 flex gap-1 flex-wrap">
        @foreach($tabs as $key => $label)
            <a href="{{ route('admin.logs.system', ['tab' => $key]) }}"
               class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition
                      {{ $tab === $key ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- Content per tab --}}
    @if($tab === 'app')
        @include('admin.logs.partials.system-app', [
            'entries' => $entries,
            'availableDates' => $availableDates,
            'date' => $date,
            'level' => $level,
            'search' => $search,
        ])
    @elseif($tab === 'failed_jobs')
        @include('admin.logs.partials.system-failed-jobs', [
            'entries' => $entries,
            'missing' => $missing ?? false,
        ])
    @elseif($tab === 'deployments')
        @include('admin.logs.partials.system-deployments', [
            'entries' => $entries,
            'missing' => $missing ?? false,
        ])
    @endif
</div>
@endsection
