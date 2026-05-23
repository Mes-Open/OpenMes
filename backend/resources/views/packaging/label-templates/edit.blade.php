@extends('layouts.app')

@section('title', __('Edit Label Template'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Label Templates'), 'url' => route('packaging.label-templates.index')],
    ['label' => __('Edit'), 'url' => null],
]" />

<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('packaging.label-templates.index') }}" class="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            {{ __('Back to Label Templates') }}
        </a>
        <h1 class="text-3xl font-bold text-gray-800">{{ __('Edit Label Template') }}</h1>
        <p class="text-gray-600 mt-1">{{ $template->name }}</p>
    </div>

    <form method="POST" action="{{ route('packaging.label-templates.update', $template) }}" class="space-y-6">
        @csrf
        @method('PUT')
        @include('packaging.label-templates._form')
        <div class="flex gap-3 justify-end">
            <a href="{{ route('packaging.label-templates.index') }}" class="btn-touch btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn-touch btn-primary">{{ __('Save Changes') }}</button>
        </div>
    </form>
</div>
@endsection
