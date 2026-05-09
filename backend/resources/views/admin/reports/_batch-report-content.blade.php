<div class="section">
    <h1>Production Series Report</h1>
    <p class="meta">Generated: {{ now()->format('Y-m-d H:i') }}</p>

    <h2>General Information</h2>
    <table>
        <tr><td style="width:35%"><strong>Work Order</strong></td><td>{{ $workOrder->order_no }}</td></tr>
        <tr><td><strong>Product</strong></td><td>{{ $workOrder->productType?->name ?? '-' }}</td></tr>
        <tr><td><strong>Line</strong></td><td>{{ $workOrder->line?->name ?? '-' }}</td></tr>
        <tr><td><strong>Workstation</strong></td><td>{{ $batch->workstation?->name ?? '-' }}</td></tr>
        <tr><td><strong>LOT Number</strong></td><td><strong>{{ $batch->lot_number ?? 'Not assigned' }}</strong></td></tr>
        <tr><td><strong>Planned Quantity</strong></td><td>{{ number_format($batch->target_qty, 2) }} pcs</td></tr>
        <tr><td><strong>Produced Quantity</strong></td><td>{{ number_format($batch->produced_qty, 2) }} pcs</td></tr>
        @if($batch->scrap_qty)
            <tr><td><strong>Scrap</strong></td><td>{{ number_format($batch->scrap_qty, 2) }} pcs</td></tr>
        @endif
        <tr><td><strong>Started</strong></td><td>{{ $batch->started_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
        <tr><td><strong>Completed</strong></td><td>{{ $batch->completed_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
        @if($batch->released_at)
            <tr><td><strong>Released</strong></td><td>{{ $batch->released_at->format('Y-m-d H:i') }} ({{ $batch->release_type === 'for_sale' ? 'For Sale' : 'For Production' }})</td></tr>
            <tr><td><strong>Released By</strong></td><td>{{ $batch->releasedBy?->name ?? '-' }}</td></tr>
        @endif
        @if($batch->expiry_date)
            <tr><td><strong>Expiry Date</strong></td><td>{{ $batch->expiry_date->format('Y-m-d') }}</td></tr>
        @endif
    </table>
</div>

@if(count($bom) > 0)
<div class="section">
    <h2>Materials (BOM)</h2>
    <table>
        <thead>
            <tr>
                <th>Material</th>
                <th>Code</th>
                <th>Type</th>
                <th>Qty/Unit</th>
                <th>Total</th>
                <th>Unit</th>
                <th>Supplier LOT</th>
            </tr>
        </thead>
        <tbody>
            @foreach($bom as $item)
                <tr>
                    <td>{{ $item['material_name'] }}</td>
                    <td>{{ $item['material_code'] }}</td>
                    <td>{{ str_replace('_', ' ', ucfirst($item['material_type'])) }}</td>
                    <td>{{ $item['quantity_per_unit'] }}</td>
                    <td><strong>{{ $item['total_qty'] }}</strong>@if($item['scrap_percentage'] > 0) <span class="meta">(+{{ $item['scrap_percentage'] }}%)</span>@endif</td>
                    <td>{{ $item['unit_of_measure'] }}</td>
                    <td class="meta">{{ $item['external_code'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<div class="section">
    <h2>Production Steps</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Step</th>
                <th>Started</th>
                <th>Started By</th>
                <th>Completed</th>
                <th>Completed By</th>
                <th>Duration</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($steps as $step)
                <tr>
                    <td>{{ $step->step_number }}</td>
                    <td>{{ $step->name }}</td>
                    <td>{{ $step->started_at?->format('Y-m-d H:i') ?? '-' }}</td>
                    <td>{{ $step->startedBy?->name ?? '-' }}</td>
                    <td>{{ $step->completed_at?->format('Y-m-d H:i') ?? '-' }}</td>
                    <td>{{ $step->completedBy?->name ?? '-' }}</td>
                    <td>{{ $step->duration_minutes ? $step->duration_minutes . ' min' : '-' }}</td>
                    <td>
                        @if($step->status === 'DONE') <span class="pass">DONE</span>
                        @elseif($step->status === 'IN_PROGRESS') <span class="badge badge-blue">IN PROGRESS</span>
                        @else {{ $step->status }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if($confirmations->isNotEmpty())
<div class="section">
    <h2>Process Confirmations</h2>
    <table>
        <thead>
            <tr><th>Date & Time</th><th>Type</th><th>Value</th><th>Confirmed By</th><th>Notes</th></tr>
        </thead>
        <tbody>
            @foreach($confirmations as $c)
                <tr>
                    <td>{{ $c->confirmed_at->format('Y-m-d H:i') }}</td>
                    <td>{{ ucfirst($c->confirmation_type) }}</td>
                    <td>{{ $c->value ?? '-' }}</td>
                    <td>{{ $c->confirmedBy?->name ?? '-' }}</td>
                    <td>{{ $c->notes ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

@if($qualityChecks->isNotEmpty())
<div class="section">
    <h2>Quality Checks ({{ $qualityChecks->count() }})</h2>
    @foreach($qualityChecks as $qc)
        <table style="margin-bottom: 8px;">
            <tr class="header-row">
                <td colspan="4">
                    <strong>Check #{{ $loop->iteration }}</strong> — {{ $qc->checked_at->format('Y-m-d H:i') }}
                    | By: {{ $qc->checkedBy?->name ?? '-' }}
                    | Production: {{ $qc->production_quantity ? number_format($qc->production_quantity, 0) . ' pcs' : '-' }}
                    | Result: @if($qc->all_passed) <span class="pass">PASS</span> @else <span class="fail">FAIL</span> @endif
                </td>
            </tr>
            <tr>
                <th>Sample #</th>
                <th>Parameter</th>
                <th>Value</th>
                <th>Result</th>
            </tr>
            @foreach($qc->samples as $s)
                <tr>
                    <td>{{ $s->sample_number }}</td>
                    <td>{{ $s->parameter_name }}</td>
                    <td>{{ $s->parameter_type === 'measurement' ? $s->value_numeric : ($s->value_boolean ? 'Yes' : 'No') }}</td>
                    <td>@if($s->is_passed) <span class="pass">PASS</span> @else <span class="fail">FAIL</span> @endif</td>
                </tr>
            @endforeach
        </table>
    @endforeach
</div>
@endif

@if($checklist)
<div class="section">
    <h2>Packaging Checklist</h2>
    <table>
        <tr><td style="width:60%">UDI code readable</td><td>@if($checklist->udi_readable) <span class="pass">PASS</span> @else <span class="fail">FAIL</span> @endif</td></tr>
        <tr><td>Packaging in good condition</td><td>@if($checklist->packaging_condition) <span class="pass">PASS</span> @else <span class="fail">FAIL</span> @endif</td></tr>
        <tr><td>Labels readable</td><td>@if($checklist->labels_readable) <span class="pass">PASS</span> @else <span class="fail">FAIL</span> @endif</td></tr>
        <tr><td>Label matches product</td><td>@if($checklist->label_matches_product) <span class="pass">PASS</span> @else <span class="fail">FAIL</span> @endif</td></tr>
        <tr><td><strong>Overall</strong></td><td>@if($checklist->all_passed) <span class="pass">ALL PASS</span> @else <span class="fail">FAILED</span> @endif</td></tr>
    </table>
    <p class="meta">Checked by: {{ $checklist->checkedBy?->name ?? '-' }} | {{ $checklist->checked_at?->format('Y-m-d H:i') }}</p>
</div>
@endif
