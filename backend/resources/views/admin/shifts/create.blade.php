@extends('layouts.app')

@section('title', 'Create Shift')

@section('content')
<div class="max-w-xl mx-auto">

    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['label' => 'Shifts', 'url' => route('admin.shifts.index')],
        ['label' => 'Create', 'url' => null],
    ]" />

    <h1 class="text-2xl font-bold text-gray-800 dark:text-white mb-6">Create Shift</h1>

    <form method="POST" action="{{ route('admin.shifts.store') }}" class="card">
        @csrf
        @php $shift = new \App\Models\Shift(); @endphp
        @include('admin.shifts._form')

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.shifts.index') }}" class="btn-touch btn-secondary">Cancel</a>
            <button type="submit" class="btn-touch btn-primary">Create Shift</button>
        </div>
    </form>
</div>
@endsection
