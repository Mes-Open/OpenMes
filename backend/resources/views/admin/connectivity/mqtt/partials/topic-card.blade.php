@php
    /** @var \App\Models\MachineTopic $topic */
    /** @var \App\Models\MachineConnection $connection */
    $actionLabels = \App\Models\TopicMapping::ACTION_LABELS;
@endphp

<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden"
     x-data="{ editTopic: false, addMapping: false }">

    {{-- Topic header --}}
    <div class="flex items-center gap-3 px-4 py-3 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
        <span class="w-2 h-2 rounded-full shrink-0 {{ $topic->is_active ? 'bg-green-500' : 'bg-slate-400' }}"></span>
        <span class="font-mono text-sm font-medium text-gray-900 dark:text-white flex-1">{{ $topic->topic_pattern }}</span>
        <span class="text-xs px-2 py-0.5 bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-300 rounded-full uppercase">
            {{ $topic->payload_format }}
        </span>
        @if($topic->description)
            <span class="text-xs text-gray-400 dark:text-gray-500 max-w-xs truncate">{{ $topic->description }}</span>
        @endif
        <div class="flex items-center gap-1 shrink-0">
            <button @click="editTopic = !editTopic" type="button"
                    class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-md hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </button>
            <form method="POST" action="{{ route('admin.connectivity.mqtt.topics.destroy', [$connection, $topic]) }}"
                  onsubmit="return confirm('{{ __('Delete this topic and all its mappings?') }}')">
                @csrf @method('DELETE')
                <button type="submit"
                        class="p-1.5 text-gray-400 hover:text-red-500 rounded-md hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>

    {{-- Edit topic form --}}
    <div x-show="editTopic" x-cloak class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 bg-blue-50/40 dark:bg-blue-900/10">
        <form method="POST" action="{{ route('admin.connectivity.mqtt.topics.update', [$connection, $topic]) }}" class="flex gap-3 items-end flex-wrap">
            @csrf @method('PUT')
            <div class="flex-1 min-w-48">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Pattern') }}</label>
                <input type="text" name="topic_pattern" value="{{ $topic->topic_pattern }}" required
                       class="w-full px-2 py-1.5 text-sm font-mono border border-gray-300 dark:border-gray-600 rounded-lg
                              bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Format') }}</label>
                <select name="payload_format"
                        class="px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg
                               bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    @foreach(['json', 'plain', 'csv', 'hex'] as $fmt)
                        <option value="{{ $fmt }}" {{ $topic->payload_format === $fmt ? 'selected' : '' }}>{{ strtoupper($fmt) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-36">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Description') }}</label>
                <input type="text" name="description" value="{{ $topic->description }}"
                       class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg
                              bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="px-3 py-1.5 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700 transition-colors">{{ __('Save') }}</button>
                <button type="button" @click="editTopic = false" class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs rounded-lg hover:bg-gray-200 transition-colors">{{ __('Cancel') }}</button>
            </div>
        </form>
    </div>

    {{-- Mappings list --}}
    <div class="divide-y divide-gray-100 dark:divide-gray-700">
        @forelse($topic->mappings as $mapping)
            @include('admin.connectivity.mqtt.partials.mapping-row', ['mapping' => $mapping, 'topic' => $topic, 'connection' => $connection])
        @empty
            <p class="px-4 py-3 text-xs text-gray-400 dark:text-gray-500 italic">{{ __('No mappings defined — messages will be logged only.') }}</p>
        @endforelse
    </div>

    {{-- Add mapping form --}}
    <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700">
        <button @click="addMapping = !addMapping" type="button"
                class="flex items-center gap-1.5 text-xs font-medium text-blue-600 dark:text-blue-400 hover:text-blue-700">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            {{ __('Add mapping rule') }}
        </button>

        <div x-show="addMapping" x-cloak x-transition class="mt-3">
            <form method="POST" action="{{ route('admin.connectivity.mqtt.topics.mappings.store', [$connection, $topic]) }}" class="space-y-3">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                            {{ __('Field path') }}
                            <span class="text-gray-400 font-normal">(e.g. $.qty or $.data.value)</span>
                        </label>
                        <input type="text" name="field_path" placeholder="$.value"
                               class="w-full px-2 py-1.5 text-xs font-mono border border-gray-300 dark:border-gray-600 rounded-lg
                                      bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Action type') }} <span class="text-red-500">*</span></label>
                        <select name="action_type" required
                                class="w-full px-2 py-1.5 text-xs border border-gray-300 dark:border-gray-600 rounded-lg
                                       bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            @foreach($actionLabels as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                            {{ __('Condition') }}
                            <span class="text-gray-400 font-normal">(e.g. value > 0)</span>
                        </label>
                        <input type="text" name="condition_expr" placeholder="value > 0"
                               class="w-full px-2 py-1.5 text-xs font-mono border border-gray-300 dark:border-gray-600 rounded-lg
                                      bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Priority') }}</label>
                        <input type="number" name="priority" value="100" min="1" max="9999"
                               class="w-full px-2 py-1.5 text-xs border border-gray-300 dark:border-gray-600 rounded-lg
                                      bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                        {{ __('Action params (JSON)') }}
                        <span class="text-gray-400 font-normal">— e.g. {"order_no_path":"$.order_no","qty_path":"$.qty"}</span>
                    </label>
                    <textarea name="action_params" rows="3" placeholder='{"order_no_path": "$.order_no", "qty_path": "$.qty"}'
                              class="w-full px-2 py-1.5 text-xs font-mono border border-gray-300 dark:border-gray-600 rounded-lg
                                     bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Description') }}</label>
                    <input type="text" name="description" placeholder="e.g. Update produced qty from machine counter"
                           class="w-full px-2 py-1.5 text-xs border border-gray-300 dark:border-gray-600 rounded-lg
                                  bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="px-3 py-1.5 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700 transition-colors">{{ __('Add Mapping') }}</button>
                    <button type="button" @click="addMapping = false" class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs rounded-lg hover:bg-gray-200 transition-colors">{{ __('Cancel') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
