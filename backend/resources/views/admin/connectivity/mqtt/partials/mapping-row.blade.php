@php
    /** @var \App\Models\TopicMapping $mapping */
    $actionLabels = \App\Models\TopicMapping::ACTION_LABELS;
    $actionColors = [
        'update_batch_step'     => 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300',
        'update_work_order_qty' => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
        'create_issue'          => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300',
        'update_line_status'    => 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300',
        'set_work_order_status' => 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300',
        'log_event'             => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300',
        'webhook_forward'       => 'bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300',
    ];
    $color = $actionColors[$mapping->action_type] ?? 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300';
@endphp

<div class="px-4 py-3 flex items-start gap-3 text-xs {{ !$mapping->is_active ? 'opacity-50' : '' }}"
     x-data="{ editMapping: false }">

    <span class="shrink-0 text-gray-400 dark:text-gray-500 tabular-nums mt-0.5">{{ str_pad($mapping->priority, 3, '0', STR_PAD_LEFT) }}</span>

    <div class="flex-1 min-w-0 space-y-1">
        <div class="flex items-center gap-2 flex-wrap">
            @if($mapping->field_path)
                <span class="font-mono text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded">
                    {{ $mapping->field_path }}
                </span>
                <span class="text-gray-400">→</span>
            @endif
            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $color }}">
                {{ $actionLabels[$mapping->action_type] ?? $mapping->action_type }}
            </span>
            @if($mapping->condition_expr)
                <span class="font-mono text-gray-400 dark:text-gray-500 text-xs">
                    if: {{ $mapping->condition_expr }}
                </span>
            @endif
        </div>
        @if($mapping->description)
            <p class="text-gray-400 dark:text-gray-500">{{ $mapping->description }}</p>
        @endif
        @if($mapping->action_params)
            <p class="font-mono text-gray-400 dark:text-gray-500 break-all">
                {{ Str::limit(json_encode($mapping->action_params), 120) }}
            </p>
        @endif

        {{-- Edit form --}}
        <div x-show="editMapping" x-cloak x-transition class="mt-2 pt-2 border-t border-gray-100 dark:border-gray-700">
            <form method="POST" action="{{ route('admin.connectivity.mqtt.topics.mappings.update', [$connection, $topic, $mapping]) }}" class="space-y-2">
                @csrf @method('PUT')
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">{{ __('Field path') }}</label>
                        <input type="text" name="field_path" value="{{ $mapping->field_path }}"
                               class="w-full px-2 py-1 text-xs font-mono border border-gray-300 dark:border-gray-600 rounded
                                      bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">{{ __('Action type') }}</label>
                        <select name="action_type"
                                class="w-full px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded
                                       bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-blue-500">
                            @foreach($actionLabels as $val => $label)
                                <option value="{{ $val }}" {{ $mapping->action_type === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">{{ __('Condition') }}</label>
                        <input type="text" name="condition_expr" value="{{ $mapping->condition_expr }}"
                               class="w-full px-2 py-1 text-xs font-mono border border-gray-300 dark:border-gray-600 rounded
                                      bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">{{ __('Priority') }}</label>
                        <input type="number" name="priority" value="{{ $mapping->priority }}" min="1" max="9999"
                               class="w-full px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded
                                      bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-blue-500">
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-0.5">{{ __('Action params (JSON)') }}</label>
                    <textarea name="action_params" rows="2"
                              class="w-full px-2 py-1 text-xs font-mono border border-gray-300 dark:border-gray-600 rounded
                                     bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-blue-500">{{ $mapping->action_params ? json_encode($mapping->action_params, JSON_PRETTY_PRINT) : '' }}</textarea>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-0.5">{{ __('Description') }}</label>
                    <input type="text" name="description" value="{{ $mapping->description }}"
                           class="w-full px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded
                                  bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-blue-500">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="px-2 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition-colors">{{ __('Save') }}</button>
                    <button type="button" @click="editMapping = false" class="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs rounded hover:bg-gray-200 transition-colors">{{ __('Cancel') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="flex items-center gap-1 shrink-0">
        <button @click="editMapping = !editMapping" type="button"
                class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
        </button>
        <form method="POST" action="{{ route('admin.connectivity.mqtt.topics.mappings.destroy', [$connection, $topic, $mapping]) }}"
              onsubmit="return confirm('{{ __('Delete this mapping?') }}')">
            @csrf @method('DELETE')
            <button type="submit" class="p-1 text-gray-400 hover:text-red-500 rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </form>
    </div>
</div>
