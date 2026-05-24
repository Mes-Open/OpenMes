@extends('layouts.app')

@section('title', __('Edit Maintenance Event'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Maintenance Events'), 'url' => route('admin.maintenance-events.index')],
    ['label' => $event->title, 'url' => route('admin.maintenance-events.show', $event)],
    ['label' => __('Edit'), 'url' => null],
]" />

<div class="max-w-3xl mx-auto">
    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.maintenance-events.show', $event) }}"
           class="text-gray-500 hover:text-gray-700 inline-flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 19.5 8.25 12l7.5-7.5"/>
            </svg>
            <span class="text-sm">{{ __('Back') }}</span>
        </a>
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-gray-800 truncate">{{ __('Edit maintenance event') }}</h1>
            <p class="text-sm text-gray-600 truncate">{{ $event->title }}</p>
        </div>
    </div>

    @if($errors->any())
        <div class="card mb-4 border-l-4 border-red-400 bg-red-50">
            <p class="text-sm font-semibold text-red-700 mb-1">{{ __('Please fix the errors below:') }}</p>
            <ul class="text-sm text-red-700 list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.maintenance-events.update', $event) }}">
        @csrf
        @method('PUT')

        @include('admin.maintenance-events.partials.form-fields', ['event' => $event])

        <div class="flex justify-end gap-2 mt-6">
            <a href="{{ route('admin.maintenance-events.show', $event) }}" class="btn-touch btn-secondary">
                {{ __('Cancel') }}
            </a>
            <button type="submit" class="btn-touch btn-primary">
                {{ __('Save changes') }}
            </button>
        </div>
    </form>
</div>
@endsection
