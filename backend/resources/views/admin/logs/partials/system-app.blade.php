{{-- Application log tab: filters + entries list --}}

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

@php
    $levelStyles = [
        'debug'     => 'bg-gray-100 text-gray-600',
        'info'      => 'bg-blue-100 text-blue-700',
        'notice'    => 'bg-blue-100 text-blue-700',
        'warning'   => 'bg-amber-100 text-amber-800',
        'error'     => 'bg-red-100 text-red-700',
        'critical'  => 'bg-red-200 text-red-900 font-bold',
        'alert'     => 'bg-red-200 text-red-900 font-bold',
        'emergency' => 'bg-red-300 text-red-900 font-bold',
    ];
@endphp

<div class="card overflow-hidden">
    @forelse($entries as $entry)
        <details class="border-b last:border-b-0">
            <summary class="px-4 py-3 hover:bg-gray-50 cursor-pointer flex items-start gap-3">
                <span class="font-mono text-xs text-gray-500 whitespace-nowrap mt-0.5">
                    {{ $entry->timestamp }}
                </span>
                <span class="inline-block px-2 py-0.5 rounded text-xs uppercase whitespace-nowrap {{ $levelStyles[$entry->level] ?? 'bg-gray-100 text-gray-600' }}">
                    {{ $entry->level }}
                </span>
                <span class="text-xs text-gray-400 whitespace-nowrap">{{ $entry->environment }}</span>
                <span class="text-sm text-gray-800 break-all flex-1">
                    {{ \Illuminate\Support\Str::limit($entry->message, 300) }}
                </span>
            </summary>
            @if(trim($entry->context) !== '' || strlen($entry->message) > 300)
                <pre class="bg-gray-50 text-xs text-gray-700 px-4 py-3 overflow-x-auto whitespace-pre-wrap break-words border-t">{{ $entry->message }}@if($entry->context)

{{ rtrim($entry->context) }}@endif</pre>
            @endif
        </details>
    @empty
        <div class="px-4 py-16 text-center text-gray-400">
            {{ __('No log entries match your filters.') }}
        </div>
    @endforelse
</div>

@if($entries->isNotEmpty())
    <p class="text-xs text-gray-400 mt-2">
        {{ __('Showing :count entries (most recent first). Older entries beyond the 2 MB tail window are not displayed.', ['count' => $entries->count()]) }}
    </p>
@endif
