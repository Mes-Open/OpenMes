<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('OEE Report') }} {{ $dateFrom }} — {{ $dateTo }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4; margin: 12mm 10mm; }

        @media print {
            .no-print { display: none !important; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .page-break { page-break-after: always; }
            .avoid-break { page-break-inside: avoid; }
        }

        body { font-family: ui-sans-serif, system-ui, -apple-system, sans-serif; color: #1f2937; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-yellow { background: #fef9c3; color: #854d0e; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-gray { background: #f3f4f6; color: #4b5563; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .badge-amber { background: #fef3c7; color: #92400e; }
        table { border-collapse: collapse; width: 100%; font-size: 11px; }
        th, td { border: 1px solid #e5e7eb; padding: 4px 6px; text-align: right; }
        th { background: #f9fafb; text-transform: uppercase; font-size: 10px; letter-spacing: 0.05em; }
        th:first-child, td:first-child { text-align: left; }
        h1 { font-size: 22px; margin: 0; }
        h2 { font-size: 16px; margin: 24px 0 8px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
        h3 { font-size: 13px; margin: 12px 0 6px; color: #4b5563; }
        .meta { color: #6b7280; font-size: 11px; }
        .pareto-bar { height: 14px; background: #e5e7eb; border-radius: 4px; overflow: hidden; }
        .pareto-fill { height: 100%; border-radius: 4px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px; }
        .grid-4 { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 6px; }
        .stat { border: 1px solid #e5e7eb; border-radius: 6px; padding: 6px 8px; }
        .stat-label { font-size: 9px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.05em; }
        .stat-value { font-size: 16px; font-weight: 700; color: #111827; margin-top: 2px; }
    </style>
</head>
<body class="bg-white">

<div class="no-print" style="position: sticky; top: 0; background: #1f2937; color: white; padding: 8px 16px; display: flex; gap: 12px; align-items: center; z-index: 50;">
    <strong>{{ __('Print Preview') }}</strong>
    <button onclick="window.print()" style="background: #2563eb; color: white; padding: 6px 14px; border: 0; border-radius: 4px; cursor: pointer; font-weight: 600;">
        🖨 {{ __('Print / Save as PDF') }}
    </button>
    <a href="{{ route('admin.oee.index') }}" style="color: #d1d5db; text-decoration: underline; margin-left: auto;">← {{ __('Back to OEE') }}</a>
</div>

<div style="max-width: 210mm; margin: 0 auto; padding: 16px 8px;">

    {{-- Report header --}}
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid #1f2937;">
        <div>
            <h1>{{ __('OEE Report') }}</h1>
            <p class="meta">
                {{ $singleLine && $perLine->count() === 1 ? $perLine->first()['line']->name : __('All active lines') }}
                — {{ \Carbon\Carbon::parse($dateFrom)->format('Y-m-d') }} {{ __('to') }} {{ \Carbon\Carbon::parse($dateTo)->format('Y-m-d') }}
            </p>
            <p class="meta">{{ __('Overall Equipment Effectiveness — Availability × Performance × Quality') }}</p>
        </div>
        <div style="text-align: right;" class="meta">
            <div><strong>{{ __('Generated') }}:</strong> {{ $generatedAt->format('Y-m-d H:i') }}</div>
            <div>{{ config('app.name') }}</div>
        </div>
    </div>

    @forelse($perLine as $idx => $entry)
        @php
            $line = $entry['line'];
            $records = $entry['records'];
            $downtimeByReason = $entry['downtimeByReason'];
            $s = $entry['summary'];
            $color = \App\Support\OeeBand::colorFor($s['avg_oee'] !== null ? (float) $s['avg_oee'] : null);
            $badgeClass = 'badge-' . $color;
        @endphp

        <section class="{{ $idx > 0 ? 'page-break' : '' }}">
            <h2>{{ $line->name }} <small class="meta">({{ $line->code }})</small></h2>

            {{-- Summary stats --}}
            <div class="avoid-break" style="display: flex; gap: 12px; align-items: center; margin-bottom: 12px;">
                <x-oee-gauge :value="$s['avg_oee']" :size="160" label="AVG OEE" />
                <div style="flex: 1;" class="grid-4">
                    <div class="stat">
                        <div class="stat-label">{{ __('Availability') }}</div>
                        <div class="stat-value">{{ $s['avg_availability'] !== null ? number_format($s['avg_availability'], 1) . '%' : '—' }}</div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">{{ __('Performance') }}</div>
                        <div class="stat-value">{{ $s['avg_performance'] !== null ? number_format($s['avg_performance'], 1) . '%' : '—' }}</div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">{{ __('Quality') }}</div>
                        <div class="stat-value">{{ $s['avg_quality'] !== null ? number_format($s['avg_quality'], 1) . '%' : '—' }}</div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">{{ __('Days analyzed') }}</div>
                        <div class="stat-value">{{ $s['days'] }}</div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">{{ __('Produced') }}</div>
                        <div class="stat-value">{{ number_format($s['total_produced']) }}</div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">{{ __('Scrap') }}</div>
                        <div class="stat-value">{{ number_format($s['total_scrap']) }}</div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">{{ __('Downtime') }}</div>
                        <div class="stat-value">{{ $s['total_downtime'] }} min</div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">{{ __('Rating') }}</div>
                        <div class="stat-value">
                            <span class="badge {{ $badgeClass }}">
                                @if($color === 'green') {{ __('World-class') }}
                                @elseif($color === 'yellow') {{ __('Typical') }}
                                @elseif($color === 'red') {{ __('Needs improvement') }}
                                @else {{ __('No data') }}
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Pareto downtimes --}}
            @if(!empty($downtimeByReason))
                @php $maxMinutes = collect($downtimeByReason)->max('total_minutes') ?: 1; @endphp
                <h3>{{ __('Downtime by Reason') }} ({{ __('Pareto') }})</h3>
                <div class="avoid-break" style="display: flex; flex-direction: column; gap: 3px; margin-bottom: 12px;">
                    @foreach($downtimeByReason as $item)
                        @php
                            $fillColor = match($item['kind_color']) {
                                'blue' => '#60a5fa',
                                'amber' => '#fbbf24',
                                default => '#f87171',
                            };
                        @endphp
                        <div style="display: flex; gap: 8px; align-items: center; font-size: 11px;">
                            <span style="width: 140px;">{{ $item['reason'] }} <span class="badge badge-{{ $item['kind_color'] }}">{{ $item['kind_label'] }}</span></span>
                            <div class="pareto-bar" style="flex: 1;">
                                <div class="pareto-fill" style="width: {{ ($item['total_minutes'] / $maxMinutes) * 100 }}%; background: {{ $fillColor }};"></div>
                            </div>
                            <span style="width: 80px; text-align: right; font-family: monospace;">{{ $item['total_minutes'] }} min ({{ $item['count'] }}×)</span>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Daily records table --}}
            @if($records->isNotEmpty())
                <h3>{{ __('Daily Records') }}</h3>
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Shift') }}</th>
                            <th>{{ __('Planned') }}</th>
                            <th>{{ __('Operating') }}</th>
                            <th>{{ __('Downtime') }}</th>
                            <th>A%</th>
                            <th>P%</th>
                            <th>Q%</th>
                            <th>OEE%</th>
                            <th>{{ __('Produced') }}</th>
                            <th>{{ __('Scrap') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($records as $r)
                            @php $oeeColor = \App\Support\OeeBand::colorFor($r->oee_pct !== null ? (float) $r->oee_pct : null); @endphp
                            <tr>
                                <td>{{ $r->record_date->format('Y-m-d') }}</td>
                                <td>{{ $r->shift?->name ?? __('All') }}</td>
                                <td>{{ $r->planned_minutes }}</td>
                                <td>{{ $r->operating_minutes }}</td>
                                <td>{{ $r->downtime_minutes }}</td>
                                <td>{{ $r->availability_pct !== null ? number_format($r->availability_pct, 1) : '—' }}</td>
                                <td>{{ $r->performance_pct !== null ? number_format($r->performance_pct, 1) : '—' }}</td>
                                <td>{{ $r->quality_pct !== null ? number_format($r->quality_pct, 1) : '—' }}</td>
                                <td><span class="badge badge-{{ $oeeColor }}">{{ $r->oee_pct !== null ? number_format($r->oee_pct, 1) : '—' }}</span></td>
                                <td>{{ number_format($r->total_produced) }}</td>
                                <td>{{ $r->scrap_qty > 0 ? number_format($r->scrap_qty) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="meta">{{ __('No records for this period.') }}</p>
            @endif
        </section>
    @empty
        <p>{{ __('No active lines.') }}</p>
    @endforelse

    <div style="margin-top: 24px; padding-top: 12px; border-top: 1px solid #e5e7eb;" class="meta">
        <p>{{ __('OEE thresholds') }}: <span class="badge badge-green">≥ 85% {{ __('World-class') }}</span> <span class="badge badge-yellow">65–84% {{ __('Typical') }}</span> <span class="badge badge-red">&lt; 65% {{ __('Needs improvement') }}</span></p>
        <p>{{ __('Downtime kinds') }}: <span class="badge badge-blue">{{ __('Planned') }}</span> ({{ __('subtracted from planned time') }}) · <span class="badge badge-red">{{ __('Unplanned') }}</span> & <span class="badge badge-amber">{{ __('Changeover') }}</span> ({{ __('counted as availability loss') }})</p>
    </div>
</div>

</body>
</html>
