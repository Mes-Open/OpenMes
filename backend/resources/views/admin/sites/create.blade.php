@extends('layouts.app')

@section('title', __('New Site'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Sites'), 'url' => route('admin.sites.index')],
    ['label' => __('New Site'), 'url' => null],
]" />

<div class="max-w-3xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">{{ __('New Site') }}</h1>
            <p class="text-gray-600 mt-1">{{ __('ISA-95 Site — top-level physical/operational location.') }}</p>
        </div>
        <a href="{{ route('admin.sites.index') }}" class="btn-touch btn-secondary">{{ __('← Back') }}</a>
    </div>

    <form method="POST" action="{{ route('admin.sites.store') }}">
        @csrf
        @include('admin.sites._form', ['site' => null])

        <div class="flex gap-3 justify-end">
            <a href="{{ route('admin.sites.index') }}" class="btn-touch btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn-touch btn-primary">{{ __('Create Site') }}</button>
        </div>
    </form>
</div>
@endsection
