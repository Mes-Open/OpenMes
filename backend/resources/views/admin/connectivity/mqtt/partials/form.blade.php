@php
    /** @var \App\Models\MachineConnection $connection */
    /** @var \App\Models\MqttConnection|null $mqtt */
    $mqtt ??= null;
@endphp

{{-- General --}}
<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 space-y-4">
    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">General</h2>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name <span class="text-red-500">*</span></label>
        <input type="text" name="name" value="{{ old('name', $connection->name ?? '') }}" required
               class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700
                      text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
        <textarea name="description" rows="2"
                  class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700
                         text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">{{ old('description', $connection->description ?? '') }}</textarea>
    </div>

    <div class="flex items-center gap-3">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" id="is_active" value="1"
               {{ old('is_active', $connection->is_active ?? false) ? 'checked' : '' }}
               class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
        <label for="is_active" class="text-sm font-medium text-gray-700 dark:text-gray-300">
            Active (start listening on daemon start)
        </label>
    </div>
</div>

{{-- Broker --}}
<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 space-y-4">
    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Broker</h2>

    <div class="grid grid-cols-3 gap-4">
        <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Host <span class="text-red-500">*</span></label>
            <input type="text" name="broker_host" value="{{ old('broker_host', $mqtt?->broker_host ?? '') }}"
                   placeholder="broker.example.com" required
                   class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700
                          text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Port <span class="text-red-500">*</span></label>
            <input type="number" name="broker_port" value="{{ old('broker_port', $mqtt?->broker_port ?? 1883) }}"
                   min="1" max="65535" required
                   class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700
                          text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono">
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Client ID</label>
        <input type="text" name="client_id" value="{{ old('client_id', $mqtt?->client_id ?? '') }}"
               placeholder="Auto-generated if empty"
               class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700
                      text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono">
    </div>
</div>

{{-- Authentication --}}
<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 space-y-4">
    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Authentication</h2>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Username</label>
            <input type="text" name="username" value="{{ old('username', $mqtt?->username ?? '') }}"
                   autocomplete="off"
                   class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700
                          text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Password
                @if($mqtt?->password_encrypted)
                    <span class="text-xs text-gray-400 dark:text-gray-500 font-normal">(leave blank to keep current)</span>
                @endif
            </label>
            <input type="password" name="password" value=""
                   autocomplete="new-password"
                   class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700
                          text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
    </div>
</div>

{{-- TLS --}}
<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 space-y-4"
     x-data="{ tls: {{ old('use_tls', $mqtt?->use_tls ?? false) ? 'true' : 'false' }} }">
    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">TLS / Security</h2>

    <div class="flex items-center gap-3">
        <input type="hidden" name="use_tls" value="0">
        <input type="checkbox" name="use_tls" id="use_tls" value="1"
               x-model="tls"
               {{ old('use_tls', $mqtt?->use_tls ?? false) ? 'checked' : '' }}
               class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
        <label for="use_tls" class="text-sm font-medium text-gray-700 dark:text-gray-300">Enable TLS (port 8883)</label>
    </div>

    <div x-show="tls" x-cloak>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">CA Certificate (PEM)</label>
        <textarea name="ca_cert" rows="4" placeholder="-----BEGIN CERTIFICATE-----"
                  class="w-full px-3 py-2 text-xs font-mono border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700
                         text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">{{ old('ca_cert', $mqtt?->ca_cert ?? '') }}</textarea>
    </div>
</div>

{{-- Advanced --}}
<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 space-y-4">
    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Advanced</h2>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">QoS default</label>
            <select name="qos_default"
                    class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700
                           text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                @foreach([0 => 'QoS 0 — At most once', 1 => 'QoS 1 — At least once', 2 => 'QoS 2 — Exactly once'] as $val => $label)
                    <option value="{{ $val }}" {{ old('qos_default', $mqtt?->qos_default ?? 0) == $val ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Keep-alive (seconds)</label>
            <input type="number" name="keep_alive_seconds" value="{{ old('keep_alive_seconds', $mqtt?->keep_alive_seconds ?? 60) }}"
                   min="5" max="3600"
                   class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700
                          text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Connect timeout (seconds)</label>
            <input type="number" name="connect_timeout" value="{{ old('connect_timeout', $mqtt?->connect_timeout ?? 10) }}"
                   min="1" max="120"
                   class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700
                          text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reconnect delay (seconds)</label>
            <input type="number" name="reconnect_delay_seconds" value="{{ old('reconnect_delay_seconds', $mqtt?->reconnect_delay_seconds ?? 5) }}"
                   min="1" max="300"
                   class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700
                          text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
    </div>

    <div class="flex items-center gap-3">
        <input type="hidden" name="clean_session" value="0">
        <input type="checkbox" name="clean_session" id="clean_session" value="1"
               {{ old('clean_session', $mqtt?->clean_session ?? true) ? 'checked' : '' }}
               class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
        <label for="clean_session" class="text-sm font-medium text-gray-700 dark:text-gray-300">
            Clean session (recommended for stateless connections)
        </label>
    </div>
</div>
