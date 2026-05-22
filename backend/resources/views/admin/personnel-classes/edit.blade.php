@extends('layouts.app')

@section('title', __('Edit Personnel Class'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Personnel Classes'), 'url' => route('admin.personnel-classes.index')],
    ['label' => $personnelClass->name, 'url' => route('admin.personnel-classes.show', $personnelClass)],
    ['label' => __('Edit'), 'url' => null],
]" />

<div class="max-w-3xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">{{ __('Edit Personnel Class') }}</h1>
            <p class="text-gray-600 mt-1 font-mono text-sm">{{ $personnelClass->code }}</p>
        </div>
        <a href="{{ route('admin.personnel-classes.show', $personnelClass) }}" class="btn-touch btn-secondary">{{ __('← Back') }}</a>
    </div>

    <form method="POST" action="{{ route('admin.personnel-classes.update', $personnelClass) }}">
        @csrf
        @method('PUT')

        @include('admin.personnel-classes.partials.form-fields')

        <div class="flex gap-3 justify-end">
            <a href="{{ route('admin.personnel-classes.show', $personnelClass) }}" class="btn-touch btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn-touch btn-primary">{{ __('Save Changes') }}</button>
        </div>
    </form>
</div>
@endsection
