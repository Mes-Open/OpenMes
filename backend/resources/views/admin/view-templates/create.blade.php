@extends('layouts.app')

@section('title', 'Create View Template')

@section('content')
<div class="max-w-2xl mx-auto">

    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['label' => 'View Templates', 'url' => route('admin.view-templates.index')],
        ['label' => 'Create', 'url' => null],
    ]" />

    <h1 class="text-2xl font-bold text-gray-800 dark:text-white mb-6">Create View Template</h1>

    <form method="POST" action="{{ route('admin.view-templates.store') }}" class="card">
        @csrf
        @php $template = new \App\Models\ViewTemplate(); @endphp
        @include('admin.view-templates._form')

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.view-templates.index') }}" class="btn-touch btn-secondary">Cancel</a>
            <button type="submit" class="btn-touch btn-primary">Create Template</button>
        </div>
    </form>
</div>
@endsection
