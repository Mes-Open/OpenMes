@props([
    'value' => null,
    'size' => 140,
    'showLabel' => true,
    'label' => 'OEE',
])

@php
    $hasValue = $value !== null;
    $pct = $hasValue ? max(0.0, min(100.0, (float) $value)) : 0.0;

    // Convert percentage to (x, y) on a unit semicircle centered at (50, 50), r=40.
    // p=0 → leftmost (10, 50), p=50 → top (50, 10), p=100 → rightmost (90, 50).
    $pointAt = function (float $p, float $r = 40.0) {
        $angle = $p / 100.0 * M_PI;
        $x = 50.0 - $r * cos($angle);
        $y = 50.0 - $r * sin($angle);
        return [round($x, 3), round($y, 3)];
    };

    [$rEndX, $rEndY] = $pointAt(\App\Support\OeeBand::RED_BELOW);
    [$yEndX, $yEndY] = $pointAt(\App\Support\OeeBand::GREEN_AT_LEAST);
    [$gEndX, $gEndY] = $pointAt(100.0);
    [$needleX, $needleY] = $pointAt($pct, 35.0);

    $textClass = \App\Support\OeeBand::textClass($hasValue ? $pct : null);
@endphp

<div {{ $attributes->merge(['class' => 'inline-flex flex-col items-center']) }}
     style="width: {{ $size }}px;">
    <svg viewBox="0 0 100 60" class="w-full h-auto" aria-hidden="true">
        {{-- Track (background) --}}
        <path d="M 10 50 A 40 40 0 0 1 90 50"
              fill="none" stroke="currentColor" stroke-width="10"
              class="text-gray-200 dark:text-slate-700" stroke-linecap="butt"/>

        {{-- Red zone: 0 → RED_BELOW --}}
        <path d="M 10 50 A 40 40 0 0 1 {{ $rEndX }} {{ $rEndY }}"
              fill="none" stroke="#ef4444" stroke-width="10" stroke-linecap="butt"/>

        {{-- Yellow zone: RED_BELOW → GREEN_AT_LEAST --}}
        <path d="M {{ $rEndX }} {{ $rEndY }} A 40 40 0 0 1 {{ $yEndX }} {{ $yEndY }}"
              fill="none" stroke="#eab308" stroke-width="10" stroke-linecap="butt"/>

        {{-- Green zone: GREEN_AT_LEAST → 100 --}}
        <path d="M {{ $yEndX }} {{ $yEndY }} A 40 40 0 0 1 {{ $gEndX }} {{ $gEndY }}"
              fill="none" stroke="#22c55e" stroke-width="10" stroke-linecap="butt"/>

        {{-- Threshold ticks --}}
        <line x1="{{ $rEndX }}" y1="{{ $rEndY }}" x2="{{ $pointAt(\App\Support\OeeBand::RED_BELOW, 46.0)[0] }}" y2="{{ $pointAt(\App\Support\OeeBand::RED_BELOW, 46.0)[1] }}"
              stroke="currentColor" stroke-width="0.5" class="text-gray-400"/>
        <line x1="{{ $yEndX }}" y1="{{ $yEndY }}" x2="{{ $pointAt(\App\Support\OeeBand::GREEN_AT_LEAST, 46.0)[0] }}" y2="{{ $pointAt(\App\Support\OeeBand::GREEN_AT_LEAST, 46.0)[1] }}"
              stroke="currentColor" stroke-width="0.5" class="text-gray-400"/>

        @if($hasValue)
            {{-- Needle --}}
            <line x1="50" y1="50" x2="{{ $needleX }}" y2="{{ $needleY }}"
                  stroke="currentColor" stroke-width="1.6" stroke-linecap="round"
                  class="text-gray-800 dark:text-gray-100"/>
            <circle cx="50" cy="50" r="2.2" fill="currentColor" class="text-gray-800 dark:text-gray-100"/>
        @else
            <circle cx="50" cy="50" r="2.2" fill="currentColor" class="text-gray-400"/>
        @endif
    </svg>

    @if($showLabel)
        <div class="-mt-2 text-center leading-tight">
            <div class="font-bold {{ $textClass }}" style="font-size: {{ $size * 0.18 }}px;">
                {{ $hasValue ? number_format($pct, 1) . '%' : 'N/A' }}
            </div>
            <div class="text-gray-500 dark:text-gray-400 uppercase tracking-wide" style="font-size: {{ $size * 0.075 }}px;">
                {{ $label }}
            </div>
        </div>
    @endif
</div>
