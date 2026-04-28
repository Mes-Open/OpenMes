@extends('layouts.app')

@section('title', 'Edit View Template')

@section('content')
<div class="max-w-2xl mx-auto">

    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['label' => 'View Templates', 'url' => route('admin.view-templates.index')],
        ['label' => $viewTemplate->name, 'url' => null],
    ]" />

    <h1 class="text-2xl font-bold text-gray-800 dark:text-white mb-6">Edit: {{ $viewTemplate->name }}</h1>

    <form method="POST" action="{{ route('admin.view-templates.update', $viewTemplate) }}" class="card">
        @csrf
        @method('PUT')
        @php $template = $viewTemplate; @endphp
        @include('admin.view-templates._form')

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.view-templates.index') }}" class="btn-touch btn-secondary">Cancel</a>
            <button type="submit" class="btn-touch btn-primary">Save Changes</button>
        </div>
    </form>
</div>
@endsection
