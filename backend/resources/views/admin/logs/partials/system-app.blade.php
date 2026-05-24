{{-- Application log tab: filters + entries list + live tail toggle --}}

<form method="GET" action="{{ route('admin.logs.system') }}" class="card mb-6">
    <input type="hidden" name="tab" value="app">

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div>
            <label for="date" class="form-label text-xs">{{ __('Date') }}</label>
            @if(count($availableDates) > 0)
                <select id="date" name="date" class="form-input w-full">
                    @foreach($availableDates as $d)
                        <option value="{{ $d }}" @selected($date->format('Y-m-d') === $d)>{{ $d }}</option>
                    @endforeach
                </select>
            @else
                <input id="date" type="date" name="date" value="{{ $date->format('Y-m-d') }}" class="form-input w-full">
            @endif
        </div>

        <div>
            <label for="level" class="form-label text-xs">{{ __('Level') }}</label>
            <select id="level" name="level" class="form-input w-full">
                <option value="">{{ __('All levels') }}</option>
                @foreach(['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'] as $lvl)
                    <option value="{{ $lvl }}" @selected($level === $lvl)>{{ ucfirst($lvl) }}</option>
                @endforeach
            </select>
        </div>

        <div class="sm:col-span-2">
            <label for="search" class="form-label text-xs">{{ __('Search') }}</label>
            <input id="search" type="text" name="search" value="{{ $search }}"
                   placeholder="{{ __('Search message or stack trace…') }}"
                   class="form-input w-full">
        </div>
    </div>

    <div class="flex flex-wrap gap-2 mt-3">
        <button type="submit" class="btn-touch btn-primary text-sm">{{ __('Apply') }}</button>
        <a href="{{ route('admin.logs.system', ['tab' => 'app']) }}" class="btn-touch btn-secondary text-sm">{{ __('Clear') }}</a>
    </div>
</form>

<div x-data="appLogViewer({{ Js::from($entries) }})">
    {{-- Live tail toggle row --}}
    <div class="flex items-center justify-between mb-3">
        <button type="button"
                @click="toggleLive()"
                :aria-pressed="live"
                class="btn-touch btn-secondary text-sm flex items-center gap-2">
            <span x-show="!live">&#9654; {{ __('Live tail') }}</span>
            <span x-show="live" class="flex items-center gap-2" x-cloak>
                <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse" aria-hidden="true"></span>
                {{ __('Live — stop') }}
            </span>
        </button>
        <span x-show="live" class="text-xs text-gray-500" x-cloak>
            <span x-show="!error">{{ __('Auto-refreshing every 5s') }}</span>
            <span x-show="error" class="text-red-600" x-text="error" x-cloak></span>
        </span>
    </div>

    {{-- Entries table --}}
    <div class="card overflow-hidden">
        <template x-for="(entry, idx) in entries" :key="idx + ':' + entry.timestamp + ':' + (entry.message || '').substring(0, 40)">
            <details class="border-b last:border-b-0">
                <summary class="px-4 py-3 hover:bg-gray-50 cursor-pointer flex items-start gap-3">
                    <span class="font-mono text-xs text-gray-500 whitespace-nowrap mt-0.5" x-text="entry.timestamp"></span>
                    <span class="inline-block px-2 py-0.5 rounded text-xs uppercase whitespace-nowrap"
                          :class="badgeClass(entry.level)"
                          x-text="entry.level"></span>
                    <span class="text-xs text-gray-400 whitespace-nowrap" x-text="entry.environment"></span>
                    <span class="text-sm text-gray-800 break-all flex-1" x-text="truncate(entry.message, 300)"></span>
                </summary>
                <template x-if="(entry.context && entry.context.trim() !== '') || (entry.message && entry.message.length > 300)">
                    <pre class="bg-gray-50 text-xs text-gray-700 px-4 py-3 overflow-x-auto whitespace-pre-wrap break-words border-t"
                         x-text="entry.message + (entry.context ? '\n\n' + entry.context.replace(/\s+$/, '') : '')"></pre>
                </template>
            </details>
        </template>
        <template x-if="entries.length === 0">
            <div class="px-4 py-16 text-center text-gray-400">
                {{ __('No log entries match your filters.') }}
            </div>
        </template>
    </div>

    <p class="text-xs text-gray-400 mt-2" x-show="entries.length > 0" x-cloak>
        <span x-text="entries.length"></span>
        {{ __('entries shown (most recent first). Older entries beyond the 2 MB tail window are not displayed.') }}
    </p>
</div>

<script>
    function appLogViewer(initial) {
        return {
            entries: Array.isArray(initial) ? initial : [],
            live: false,
            pollTimer: null,
            error: null,
            async refresh() {
                try {
                    const r = await fetch(@json(route('admin.logs.system.tail')), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        cache: 'no-store',
                        credentials: 'same-origin',
                    });
                    if (!r.ok) {
                        this.error = '{{ __('Live tail error') }}: ' + r.status;
                        return;
                    }
                    const d = await r.json();
                    this.entries = Array.isArray(d.entries) ? d.entries : [];
                    this.error = null;
                } catch (e) {
                    this.error = '{{ __('Live tail unreachable') }}';
                }
            },
            toggleLive() {
                this.live ? this.stop() : this.start();
            },
            start() {
                this.live = true;
                this.error = null;
                this.refresh();
                this.pollTimer = setInterval(() => this.refresh(), 5000);
            },
            stop() {
                this.live = false;
                if (this.pollTimer) {
                    clearInterval(this.pollTimer);
                    this.pollTimer = null;
                }
            },
            truncate(s, n) {
                if (!s) return '';
                return s.length > n ? s.substring(0, n) + '…' : s;
            },
            badgeClass(level) {
                const map = {
                    'debug':     'bg-gray-100 text-gray-600',
                    'info':      'bg-blue-100 text-blue-700',
                    'notice':    'bg-blue-100 text-blue-700',
                    'warning':   'bg-amber-100 text-amber-800',
                    'error':     'bg-red-100 text-red-700',
                    'critical':  'bg-red-200 text-red-900 font-bold',
                    'alert':     'bg-red-200 text-red-900 font-bold',
                    'emergency': 'bg-red-300 text-red-900 font-bold',
                };
                return map[(level || '').toLowerCase()] || 'bg-gray-100 text-gray-600';
            },
        };
    }
</script>
