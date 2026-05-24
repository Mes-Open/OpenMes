@if($entityId && $templates->isNotEmpty())
    <div x-data="{ open: false }" class="relative inline-block">
        <button type="button" @click="open = !open" @click.outside="open = false"
                class="btn-touch btn-secondary text-sm inline-flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            {{ $label }}
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div x-show="open" x-cloak x-transition
             class="absolute right-0 mt-1 w-64 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-gray-200 dark:border-slate-700 z-50">
            <div class="p-2">
                <p class="px-2 py-1 text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('Choose template') }}</p>
                @foreach($templates as $template)
                    <div class="px-2 py-1.5">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-200 truncate">
                            {{ $template->name }}
                            @if($template->is_default)<span class="text-[10px] text-blue-600 ml-1">{{ __('default') }}</span>@endif
                        </p>
                        <p class="text-xs text-gray-500">{{ $template->size }} mm · {{ strtoupper($template->barcode_format) }}</p>
                        <div class="flex gap-2 mt-1">
                            <a href="{{ route($routeBase.'.pdf', [$paramName => $entityId, 'template' => $template->id]) }}"
                               target="_blank"
                               class="flex-1 text-center text-xs px-2 py-1 rounded bg-blue-600 text-white hover:bg-blue-700">PDF</a>
                            <a href="{{ route($routeBase.'.zpl', [$paramName => $entityId, 'template' => $template->id]) }}"
                               class="flex-1 text-center text-xs px-2 py-1 rounded bg-gray-700 text-white hover:bg-gray-800">ZPL</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@elseif($entityId)
    <a href="{{ route('packaging.label-templates.index') }}" class="btn-touch btn-secondary text-sm" title="{{ __('Configure label templates first') }}">
        <svg class="w-4 h-4 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
        </svg>
        {{ __('Setup labels…') }}
    </a>
@endif
