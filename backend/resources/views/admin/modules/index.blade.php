@extends('layouts.app')

@section('title', 'Modules — Installed')

@section('content')
<div class="max-w-5xl mx-auto">

    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['label' => 'Modules', 'url' => null],
        ['label' => 'Installed', 'url' => null],
    ]" />

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">{{ __('Installed Modules') }}</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">{{ __('Enable and disable installed OpenMES extensions') }}</p>
        </div>
        <a href="{{ route('admin.modules.install') }}" class="btn-touch btn-primary text-sm">
            + {{ __('Install Module') }}
        </a>
    </div>

    @if($modules->isEmpty())
        <div class="card text-center py-16">
            <svg class="mx-auto h-16 w-16 text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                      d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
            <p class="text-gray-500 text-lg font-medium">No modules installed</p>
            <p class="text-gray-400 text-sm mt-1">
                <a href="{{ route('admin.modules.install') }}" class="text-blue-600 hover:underline">Install a module from a ZIP file</a>
                or place the module folder in <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">modules/</code>
            </p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($modules as $module)
                <div class="card flex flex-col gap-4 {{ $module['enabled'] ? 'border-l-4 border-blue-400' : '' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-bold text-gray-800 dark:text-white">{{ $module['display_name'] ?? $module['name'] }}</h3>
                                <span class="text-xs text-gray-400 font-mono">v{{ $module['version'] ?? '?' }}</span>
                                @if($module['enabled'])
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                        Enabled
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                                        Disabled
                                    </span>
                                @endif
                                @if($module['has_error'])
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                        Provider Error
                                    </span>
                                @endif
                            </div>
                            @if(!empty($module['author']))
                                <p class="text-xs text-gray-400 mt-0.5">
                                    by
                                    @if(!empty($module['homepage']))
                                        <a href="{{ $module['homepage'] }}" target="_blank" rel="noopener" class="hover:underline">{{ $module['author'] }}</a>
                                    @else
                                        {{ $module['author'] }}
                                    @endif
                                </p>
                            @endif
                        </div>
                    </div>

                    <p class="text-sm text-gray-600 dark:text-gray-400 flex-1">{{ $module['description'] ?? 'No description.' }}</p>

                    @if(!empty($module['hooks']))
                        <div>
                            <p class="text-xs text-gray-400 mb-1">{{ __('Used hooks') }}</p>
                            <div class="flex flex-wrap gap-1">
                                @foreach($module['hooks'] as $hook)
                                    <span class="text-xs bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-300 px-2 py-0.5 rounded font-mono">{{ $hook }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="flex gap-2 pt-2 border-t border-gray-100 dark:border-gray-700">
                        @if($module['enabled'])
                            <form method="POST" action="{{ route('admin.modules.disable', $module['name']) }}">
                                @csrf
                                <button class="btn-touch btn-secondary text-sm">{{ __('Disable') }}</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.modules.enable', $module['name']) }}">
                                @csrf
                                <button class="btn-touch btn-primary text-sm">{{ __('Enable') }}</button>
                            </form>
                        @endif

                        @if($module['name'] !== 'ExampleHooks')
                            @php $confirmLabel = addslashes($module['display_name'] ?? $module['name']); @endphp
                            <form method="POST" action="{{ route('admin.modules.destroy', $module['name']) }}"
                                  onsubmit="return confirm('Uninstall module {{ $confirmLabel }}? Files will be removed.')">
                                @csrf
                                @method('DELETE')
                                <button class="btn-touch btn-secondary text-sm text-red-500 hover:text-red-700">{{ __('Uninstall') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection
