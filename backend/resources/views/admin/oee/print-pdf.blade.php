<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>OEE Report — {{ $dateFrom }} to {{ $dateTo }}</title>
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; margin: 0; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 14px; margin: 16px 0 6px; padding-bottom: 3px; border-bottom: 2px solid #2563eb; color: #1f2937; }
        h3 { font-size: 11px; margin: 10px 0 4px; color: #4b5563; }
        p { margin: 2px 0; }
        .meta { color: #6b7280; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { padding: 3px 5px; border: 1px solid #d1d5db; font-size: 9px; text-align: right; }
        th { background: #f3f4f6; font-weight: bold; text-transform: uppercase; letter-spacing: 0.04em; }
        th:first-child, td:first-child { text-align: left; }
        .stat-table { border: 0; }
        .stat-table td { border: 1px solid #e5e7eb; padding: 6px 8px; vertical-align: top; text-align: left; }
        .stat-label { font-size: 8px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
        .stat-value { font-size: 15px; font-weight: bold; color: #111827; margin-top: 1px; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-yellow { background: #fef9c3; color: #854d0e; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-gray { background: #f3f4f6; color: #4b5563; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .badge-amber { background: #fef3c7; color: #92400e; }
        .pareto-row td { border: 0; padding: 2px 4px; }
        .pareto-bar { background: #e5e7eb; height: 10px; width: 100%; position: relative; }
        .pareto-fill { height: 10px; }
        .page-break { page-break-after: always; }
        .avoid-break { page-break-inside: avoid; }
        .header { border-bottom: 2px solid #1f2937; padding-bottom: 8px; margin-bottom: 14px; }
        .footer-note { margin-top: 16px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 8px; color: #6b7280; }
    </style>
</head>
<body>

    <div class="header">
        <table style="border: 0;">
            <tr>
                <td style="border: 0; padding: 0; text-align: left; vertical-align: top;">
                    <h1>OEE Report</h1>
                    <p class="meta">
                        {{ $singleLine && $perLine->count() === 1 ? $perLine->first()['line']->name : 'All active lines' }}
                        — {{ $dateFrom }} to {{ $dateTo }}
                    </p>
                    <p class="meta">Overall Equipment Effectiveness = Availability × Performance × Quality</p>
                </td>
                <td style="border: 0; padding: 0; text-align: right; vertical-align: top;" class="meta">
                    <strong>Generated:</strong> {{ $generatedAt->format('Y-m-d H:i') }}<br>
                    {{ config('app.name') }}
                </td>
            </tr>
        </table>
    </div>

    @forelse($perLine as $idx => $entry)
        @php
            $line = $entry['line'];
            $records = $entry['records'];
            $downtimeByReason = $entry['downtimeByReason'];
            $s = $entry['summary'];
            $oeeColor = \App\Support\OeeBand::colorFor($s['avg_oee'] !== null ? (float) $s['avg_oee'] : null);

            $pointAt = function (float $p, float $r = 40.0) {
                $angle = $p / 100.0 * M_PI;
                return [round(50.0 - $r * cos($angle), 2), round(50.0 - $r * sin($angle), 2)];
            };
            [$rEndX, $rEndY] = $pointAt(\App\Support\OeeBand::RED_BELOW);
            [$yEndX, $yEndY] = $pointAt(\App\Support\OeeBand::GREEN_AT_LEAST);
            [$gEndX, $gEndY] = $pointAt(100.0);
            $hasValue = $s['avg_oee'] !== null;
            $needleVal = $hasValue ? max(0.0, min(100.0, (float) $s['avg_oee'])) : 0;
            [$nX, $nY] = $pointAt($needleVal, 35.0);
        @endphp

        <div class="{{ $idx > 0 ? 'page-break' : '' }}">
            <h2>{{ $line->name }} <span class="meta">({{ $line->code }})</span></h2>

            <div class="avoid-break">
                <table style="border: 0;">
                    <tr>
                        <td style="border: 0; width: 200px; padding: 0; vertical-align: top; text-align: center;">
                            <svg width="180" height="115" viewBox="0 0 100 65" xmlns="http://www.w3.org/2000/svg">
                                <path d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke="#e5e7eb" stroke-width="10"/>
                                <path d="M 10 50 A 40 40 0 0 1 {{ $rEndX }} {{ $rEndY }}" fill="none" stroke="#ef4444" stroke-width="10"/>
                                <path d="M {{ $rEndX }} {{ $rEndY }} A 40 40 0 0 1 {{ $yEndX }} {{ $yEndY }}" fill="none" stroke="#eab308" stroke-width="10"/>
                                <path d="M {{ $yEndX }} {{ $yEndY }} A 40 40 0 0 1 {{ $gEndX }} {{ $gEndY }}" fill="none" stroke="#22c55e" stroke-width="10"/>
                                @if($hasValue)
                                    <line x1="50" y1="50" x2="{{ $nX }}" y2="{{ $nY }}" stroke="#1f2937" stroke-width="1.6"/>
                                    <circle cx="50" cy="50" r="2.2" fill="#1f2937"/>
                                @endif
                                <text x="50" y="60" text-anchor="middle" font-size="9" font-weight="bold"
                                      fill="{{ $oeeColor === 'green' ? '#16a34a' : ($oeeColor === 'yellow' ? '#ca8a04' : ($oeeColor === 'red' ? '#dc2626' : '#6b7280')) }}">
                                    {{ $hasValue ? number_format($needleVal, 1) . '%' : 'N/A' }}
                                </text>
                                <text x="50" y="64" text-anchor="middle" font-size="3" fill="#6b7280">AVG OEE</text>
                            </svg>
                        </td>
                        <td style="border: 0; padding: 0 0 0 10px; vertical-align: top;">
                            <table class="stat-table">
                                <tr>
                                    <td><div class="stat-label">Availability</div><div class="stat-value">{{ $s['avg_availability'] !== null ? number_format($s['avg_availability'], 1) . '%' : '—' }}</div></td>
                                    <td><div class="stat-label">Performance</div><div class="stat-value">{{ $s['avg_performance'] !== null ? number_format($s['avg_performance'], 1) . '%' : '—' }}</div></td>
                                    <td><div class="stat-label">Quality</div><div class="stat-value">{{ $s['avg_quality'] !== null ? number_format($s['avg_quality'], 1) . '%' : '—' }}</div></td>
                                    <td><div class="stat-label">Days</div><div class="stat-value">{{ $s['days'] }}</div></td>
                                </tr>
                                <tr>
                                    <td><div class="stat-label">Produced</div><div class="stat-value">{{ number_format($s['total_produced']) }}</div></td>
                                    <td><div class="stat-label">Scrap</div><div class="stat-value">{{ number_format($s['total_scrap']) }}</div></td>
                                    <td><div class="stat-label">Downtime</div><div class="stat-value">{{ $s['total_downtime'] }} min</div></td>
                                    <td>
                                        <div class="stat-label">Rating</div>
                                        <div style="margin-top: 3px;">
                                            <span class="badge badge-{{ $oeeColor }}">
                                                @switch($oeeColor)
                                                    @case('green') World-class @break
                                                    @case('yellow') Typical @break
                                                    @case('red') Needs improvement @break
                                                    @default No data
                                                @endswitch
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </div>

            @if(!empty($downtimeByReason))
                @php $maxMinutes = collect($downtimeByReason)->max('total_minutes') ?: 1; @endphp
                <h3>Downtime by Reason (Pareto)</h3>
                <table class="avoid-break" style="border: 0;">
                    @foreach($downtimeByReason as $item)
                        @php
                            $fill = match($item['kind_color']) {
                                'blue' => '#60a5fa',
                                'amber' => '#fbbf24',
                                default => '#f87171',
                            };
                            $widthPct = round(($item['total_minutes'] / $maxMinutes) * 100);
                        @endphp
                        <tr class="pareto-row">
                            <td style="width: 130px;">
                                {{ $item['reason'] }}
                                <span class="badge badge-{{ $item['kind_color'] }}">{{ $item['kind_label'] }}</span>
                            </td>
                            <td style="text-align: left;">
                                <div class="pareto-bar">
                                    <div class="pareto-fill" style="width: {{ $widthPct }}%; background: {{ $fill }};"></div>
                                </div>
                            </td>
                            <td style="width: 90px; text-align: right; font-family: monospace; font-size: 9px;">
                                {{ $item['total_minutes'] }} min ({{ $item['count'] }}×)
                            </td>
                        </tr>
                    @endforeach
                </table>
            @endif

            @if($records->isNotEmpty())
                <h3>Daily Records</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Shift</th>
                            <th>Planned</th>
                            <th>Operating</th>
                            <th>Downtime</th>
                            <th>A%</th>
                            <th>P%</th>
                            <th>Q%</th>
                            <th>OEE%</th>
                            <th>Produced</th>
                            <th>Scrap</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($records as $r)
                            @php $rowColor = \App\Support\OeeBand::colorFor($r->oee_pct !== null ? (float) $r->oee_pct : null); @endphp
                            <tr>
                                <td>{{ $r->record_date->format('Y-m-d') }}</td>
                                <td>{{ $r->shift?->name ?? 'All' }}</td>
                                <td>{{ $r->planned_minutes }}</td>
                                <td>{{ $r->operating_minutes }}</td>
                                <td>{{ $r->downtime_minutes }}</td>
                                <td>{{ $r->availability_pct !== null ? number_format($r->availability_pct, 1) : '—' }}</td>
                                <td>{{ $r->performance_pct !== null ? number_format($r->performance_pct, 1) : '—' }}</td>
                                <td>{{ $r->quality_pct !== null ? number_format($r->quality_pct, 1) : '—' }}</td>
                                <td><span class="badge badge-{{ $rowColor }}">{{ $r->oee_pct !== null ? number_format($r->oee_pct, 1) : '—' }}</span></td>
                                <td>{{ number_format($r->total_produced) }}</td>
                                <td>{{ $r->scrap_qty > 0 ? number_format($r->scrap_qty) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="meta">No records for this period.</p>
            @endif
        </div>
    @empty
        <p>No active lines.</p>
    @endforelse

    <div class="footer-note">
        <strong>OEE thresholds:</strong>
        <span class="badge badge-green">≥ 85% World-class</span>
        <span class="badge badge-yellow">65–84% Typical</span>
        <span class="badge badge-red">&lt; 65% Needs improvement</span>
        &nbsp;·&nbsp;
        <strong>Downtime kinds:</strong>
        <span class="badge badge-blue">Planned</span> (subtracted from planned time) ·
        <span class="badge badge-red">Unplanned</span> &
        <span class="badge badge-amber">Changeover</span> (counted as availability loss)
    </div>

</body>
</html>
