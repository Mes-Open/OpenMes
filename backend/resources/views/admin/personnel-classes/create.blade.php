@extends('layouts.app')

@section('title', __('New Personnel Class'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Personnel Classes'), 'url' => route('admin.personnel-classes.index')],
    ['label' => __('New'), 'url' => null],
]" />

<div class="max-w-3xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">{{ __('New Personnel Class') }}</h1>
        <a href="{{ route('admin.personnel-classes.index') }}" class="btn-touch btn-secondary">{{ __('← Back') }}</a>
    </div>

    <form method="POST" action="{{ route('admin.personnel-classes.store') }}">
        @csrf

        @include('admin.personnel-classes.partials.form-fields')

        <div class="flex gap-3 justify-end">
            <a href="{{ route('admin.personnel-classes.index') }}" class="btn-touch btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn-touch btn-primary">{{ __('Create') }}</button>
        </div>
    </form>
</div>
@endsection
