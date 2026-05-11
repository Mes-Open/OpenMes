@extends('layouts.app')

@section('title', 'New Label Template')

@section('content')
<x-breadcrumbs :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'Label Templates', 'url' => route('packaging.label-templates.index')],
    ['label' => 'New Template', 'url' => null],
]" />

<div class="max-w-3xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white">New Label Template</h1>
        <a href="{{ route('packaging.label-templates.index') }}" class="btn-touch btn-secondary">← Back</a>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('packaging.label-templates.store') }}">
            @csrf
            @include('packaging::label-templates._form')
            <div class="flex gap-3 justify-end mt-6">
                <a href="{{ route('packaging.label-templates.index') }}" class="btn-touch btn-secondary">Cancel</a>
                <button type="submit" class="btn-touch btn-primary">Create Template</button>
            </div>
        </form>
    </div>
</div>
@endsection
