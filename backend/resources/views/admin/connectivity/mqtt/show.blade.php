@extends('layouts.app')
@section('title', $connection->name . ' — MQTT Connection')

@section('content')
<div class="p-6 space-y-6">

    {{-- Header --}}
    <div class="flex items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.connectivity.mqtt.index') }}"
               class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 flex items-center gap-1 mb-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                MQTT Connections
            </a>
            <div class="flex items-center gap-3">
                @php
                    $colorMap = ['green' => 'bg-green-500', 'yellow' => 'bg-yellow-400', 'red' => 'bg-red-500', 'slate' => 'bg-slate-400'];
                    $dot = $colorMap[$connection->statusColor()] ?? 'bg-slate-400';
                @endphp
                <span class="w-3 h-3 rounded-full {{ $dot }} {{ $connection->status === 'connected' ? 'animate-pulse' : '' }}"></span>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $connection->name }}</h1>
                @if(!$connection->is_active)
                    <span class="text-xs px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 rounded-full">Inactive</span>
                @endif
            </div>
            @if($connection->mqttConnection)
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 font-mono">
                    {{ $connection->mqttConnection->broker_host }}:{{ $connection->mqttConnection->broker_port }}
                    @if($connection->mqttConnection->use_tls)
                        · <span class="text-green-600 dark:text-green-400">TLS</span>
                    @endif
                    · QoS {{ $connection->mqttConnection->qos_default }}
                </p>
            @endif
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.connectivity.mqtt.edit', $connection) }}"
               class="px-4 py-2 text-sm font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200
                      rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                Edit
            </a>
            <form method="POST" action="{{ route('admin.connectivity.mqtt.toggle-active', $connection) }}">
                @csrf
                <button type="submit"
                        class="px-4 py-2 text-sm font-medium rounded-lg transition-colors
                               {{ $connection->is_active
                                   ? 'bg-yellow-50 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300 hover:bg-yellow-100'
                                   : 'bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300 hover:bg-green-100' }}">
                    {{ $connection->is_active ? 'Disable' : 'Enable' }}
                </button>
            </form>
        </div>
    </div>

    @if($errors->any())
        <div class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg dark:bg-red-900/20 dark:border-red-700 dark:text-red-300">
            <ul class="list-disc list-inside space-y-1 text-sm">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 text-center">
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $connection->topics->count() }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Topics</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 text-center">
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($connection->messages_received) }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Messages received</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 text-center">
            <p class="text-2xl font-bold text-gray-900 dark:text-white capitalize">{{ $connection->status }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                {{ $connection->last_connected_at ? $connection->last_connected_at->diffForHumans() : 'Never' }}
            </p>
        </div>
    </div>

    {{-- Topics & Mappings --}}
    <div>
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Topics & Mappings</h2>
        </div>

        <div class="space-y-4">
            @forelse($connection->topics as $topic)
                @include('admin.connectivity.mqtt.partials.topic-card', ['topic' => $topic, 'connection' => $connection])
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-dashed border-gray-300 dark:border-gray-600 p-8 text-center text-gray-400 dark:text-gray-500">
                    <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                    </svg>
                    <p class="text-sm">No topics subscribed yet.</p>
                </div>
            @endforelse

            {{-- Add topic form --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4"
                 x-data="{ open: false }">
                <button @click="open = !open" type="button"
                        class="flex items-center gap-2 text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add topic
                </button>
                <div x-show="open" x-cloak x-transition class="mt-4">
                    <form method="POST" action="{{ route('admin.connectivity.mqtt.topics.store', $connection) }}" class="space-y-3">
                        @csrf
                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                    Topic pattern <span class="text-gray-400">(supports + and # wildcards)</span>
                                </label>
                                <input type="text" name="topic_pattern" placeholder="factory/line1/+/status" required
                                       class="w-full px-3 py-2 text-sm font-mono border border-gray-300 dark:border-gray-600 rounded-lg
                                              bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Payload format</label>
                                <select name="payload_format"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg
                                               bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="json">JSON</option>
                                    <option value="plain">Plain text</option>
                                    <option value="csv">CSV</option>
                                    <option value="hex">Hex</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Description (optional)</label>
                            <input type="text" name="description" placeholder="e.g. Production count from Line 1"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg
                                          bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div class="flex gap-2">
                            <button type="submit"
                                    class="px-4 py-1.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors">
                                Add Topic
                            </button>
                            <button type="button" @click="open = false"
                                    class="px-4 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm rounded-lg hover:bg-gray-200 transition-colors">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Live Message Log --}}
    <div>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Live Message Log</h2>

        <div class="bg-gray-900 dark:bg-gray-950 rounded-xl border border-gray-700 overflow-hidden"
             x-data="mqttLiveLog('{{ route('admin.connectivity.mqtt.messages', $connection) }}')"
             x-init="init()">

            {{-- Log toolbar --}}
            <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-700 bg-gray-800/60">
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                        <span class="text-xs text-gray-400">Live (polling)</span>
                    </div>
                    <span class="text-xs text-gray-500" x-text="messages.length + ' new messages'"></span>
                </div>
                <div class="flex items-center gap-2">
                    <label class="flex items-center gap-1.5 text-xs text-gray-400 cursor-pointer">
                        <input type="checkbox" x-model="autoScroll" class="rounded border-gray-600 text-blue-500">
                        Auto-scroll
                    </label>
                    <button @click="messages = []" class="text-xs text-gray-500 hover:text-gray-300 transition-colors">
                        Clear
                    </button>
                </div>
            </div>

            {{-- Log entries --}}
            <div class="h-96 overflow-y-auto font-mono text-xs p-4 space-y-2"
                 id="mqtt-log-{{ $connection->id }}"
                 x-ref="logContainer">

                {{-- Initial messages (server-side) --}}
                @foreach($recentMessages->reverse() as $msg)
                    <div class="flex gap-3 items-start opacity-60">
                        <span class="text-gray-500 shrink-0 tabular-nums">{{ $msg->received_at?->format('H:i:s') }}</span>
                        <span class="w-1.5 h-1.5 rounded-full mt-1 shrink-0
                              {{ $msg->processing_status === 'ok' ? 'bg-green-500' : ($msg->processing_status === 'error' ? 'bg-red-500' : 'bg-yellow-500') }}"></span>
                        <span class="text-blue-300 shrink-0 max-w-xs truncate">{{ $msg->topic }}</span>
                        <span class="text-gray-300 break-all">{{ Str::limit($msg->raw_payload, 200) }}</span>
                    </div>
                @endforeach

                {{-- Live messages (Alpine) --}}
                <template x-for="msg in messages" :key="msg.id">
                    <div class="flex gap-3 items-start">
                        <span class="text-gray-500 shrink-0 tabular-nums" x-text="formatTime(msg.received_at)"></span>
                        <span class="w-1.5 h-1.5 rounded-full mt-1 shrink-0"
                              :class="{
                                  'bg-green-500': msg.processing_status === 'ok',
                                  'bg-red-500':   msg.processing_status === 'error',
                                  'bg-yellow-500': msg.processing_status === 'skipped'
                              }"></span>
                        <span class="text-blue-300 shrink-0 max-w-xs truncate" x-text="msg.topic"></span>
                        <span class="text-gray-300 break-all" x-text="msg.raw_payload?.substring(0, 200)"></span>
                        <template x-if="msg.processing_error">
                            <span class="text-red-400 ml-1" x-text="'⚠ ' + msg.processing_error"></span>
                        </template>
                    </div>
                </template>

                <div x-show="messages.length === 0" class="text-gray-600 text-center py-8">
                    Waiting for messages...
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function mqttLiveLog(messagesUrl) {
    return {
        messages: [],
        autoScroll: true,
        lastId: {{ $recentMessages->max('id') ?? 0 }},
        pollingInterval: null,

        init() {
            this.pollingInterval = setInterval(() => this.poll(), 3000);
        },

        async poll() {
            try {
                const res = await fetch(messagesUrl + '?after_id=' + this.lastId);
                if (!res.ok) return;
                const data = await res.json();
                data.reverse().forEach(msg => this.addMessage(msg));
            } catch (e) {}
        },

        addMessage(msg) {
            if (msg.id <= this.lastId && this.lastId > 0) return;
            this.lastId = Math.max(this.lastId, msg.id ?? 0);
            this.messages.push(msg);
            if (this.messages.length > 500) this.messages.shift();
            if (this.autoScroll) {
                this.$nextTick(() => {
                    const c = this.$refs.logContainer;
                    if (c) c.scrollTop = c.scrollHeight;
                });
            }
        },

        formatTime(iso) {
            if (!iso) return '';
            try {
                return new Date(iso).toLocaleTimeString('pl-PL', { hour12: false });
            } catch { return iso; }
        },

        destroy() {
            if (this.pollingInterval) clearInterval(this.pollingInterval);
        }
    };
}
</script>
@endpush
@endsection
