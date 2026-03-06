<div class="overflow-hidden rounded-lg border border-gray-200 bg-white mb-1">
    <table class="min-w-full divide-y divide-gray-100">
        <thead>
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Order</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Product</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 hidden sm:table-cell">Due</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 hidden md:table-cell">Qty</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 hidden lg:table-cell">Priority</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($orders as $wo)
            @php
                $isOverdue = $wo->due_date && $wo->due_date->isPast();
            @endphp
            <tr class="hover:bg-gray-50 cursor-pointer {{ $isOverdue ? 'bg-red-50' : '' }}"
                onclick="window.location='{{ route('admin.work-orders.show', $wo) }}'">
                <td class="px-4 py-3">
                    <span class="inline-flex items-center font-mono text-sm font-semibold text-blue-700
                                 bg-blue-50 border border-blue-200 rounded px-2 py-0.5">
                        {{ $wo->order_no }}
                    </span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ $wo->productType?->name ?? '—' }}</td>
                <td class="px-4 py-3 text-sm hidden sm:table-cell">
                    @if($wo->due_date)
                        <span class="{{ $isOverdue ? 'text-red-600 font-semibold' : 'text-gray-600' }}">
                            {{ $wo->due_date->format('d M') }}
                            @if($isOverdue) ⚠ @endif
                        </span>
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-sm text-gray-600 hidden md:table-cell">{{ number_format($wo->planned_qty) }}</td>
                <td class="px-4 py-3">
                    @php
                        $colors = [
                            'BLOCKED'     => 'bg-red-100 text-red-700',
                            'IN_PROGRESS' => 'bg-blue-100 text-blue-700',
                            'ACCEPTED'    => 'bg-green-100 text-green-700',
                            'PENDING'     => 'bg-gray-100 text-gray-600',
                            'PAUSED'      => 'bg-yellow-100 text-yellow-700',
                        ];
                    @endphp
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $colors[$wo->status] ?? 'bg-gray-100 text-gray-600' }}">
                        {{ $wo->status }}
                    </span>
                </td>
                <td class="px-4 py-3 hidden lg:table-cell">
                    @if($wo->priority)
                        <div class="flex items-center gap-1.5">
                            <div class="h-1.5 rounded-full bg-blue-500" style="width: {{ min($wo->priority, 100) }}px; max-width: 80px"></div>
                            <span class="text-xs text-gray-500">{{ $wo->priority }}</span>
                        </div>
                    @else
                        <span class="text-gray-400 text-xs">—</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
