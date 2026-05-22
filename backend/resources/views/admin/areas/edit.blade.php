@extends('layouts.app')

@section('title', __('Edit Area'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Areas'), 'url' => route('admin.areas.index')],
    ['label' => $area->name, 'url' => route('admin.areas.show', $area)],
    ['label' => __('Edit'), 'url' => null],
]" />

<div class="max-w-3xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">{{ __('Edit Area') }}: {{ $area->name }}</h1>
        <a href="{{ route('admin.areas.show', $area) }}" class="btn-touch btn-secondary">{{ __('← Back') }}</a>
    </div>

    <form method="POST" action="{{ route('admin.areas.update', $area) }}">
        @csrf
        @method('PUT')
        @include('admin.areas._form', ['area' => $area, 'site' => null])

        <div class="flex gap-3 justify-end">
            <a href="{{ route('admin.areas.show', $area) }}" class="btn-touch btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn-touch btn-primary">{{ __('Save Changes') }}</button>
        </div>
    </form>
</div>
@endsection
