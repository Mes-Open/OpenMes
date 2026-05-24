@extends('layouts.app')

@section('title', __('New Area'))

@section('content')
@php $site ??= null; @endphp
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Sites'), 'url' => route('admin.sites.index')],
    $site && $site->exists ? ['label' => $site->name, 'url' => route('admin.sites.show', $site)] : ['label' => __('Areas'), 'url' => route('admin.areas.index')],
    ['label' => __('New Area'), 'url' => null],
]" />

<div class="max-w-3xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">{{ __('New Area') }}</h1>
            <p class="text-gray-600 mt-1">{{ __('ISA-95 Area — physical/logical sub-section of a Site.') }}</p>
        </div>
        @if($site && $site->exists)
            <a href="{{ route('admin.sites.show', $site) }}" class="btn-touch btn-secondary">{{ __('← Back to Site') }}</a>
        @else
            <a href="{{ route('admin.areas.index') }}" class="btn-touch btn-secondary">{{ __('← Back') }}</a>
        @endif
    </div>

    <form method="POST" action="{{ $site && $site->exists ? route('admin.sites.areas.store', $site) : route('admin.areas.store') }}">
        @csrf
        @include('admin.areas._form', ['area' => null, 'site' => $site])

        <div class="flex gap-3 justify-end">
            @if($site && $site->exists)
                <a href="{{ route('admin.sites.show', $site) }}" class="btn-touch btn-secondary">{{ __('Cancel') }}</a>
            @else
                <a href="{{ route('admin.areas.index') }}" class="btn-touch btn-secondary">{{ __('Cancel') }}</a>
            @endif
            <button type="submit" class="btn-touch btn-primary">{{ __('Create Area') }}</button>
        </div>
    </form>
</div>
@endsection
