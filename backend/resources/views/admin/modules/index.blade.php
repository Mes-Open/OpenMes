@extends('layouts.app')

@section('title', 'Modules')

@section('content')
<div class="max-w-5xl mx-auto">

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Modules</h1>
            <p class="text-gray-600 mt-1">Extend OpenMES with community or custom modules</p>
        </div>
        <a href="https://github.com/Mes-Open/OpenMes/discussions" target="_blank" rel="noopener"
           class="btn-touch btn-secondary text-sm">
            Community Modules ↗
        </a>
    </div>

    {{-- Install from ZIP --}}
    <div class="card mb-8" x-data="{ open: false }">
        <button @click="open = !open"
                class="flex items-center gap-2 text-base font-bold text-gray-800 w-full text-left">
            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Install Module from ZIP
            <svg class="w-4 h-4 ml-auto text-gray-400 transition-transform" :class="{ 'rotate-180': open }"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        <div x-show="open" x-transition class="mt-4 border-t pt-4">
            <form method="POST" action="{{ route('admin.modules.upload') }}" enctype="multipart/form-data"
                  x-data="{ filename: '' }">
                @csrf
                <div class="flex flex-col sm:flex-row gap-3 items-start">
                    <div class="flex-1">
                        <div class="border-2 border-dashed border-gray-300 hover:border-blue-400 rounded-lg p-4 cursor-pointer text-center transition-colors"
                             @click="$refs.zipInput.click()">
                            <svg class="mx-auto h-8 w-8 text-gray-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <p class="text-sm text-gray-500" x-text="filename || 'Click to select .zip file'"></p>
                            <input type="file" name="module_zip" x-ref="zipInput" accept=".zip"
                                   class="hidden" @change="filename = $refs.zipInput.files[0]?.name || ''" required>
                        </div>
                        @error('module_zip')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="btn-touch btn-primary text-sm flex-shrink-0"
                            :disabled="!filename" :class="!filename ? 'opacity-50' : ''">
                        Install
                    </button>
                </div>
            </form>
            <p class="text-xs text-gray-400 mt-3">
                The ZIP must contain a <code class="bg-gray-100 px-1 rounded">module.json</code> at its root or inside a single top-level directory.
                Max 20 MB.
            </p>
        </div>
    </div>

    {{-- Module Cards --}}
    @if($modules->isEmpty())
        <div class="card text-center py-16">
            <svg class="mx-auto h-16 w-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                      d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
            <p class="text-gray-500 text-lg font-medium">No modules installed</p>
            <p class="text-gray-400 text-sm mt-1">Upload a ZIP above or drop module folders into <code class="bg-gray-100 px-1 rounded">backend/modules/</code></p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($modules as $module)
                <div class="card flex flex-col gap-4 {{ $module['enabled'] ? 'border-l-4 border-blue-400' : '' }}">
                    {{-- Header --}}
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-bold text-gray-800">{{ $module['display_name'] ?? $module['name'] }}</h3>
                                <span class="text-xs text-gray-400 font-mono">v{{ $module['version'] ?? '?' }}</span>
                                @if($module['enabled'])
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                        Enabled
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                                        Disabled
                                    </span>
                                @endif
                                @if($module['has_error'])
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                        Provider error
                                    </span>
                                @endif
                            </div>
                            @if(!empty($module['author']))
                                <p class="text-xs text-gray-400 mt-0.5">
                                    by
                                    @if(!empty($module['homepage']))
                                        <a href="{{ $module['homepage'] }}" target="_blank" rel="noopener"
                                           class="hover:underline">{{ $module['author'] }}</a>
                                    @else
                                        {{ $module['author'] }}
                                    @endif
                                </p>
                            @endif
                        </div>
                    </div>

                    {{-- Description --}}
                    <p class="text-sm text-gray-600 flex-1">{{ $module['description'] ?? 'No description.' }}</p>

                    {{-- Hooks --}}
                    @if(!empty($module['hooks']))
                        <div>
                            <p class="text-xs text-gray-400 mb-1">Hooks used</p>
                            <div class="flex flex-wrap gap-1">
                                @foreach($module['hooks'] as $hook)
                                    <span class="text-xs bg-purple-50 text-purple-700 px-2 py-0.5 rounded font-mono">
                                        {{ $hook }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Actions --}}
                    <div class="flex gap-2 pt-2 border-t border-gray-100">
                        @if($module['enabled'])
                            <form method="POST" action="{{ route('admin.modules.disable', $module['name']) }}">
                                @csrf
                                <button class="btn-touch btn-secondary text-sm">Disable</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.modules.enable', $module['name']) }}">
                                @csrf
                                <button class="btn-touch btn-primary text-sm">Enable</button>
                            </form>
                        @endif

                        @if($module['name'] !== 'ExampleHooks')
                            <form method="POST" action="{{ route('admin.modules.destroy', $module['name']) }}"
                                  onsubmit="return confirm('Uninstall module {{ addslashes($module[\'display_name\'] ?? $module[\'name\']) }}? This will delete its files.')">
                                @csrf
                                @method('DELETE')
                                <button class="btn-touch btn-secondary text-sm text-red-500 hover:text-red-700">Uninstall</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Developer guide --}}
    <div class="card mt-8 bg-gray-50 border border-gray-200">
        <h3 class="font-bold text-gray-700 mb-2">Building your own module</h3>
        <p class="text-sm text-gray-600 mb-3">
            Create a folder in <code class="bg-white border rounded px-1 text-xs">backend/modules/YourModule/</code> with a
            <code class="bg-white border rounded px-1 text-xs">module.json</code> and a Laravel ServiceProvider.
            See the <strong>ExampleHooks</strong> module for a working reference.
        </p>
        <div class="text-xs font-mono bg-white border rounded p-3 text-gray-700 space-y-0.5">
            <p>modules/YourModule/</p>
            <p class="pl-4">├── module.json</p>
            <p class="pl-4">├── Providers/</p>
            <p class="pl-8">│   └── YourModuleServiceProvider.php</p>
            <p class="pl-4">├── Listeners/</p>
            <p class="pl-8">│   └── YourListener.php</p>
            <p class="pl-4">└── README.md</p>
        </div>
        <a href="https://github.com/Mes-Open/OpenMes/blob/main/HOOKS.md" target="_blank" rel="noopener"
           class="text-sm text-blue-600 hover:underline mt-3 inline-block">
            View all available hooks (HOOKS.md) ↗
        </a>
    </div>

</div>
@endsection
