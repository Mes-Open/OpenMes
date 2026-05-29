{{-- Runtime status banner. Expects $runtime = RuntimeMonitor::connectionRuntime(). --}}
@if($runtime['alive'])
    <div class="rounded-lg border border-green-200 bg-green-50 p-3 flex items-center gap-3">
        <span class="w-2.5 h-2.5 rounded-full bg-green-500 animate-pulse shrink-0"></span>
        <p class="text-sm text-green-800">
            <strong>{{ $runtime['label'] }}</strong> {{ __('is running') }}
            @if($runtime['seconds_ago'] !== null)<span class="text-green-600">({{ __('last seen :n s ago', ['n' => $runtime['seconds_ago']]) }})</span>@endif
        </p>
    </div>
@else
    <div class="rounded-lg border border-amber-300 bg-amber-50 p-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.75-2.97L12.75 4.97a2 2 0 00-3.5 0l-7 12A2 2 0 005.07 19z"/>
            </svg>
            <div class="flex-1">
                <p class="text-sm font-semibold text-amber-800">
                    {{ $runtime['label'] }} {{ __('is not running') }}
                </p>
                <p class="text-xs text-amber-700 mt-1">
                    {{ __('This connection is configured but no process is reading from the machine. Start one of the following:') }}
                </p>
                @if($runtime['command'])
                    <p class="text-[11px] text-amber-700 mt-2 mb-1">{{ __('Bare metal / local:') }}</p>
                    <code class="block bg-gray-900 text-green-300 rounded p-2 text-xs font-mono select-all">{{ $runtime['command'] }}</code>
                @endif
                @if($runtime['docker'])
                    <p class="text-[11px] text-amber-700 mt-2 mb-1">{{ __('Docker:') }}</p>
                    <code class="block bg-gray-900 text-green-300 rounded p-2 text-xs font-mono select-all">{{ $runtime['docker'] }}</code>
                @endif
            </div>
        </div>
    </div>
@endif
