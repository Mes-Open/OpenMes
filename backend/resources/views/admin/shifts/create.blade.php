@extends('layouts.app')

@section('title', __('Create Shift'))

@section('content')
<div class="max-w-xl mx-auto">

    <x-breadcrumbs :items="[
        ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
        ['label' => __('Shifts'), 'url' => route('admin.shifts.index')],
        ['label' => __('Create'), 'url' => null],
    ]" />

    <h1 class="text-2xl font-bold text-gray-800 dark:text-white mb-6">{{ __('Create Shift') }}</h1>

    <form method="POST" action="{{ route('admin.shifts.store') }}" class="card">
        @csrf
        @php $shift = new \App\Models\Shift(); @endphp
        @include('admin.shifts._form')

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.shifts.index') }}" class="btn-touch btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn-touch btn-primary">{{ __('Create Shift') }}</button>
        </div>
    </form>
</div>
@endsection
