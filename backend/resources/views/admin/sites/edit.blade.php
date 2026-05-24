@extends('layouts.app')

@section('title', __('Edit Site'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Sites'), 'url' => route('admin.sites.index')],
    ['label' => $site->name, 'url' => route('admin.sites.show', $site)],
    ['label' => __('Edit'), 'url' => null],
]" />

<div class="max-w-3xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">{{ __('Edit Site') }}: {{ $site->name }}</h1>
        <a href="{{ route('admin.sites.show', $site) }}" class="btn-touch btn-secondary">{{ __('← Back') }}</a>
    </div>

    <form method="POST" action="{{ route('admin.sites.update', $site) }}">
        @csrf
        @method('PUT')
        @include('admin.sites._form', ['site' => $site])

        <div class="flex gap-3 justify-end">
            <a href="{{ route('admin.sites.show', $site) }}" class="btn-touch btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn-touch btn-primary">{{ __('Save Changes') }}</button>
        </div>
    </form>
</div>
@endsection
