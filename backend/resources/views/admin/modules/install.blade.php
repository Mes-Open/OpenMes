@extends('layouts.app')

@section('title', 'Modules — Install')

@section('content')
<div class="max-w-3xl mx-auto">

    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['label' => 'Modules', 'url' => route('admin.modules.index')],
        ['label' => 'Install', 'url' => null],
    ]" />

    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Install Module</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">Upload a module from a ZIP file or place the folder manually</p>
    </div>

    {{-- Upload ZIP ───────────────────────────────────────────────────────── --}}
    <div class="card mb-6" x-data="{ filename: '' }">
        <h2 class="text-base font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Upload ZIP file
        </h2>

        <form method="POST" action="{{ route('admin.modules.upload') }}" enctype="multipart/form-data">
            @csrf
            <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 hover:border-blue-400 dark:hover:border-blue-500 rounded-xl p-8 cursor-pointer text-center transition-colors mb-4"
                 @click="$refs.zipInput.click()">
                <svg class="mx-auto h-10 w-10 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                <p class="text-sm text-gray-500 dark:text-gray-400" x-text="filename || 'Click to select a .zip file'"></p>
                <p class="text-xs text-gray-400 mt-1">Max 20 MB</p>
                <input type="file" name="module_zip" x-ref="zipInput" accept=".zip"
                       class="hidden" @change="filename = $refs.zipInput.files[0]?.name || ''" required>
            </div>
            @error('module_zip')
                <p class="text-red-600 text-sm mb-3">{{ $message }}</p>
            @enderror
            <button type="submit" class="btn-touch btn-primary"
                    :disabled="!filename" :class="!filename ? 'opacity-50 cursor-not-allowed' : ''">
                Install Module
            </button>
        </form>

        <p class="text-xs text-gray-400 dark:text-gray-500 mt-4">
            The ZIP must contain a <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">module.json</code>
            file in the root directory or inside a single subfolder.
        </p>
    </div>

    {{-- Manual install guide ─────────────────────────────────────────────── --}}
    <div class="card bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700">
        <h3 class="font-bold text-gray-700 dark:text-gray-300 mb-2">Manual Installation</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
            Place the module folder directly in <code class="bg-white dark:bg-gray-700 border rounded px-1 text-xs">modules/</code>,
            then go to <a href="{{ route('admin.modules.index') }}" class="text-blue-600 hover:underline">Installed Modules</a> and enable it.
        </p>
        <div class="text-xs font-mono bg-white dark:bg-gray-900 border dark:border-gray-700 rounded p-3 text-gray-700 dark:text-gray-300 space-y-0.5 mb-4">
            <p>modules/YourModule/</p>
            <p class="pl-4">├── module.json</p>
            <p class="pl-4">├── Providers/</p>
            <p class="pl-8">│   └── YourModuleServiceProvider.php</p>
            <p class="pl-4">├── Controllers/</p>
            <p class="pl-4">├── Models/</p>
            <p class="pl-4">├── migrations/</p>
            <p class="pl-4">├── views/</p>
            <p class="pl-4">└── README.md</p>
        </div>
        <a href="https://github.com/Mes-Open/OpenMes/blob/main/HOOKS.md" target="_blank" rel="noopener"
           class="text-sm text-blue-600 hover:underline">
            Available hooks and events (HOOKS.md) ↗
        </a>
    </div>

</div>
@endsection
