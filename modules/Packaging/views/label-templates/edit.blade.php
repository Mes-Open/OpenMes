@extends('layouts.app')

@section('title', 'Edit Label Template')

@section('content')
<x-breadcrumbs :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'Label Templates', 'url' => route('packaging.label-templates.index')],
    ['label' => 'Edit', 'url' => null],
]" />

<div class="max-w-3xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Edit Label Template</h1>
            <p class="text-gray-600 dark:text-gray-300 mt-1">{{ $template->name }}</p>
        </div>
        <a href="{{ route('packaging.label-templates.index') }}" class="btn-touch btn-secondary">← Back</a>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('packaging.label-templates.update', $template) }}">
            @csrf
            @method('PUT')
            @include('packaging::label-templates._form')
            <div class="flex gap-3 justify-end mt-6">
                <a href="{{ route('packaging.label-templates.index') }}" class="btn-touch btn-secondary">Cancel</a>
                <button type="submit" class="btn-touch btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection
