@extends('layouts.app')

@section('title', 'Add Material')

@section('content')
<x-breadcrumbs :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'Materials', 'url' => route('admin.materials.index')],
    ['label' => 'Add Material', 'url' => null],
]" />

<div class="max-w-3xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Add Material</h1>

    <form method="POST" action="{{ route('admin.materials.store') }}" class="card">
        @csrf
        @include('admin.materials._form')

        <div class="flex justify-end gap-3 mt-6 pt-6 border-t">
            <a href="{{ route('admin.materials.index') }}" class="btn-touch btn-secondary">Cancel</a>
            <button type="submit" class="btn-touch btn-primary">Create Material</button>
        </div>
    </form>
</div>
@endsection
