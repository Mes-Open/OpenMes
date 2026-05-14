@extends('layouts.app')

@section('title', __('Add Integration'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Integrations'), 'url' => route('admin.integrations.index')],
    ['label' => __('Add Integration'), 'url' => null],
]" />

<div class="max-w-3xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">{{ __('Add Integration') }}</h1>

    <form method="POST" action="{{ route('admin.integrations.store') }}" class="card">
        @csrf
        @include('admin.integrations._form')
        <div class="flex justify-end gap-3 mt-6 pt-6 border-t">
            <a href="{{ route('admin.integrations.index') }}" class="btn-touch btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn-touch btn-primary">{{ __('Create') }}</button>
        </div>
    </form>
</div>
@endsection
