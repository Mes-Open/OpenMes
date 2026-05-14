@extends('layouts.app')

@section('title', __('Modules — Store'))

@section('content')
<div class="max-w-3xl mx-auto">

    <x-breadcrumbs :items="[
        ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
        ['label' => __('Modules'), 'url' => route('admin.modules.index')],
        ['label' => __('Store'), 'url' => null],
    ]" />

    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white">{{ __('Module Store') }}</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">{{ __('Browse and install ready-made OpenMES modules') }}</p>
    </div>

    <div class="card text-center py-20">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-100 dark:bg-gray-700 mb-6">
            <svg class="w-10 h-10 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
        </div>
        <h2 class="text-2xl font-bold text-gray-700 dark:text-gray-200 mb-2">{{ __('Coming Soon') }}</h2>
        <p class="text-gray-500 dark:text-gray-400 text-sm max-w-md mx-auto mb-6">
            {{ __('The module store is being prepared. Soon you will be able to browse, purchase and install certified OpenMES extensions with a single click.') }}
        </p>
        <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300 border border-yellow-200 dark:border-yellow-700">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ __('Coming soon') }}
        </span>

        <div class="mt-8 pt-8 border-t border-gray-200 dark:border-gray-700">
            <p class="text-xs text-gray-400 dark:text-gray-500 mb-3">{{ __('In the meantime, you can install modules manually:') }}</p>
            <a href="{{ route('admin.modules.install') }}" class="btn-touch btn-secondary text-sm">
                {{ __('Install from ZIP file') }}
            </a>
        </div>
    </div>

</div>
@endsection
