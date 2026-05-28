<div x-data="maintenanceReminder()" x-init="init()" x-show="visible" x-cloak x-transition
     class="fixed bottom-4 right-4 z-50 max-w-sm">
    <div class="bg-purple-600 text-white rounded-xl shadow-2xl p-4 border border-purple-400">
        <div class="flex items-start gap-3">
            <div class="text-2xl">🔧</div>
            <div class="flex-1">
                <h4 class="font-bold text-sm">{{ __('Maintenance Reminder') }}</h4>
                @foreach($upcoming as $event)
                    <div class="mt-2 text-sm opacity-90">
                        <span class="font-semibold">{{ $event->title }}</span>
                        <br>
                        <span class="text-xs opacity-75">
                            {{ $event->line?->name ?? $event->workstation?->name ?? '' }}
                            &mdash; {{ $event->scheduled_at->format('H:i') }}
                            ({{ $event->scheduled_at->diffForHumans() }})
                        </span>
                    </div>
                @endforeach
            </div>
            <button @click="dismiss()" class="text-white/60 hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>
</div>

<script>
function maintenanceReminder() {
    return {
        visible: true,
        dismissed: false,
        pollInterval: null,
        lastCount: {{ $upcoming->count() }},

        init() {
            this.playSound();
            this.pollInterval = setInterval(() => this.check(), 30000);
        },

        dismiss() {
            this.visible = false;
            this.dismissed = true;
            if (this.pollInterval) clearInterval(this.pollInterval);
        },

        async check() {
            if (this.dismissed) return;
            try {
                const res = await fetch('{{ route("maintenance.upcoming-count") }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!res.ok) return;
                const data = await res.json();
                if (data.count > this.lastCount) {
                    this.visible = true;
                    this.lastCount = data.count;
                    this.playSound();
                    window.location.reload();
                }
            } catch(e) {}
        },

        playSound() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                // Two-tone chime (less urgent than alert beep)
                [0, 0.3].forEach((delay, i) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.frequency.value = i === 0 ? 523 : 659; // C5, E5
                    osc.type = 'sine';
                    gain.gain.value = 0.2;
                    osc.start(ctx.currentTime + delay);
                    osc.stop(ctx.currentTime + delay + 0.2);
                });
            } catch(e) {}
        }
    };
}
</script>
