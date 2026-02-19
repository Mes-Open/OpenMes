@props(['items' => []])

@if(count($items))
<nav class="mb-5 flex items-center flex-wrap gap-1 text-sm">
    @foreach($items as $item)
        @if(!$loop->last)
            <a href="{{ $item['url'] }}" class="text-gray-500 hover:text-blue-600 transition-colors">{{ $item['label'] }}</a>
            <svg class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
            </svg>
        @else
            <span class="text-gray-800 font-medium">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
@endif
