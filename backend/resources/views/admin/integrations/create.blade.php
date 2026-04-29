@extends('layouts.app')

@section('title', 'Add Integration')

@section('content')
<x-breadcrumbs :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'Integrations', 'url' => route('admin.integrations.index')],
    ['label' => 'Add Integration', 'url' => null],
]" />

<div class="max-w-3xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Add Integration</h1>

    <form method="POST" action="{{ route('admin.integrations.store') }}" class="card">
        @csrf
        @include('admin.integrations._form')
        <div class="flex justify-end gap-3 mt-6 pt-6 border-t">
            <a href="{{ route('admin.integrations.index') }}" class="btn-touch btn-secondary">Cancel</a>
            <button type="submit" class="btn-touch btn-primary">Create</button>
        </div>
    </form>
</div>
@endsection
