@auth @hasrole('Admin')
<div
    x-data="{
        show: false,
        latest: '',
        name: '',
        releaseUrl: '',
        dismissed: false,
        async init() {
            if (sessionStorage.getItem('update_dismissed')) return;
            const cached = sessionStorage.getItem('update_check');
            if (cached) {
                const d = JSON.parse(cached);
                if (d.available) { this.latest = d.latest; this.name = d.name; this.releaseUrl = d.release_url; this.show = true; }
                return;
            }
            try {
                const r = await fetch('{{ route('admin.update.check') }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const d = await r.json();
                sessionStorage.setItem('update_check', JSON.stringify(d));
                if (d.available) { this.latest = d.latest; this.name = d.name; this.releaseUrl = d.release_url; this.show = true; }
            } catch(e) {}
        },
        dismiss() {
            this.show = false;
            sessionStorage.setItem('update_dismissed', '1');
        }
    }"
    x-show="show && !dismissed"
    x-cloak
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 -translate-y-2"
    x-transition:enter-end="opacity-100 translate-y-0"
    class="shrink-0 flex items-center gap-3 px-4 py-2.5 bg-blue-600 text-white text-sm"
>
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
    </svg>

    <span class="flex-1">
        Update available — <strong x-text="name"></strong>
        <a x-bind:href="releaseUrl" target="_blank" rel="noopener"
           class="underline hover:text-blue-200 ml-1">View changelog</a>
    </span>

    <form method="POST" action="{{ route('admin.update.apply') }}" class="flex items-center gap-2"
          onsubmit="return confirm('Apply update now? The system will run migrations and clear caches.')">
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
@endhasrole @endauth
