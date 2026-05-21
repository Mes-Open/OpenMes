{{-- Failed jobs tab --}}

@if($missing)
    <div class="card border border-amber-200 bg-amber-50 text-amber-900">
        <p class="font-medium">{{ __('Failed jobs table is missing.') }}</p>
        <p class="text-sm mt-1">{{ __('Run the Laravel queue migrations to enable the failed_jobs table.') }}</p>
    </div>
@else
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="text-left px-4 py-2">{{ __('ID') }}</th>
                        <th class="text-left px-4 py-2">{{ __('Connection') }}</th>
                        <th class="text-left px-4 py-2">{{ __('Queue') }}</th>
                        <th class="text-left px-4 py-2">{{ __('Payload') }}</th>
                        <th class="text-left px-4 py-2">{{ __('Exception') }}</th>
                        <th class="text-left px-4 py-2">{{ __('Failed at') }}</th>
                        <th class="text-left px-4 py-2">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($entries as $job)
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-4 py-3 text-xs font-mono text-gray-500 whitespace-nowrap">
                                {{ $job->id }}
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-700 whitespace-nowrap">
                                {{ $job->connection }}
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-700 whitespace-nowrap">
                                {{ $job->queue }}
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-600">
                                <details>
                                    <summary class="cursor-pointer text-blue-600 hover:underline">
                                        {{ __('View payload') }}
                                    </summary>
                                    <pre class="mt-2 bg-gray-50 p-2 rounded max-w-xl overflow-x-auto whitespace-pre-wrap break-words">{{ \Illuminate\Support\Str::limit($job->payload, 4000) }}</pre>
                                </details>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-700 max-w-md">
                                <div class="text-red-700 break-words">
                                    {{ \Illuminate\Support\Str::limit(strtok($job->exception, "\n"), 200) }}
                                </div>
                                <details class="mt-1">
                                    <summary class="cursor-pointer text-blue-600 hover:underline text-xs">
                                        {{ __('Show stack trace') }}
                                    </summary>
                                    <pre class="mt-2 bg-gray-50 p-2 rounded max-w-xl overflow-x-auto whitespace-pre-wrap break-words text-gray-700">{{ \Illuminate\Support\Str::limit($job->exception, 8000) }}</pre>
                                </details>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap font-mono">
                                {{ $job->failed_at }}
                            </td>
                            <td class="px-4 py-3 text-xs whitespace-nowrap">
                                <button type="button"
                                        disabled
                                        title="{{ __('Retry is not implemented yet — run `php artisan queue:retry :id` on the host.', ['id' => $job->id]) }}"
                                        class="px-3 py-1 text-xs rounded bg-gray-100 text-gray-400 cursor-not-allowed">
                                    {{ __('Retry') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center text-gray-400">
                                {{ __('No failed jobs.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($entries, 'links') && $entries->hasPages())
            <div class="p-3 border-t">{{ $entries->links() }}</div>
        @endif
    </div>
@endif
