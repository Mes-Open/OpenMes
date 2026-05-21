{{-- Deployments tab --}}

@if($missing)
    <div class="card border border-blue-200 bg-blue-50 text-blue-900">
        <p class="font-medium">{{ __('Deployment audit log is not available on this build.') }}</p>
        <p class="text-sm mt-1">
            {{ __('Deployments log requires v0.12+ schema (system_updates table). This table was introduced by the updater hardening work and has not been merged into this branch yet.') }}
        </p>
        <p class="text-xs mt-2 text-blue-700">
            {{ __('Once the system_updates migration lands, this tab will surface start/end timestamps, the upgraded version, success/failure status, and any error output from each deployment.') }}
        </p>
    </div>
@else
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="text-left px-4 py-2">{{ __('Started') }}</th>
                        <th class="text-left px-4 py-2">{{ __('Finished') }}</th>
                        <th class="text-left px-4 py-2">{{ __('Version') }}</th>
                        <th class="text-left px-4 py-2">{{ __('Status') }}</th>
                        <th class="text-left px-4 py-2">{{ __('Triggered by') }}</th>
                        <th class="text-left px-4 py-2">{{ __('Output') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($entries as $row)
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-4 py-3 text-xs font-mono text-gray-600 whitespace-nowrap">
                                {{ $row->started_at ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs font-mono text-gray-600 whitespace-nowrap">
                                {{ $row->finished_at ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-800 whitespace-nowrap font-mono">
                                {{ $row->from_version ?? '—' }} → {{ $row->to_version ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs whitespace-nowrap">
                                @php
                                    $state = $row->state ?? 'unknown';
                                    $statusBadge = match($state) {
                                        'completed'    => 'bg-green-100 text-green-700',
                                        'failed'       => 'bg-red-100 text-red-700',
                                        'rolled_back'  => 'bg-red-100 text-red-700',
                                        'queued',
                                        'in_progress'  => 'bg-blue-100 text-blue-700',
                                        default        => 'bg-gray-100 text-gray-600',
                                    };
                                @endphp
                                <span class="inline-block px-2 py-0.5 rounded text-xs font-medium {{ $statusBadge }}">
                                    {{ ucfirst(str_replace('_', ' ', $state)) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-700 whitespace-nowrap">
                                @php
                                    $userId = $row->user_id ?? null;
                                    $triggeredBy = $userId ? \App\Models\User::find($userId)?->name : null;
                                @endphp
                                {{ $triggeredBy ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-600">
                                @if(! empty($row->error))
                                    <details>
                                        <summary class="cursor-pointer text-blue-600 hover:underline">
                                            {{ __('View output') }}
                                        </summary>
                                        <pre class="mt-2 bg-gray-50 p-2 rounded max-w-xl overflow-x-auto whitespace-pre-wrap break-words">{{ \Illuminate\Support\Str::limit($row->error, 8000) }}</pre>
                                    </details>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-16 text-center text-gray-400">
                                {{ __('No deployments recorded.') }}
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
