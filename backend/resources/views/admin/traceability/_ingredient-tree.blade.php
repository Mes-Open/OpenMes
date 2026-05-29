{{-- Recursive backward genealogy node. Expects $node = backwardTraceLot() output. --}}
@if(!empty($node['ingredients']))
    <ul class="ml-4 border-l border-gray-200 pl-4 space-y-2">
        @foreach($node['ingredients'] as $child)
            <li>
                <div class="flex items-center gap-2 flex-wrap text-sm">
                    <span class="font-mono font-medium text-gray-800">{{ $child['lot']['lot_number'] ?? '—' }}</span>
                    <span class="text-gray-500">{{ $child['material']['name'] ?? '' }}</span>
                    @if(!empty($child['supplier_lot_no']))
                        <span class="text-xs text-gray-400">{{ __('Supplier LOT') }}: <span class="font-mono">{{ $child['supplier_lot_no'] }}</span></span>
                    @endif
                    @if(!empty($child['source_batch_id']))
                        <span class="text-xs px-2 py-0.5 rounded bg-purple-50 text-purple-700">{{ __('semi-finished') }}</span>
                    @endif
                </div>
                @if(!empty($child['truncated']))
                    <p class="text-xs text-amber-600 ml-1">{{ __('Trace truncated (max depth reached).') }}</p>
                @else
                    @include('admin.traceability._ingredient-tree', ['node' => $child])
                @endif
            </li>
        @endforeach
    </ul>
@endif
