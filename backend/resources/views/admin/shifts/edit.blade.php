@extends('layouts.app')

@section('title', __('Edit Shift'))

@section('content')
<div class="max-w-xl mx-auto">

    <x-breadcrumbs :items="[
        ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
        ['label' => __('Shifts'), 'url' => route('admin.shifts.index')],
        ['label' => $shift->name, 'url' => null],
    ]" />

    <h1 class="text-2xl font-bold text-gray-800 dark:text-white mb-6">{{ __('Edit:') }} {{ $shift->name }}</h1>

    <form method="POST" action="{{ route('admin.shifts.update', $shift) }}" class="card">
        @csrf
        @method('PUT')
        @include('admin.shifts._form')

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.shifts.index') }}" class="btn-touch btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn-touch btn-primary">{{ __('Save Changes') }}</button>
        </div>
    </form>
</div>
@endsection
