@auth @hasrole('Admin')
<div
    x-data="{
        // Banner mode states
        show: false,
        latest: '',
        name: '',
        releaseUrl: '',
        dismissed: false,

        // Progress mode states
        progressMode: false,
        status: null,
        pollTimer: null,
        pollErrors: 0,

        // States that mean the update is actively running.
        ACTIVE_STATES: [
            'queued','downloading','verifying','extracting',
            'backing_up','copying','migrating','rolling_back'
        ],
        TERMINAL_STATES: ['completed','failed','rolled_back'],

        async init() {
            // If an update is in flight (e.g. admin refreshed the page), pick
            // it back up.  Stale terminal states (>1h old) are ignored so the
            // normal 'update available' banner still appears.
            await this.refreshStatus(true);
            if (this.progressMode) return;

            if (sessionStorage.getItem('update_dismissed')) return;
            const cached = sessionStorage.getItem('update_check');
            if (cached) {
                const d = JSON.parse(cached);
                if (d.available) {
                    this.latest = d.latest;
                    this.name = d.name;
                    this.releaseUrl = d.release_url;
                    this.show = true;
                }
                return;
            }
            try {
                const r = await fetch('{{ route('admin.update.check') }}', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const d = await r.json();
                sessionStorage.setItem('update_check', JSON.stringify(d));
                if (d.available) {
                    this.latest = d.latest;
                    this.name = d.name;
                    this.releaseUrl = d.release_url;
                    this.show = true;
                }
            } catch (e) {}
        },

        dismiss() {
            this.show = false;
            sessionStorage.setItem('update_dismissed', '1');
        },

        async onSubmit(ev) {
            if (!confirm('Download and install update? Files will be overwritten (storage, .env and vendor are preserved). The system will run migrations and clear caches.')) {
                ev.preventDefault();
                return false;
            }
            // Allow normal POST → redirect; right after, init() picks up the
            // queued status on the new page and switches to progressMode.
        },

        async refreshStatus(initial = false) {
            try {
                const r = await fetch('{{ route('admin.update.status') }}', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    cache: 'no-store'
                });
                if (!r.ok) {
                    this.pollErrors++;
                    if (this.pollErrors >= 5) this.stopPolling();
                    return;
                }
                this.pollErrors = 0;
                const s = await r.json();
                if (!s) {
                    if (!initial) this.stopPolling();
                    return;
                }

                // Treat stale terminal states (>1h since updated_at) as 'no
                // longer in progress' on first load — banner should behave
                // normally, not show a week-old 'completed' card.
                if (initial && this.TERMINAL_STATES.includes(s.state)) {
                    const updatedAt = s.updated_at ? Date.parse(s.updated_at) : 0;
                    if (!updatedAt || Date.now() - updatedAt > 60 * 60 * 1000) {
                        return;
                    }
                }

                this.status = s;
                this.progressMode = true;
                this.show = true;

                if (this.TERMINAL_STATES.includes(s.state)) {
                    this.stopPolling();
                } else if (!this.pollTimer) {
                    this.startPolling();
                }
            } catch (e) {
                this.pollErrors++;
                if (this.pollErrors >= 5) this.stopPolling();
            }
        },

        startPolling() {
            this.stopPolling();
            this.pollTimer = setInterval(() => this.refreshStatus(false), 3000);
        },

        stopPolling() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },

        bannerColor() {
            if (!this.progressMode) return 'bg-blue-600';
            const s = this.status?.state;
            if (s === 'completed') return 'bg-emerald-600';
            if (s === 'failed' || s === 'rolled_back') return 'bg-rose-600';
            return 'bg-blue-600';
        },

        reloadPage() {
            window.location.reload();
        }
    }"
    x-show="show && !dismissed"
    x-cloak
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 -translate-y-2"
    x-transition:enter-end="opacity-100 translate-y-0"
    :class="bannerColor()"
    class="shrink-0 flex items-center gap-3 px-4 py-2.5 text-white text-sm"
>
    {{-- DEFAULT MODE: update available --}}
    <template x-if="!progressMode">
        <div class="flex items-center gap-3 w-full">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
            </svg>

            <span class="flex-1">
                Update available — <strong x-text="name"></strong>
                <a x-bind:href="releaseUrl" target="_blank" rel="noopener"
                   class="underline hover:text-blue-200 ml-1">View changelog</a>
            </span>

            <form method="POST" action="{{ route('admin.update.apply') }}"
                  class="flex items-center gap-2"
                  @submit="onSubmit($event)">
                @csrf
                <button type="submit"
                        class="px-3 py-1 bg-white text-blue-700 rounded font-medium hover:bg-blue-50 transition-colors text-xs">
                    Update now
                </button>
            </form>

            <button @click="dismiss()" class="p-1 hover:text-blue-200 transition-colors" aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </template>

    {{-- PROGRESS MODE: update running / finished --}}
    <template x-if="progressMode">
        <div class="flex items-center gap-3 w-full">
            {{-- Running spinner --}}
            <template x-if="ACTIVE_STATES.includes(status?.state)">
                <svg class="w-4 h-4 shrink-0 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                          d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"></path>
                </svg>
            </template>
            {{-- Done / failed icon --}}
            <template x-if="status?.state === 'completed'">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M5 13l4 4L19 7"/>
                </svg>
            </template>
            <template x-if="status?.state === 'failed' || status?.state === 'rolled_back'">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01M5 19h14a2 2 0 002-2L13 4a2 2 0 00-3.5 0L3 17a2 2 0 002 2z"/>
                </svg>
            </template>

            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <span class="font-medium">
                        Updating to <span x-text="status?.version || ''"></span>
                    </span>
                    <span class="text-white/80 text-xs uppercase tracking-wide"
                          x-text="(status?.state || '').replace('_',' ')"></span>
                </div>
                <div class="text-xs text-white/90 truncate" x-text="status?.message || ''"></div>

                {{-- Progress bar while running --}}
                <template x-if="ACTIVE_STATES.includes(status?.state)">
                    <div class="mt-1 h-1.5 bg-white/20 rounded overflow-hidden">
                        <div class="h-full bg-white transition-all"
                             :style="'width:' + Math.max(2, Math.min(100, status?.progress || 0)) + '%'"></div>
                    </div>
                </template>
            </div>

            {{-- Reload button on completed --}}
            <template x-if="status?.state === 'completed'">
                <button @click="reloadPage()"
                        class="px-3 py-1 bg-white text-emerald-700 rounded font-medium hover:bg-emerald-50 transition-colors text-xs">
                    Reload
                </button>
            </template>

            {{-- Dismiss button on terminal failure (so banner can be cleared) --}}
            <template x-if="status?.state === 'failed' || status?.state === 'rolled_back'">
                <button @click="dismiss()" class="p-1 hover:text-white/70 transition-colors" aria-label="Dismiss">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </template>
        </div>
    </template>
</div>
@endhasrole @endauth
