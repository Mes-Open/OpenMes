<div class="section">
    <h1>生产批次报告</h1>
    <p class="meta">生成时间： {{ now()->format('Y-m-d H:i') }}</p>

    <h2>基本信息</h2>
    <table>
        <tr><td style="width:35%"><strong>工单</strong></td><td>{{ $workOrder->order_no }}</td></tr>
        <tr><td><strong>产品</strong></td><td>{{ $workOrder->productType?->name ?? '-' }}</td></tr>
        <tr><td><strong>产线</strong></td><td>{{ $workOrder->line?->name ?? '-' }}</td></tr>
        <tr><td><strong>工位</strong></td><td>{{ $batch->workstation?->name ?? '-' }}</td></tr>
        <tr><td><strong>批次号</strong></td><td><strong>{{ $batch->lot_number ?? '未分配' }}</strong></td></tr>
        <tr><td><strong>计划数量</strong></td><td>{{ number_format($batch->target_qty, 2) }} 件</td></tr>
        <tr><td><strong>实际产量</strong></td><td>{{ number_format($batch->produced_qty, 2) }} 件</td></tr>
        @if($batch->scrap_qty)
            <tr><td><strong>报废</strong></td><td>{{ number_format($batch->scrap_qty, 2) }} 件</td></tr>
        @endif
        <tr><td><strong>开始时间</strong></td><td>{{ $batch->started_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
        <tr><td><strong>完成时间</strong></td><td>{{ $batch->completed_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
        @if($batch->released_at)
            <tr><td><strong>放行时间</strong></td><td>{{ $batch->released_at->format('Y-m-d H:i') }} ({{ $batch->release_type === 'for_sale' ? '可销售' : '用于生产' }})</td></tr>
            <tr><td><strong>放行人</strong></td><td>{{ $batch->releasedBy?->name ?? '-' }}</td></tr>
        @endif
        @if($batch->expiry_date)
            <tr><td><strong>到期日</strong></td><td>{{ $batch->expiry_date->format('Y-m-d') }}</td></tr>
        @endif
    </table>
</div>

@if(count($bom) > 0)
<div class="section">
    <h2>物料（BOM）</h2>
    <table>
        <thead>
            <tr>
                <th>物料</th>
                <th>编码</th>
                <th>类型</th>
                <th>单件用量</th>
                <th>合计</th>
                <th>单位</th>
                <th>供应商批号</th>
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
    <h2>生产步骤</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>工序</th>
                <th>开始时间</th>
                <th>开始人</th>
                <th>完成时间</th>
                <th>完成人</th>
                <th>时长</th>
                <th>状态</th>
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
                    <td>{{ $step->duration_minutes ? $step->duration_minutes . ' 分钟' : '-' }}</td>
                    <td>
                        @if($step->status === 'DONE') <span class="pass">已完成</span>
                        @elseif($step->status === 'IN_PROGRESS') <span class="badge badge-blue">进行中</span>
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
    <h2>工序确认</h2>
    <table>
        <thead>
            <tr><th>日期时间</th><th>类型</th><th>值</th><th>确认人</th><th>备注</th></tr>
        </thead>
        <tbody>
            @foreach($confirmations as $c)
                <tr>
                    <td>{{ $c->confirmed_at->format('Y-m-d H:i') }}</td>
                    <td>{{ ['parameters' => '参数确认', 'drying' => '干燥确认', 'custom' => '自定义确认'][$c->confirmation_type] ?? $c->confirmation_type }}</td>
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
    <h2>质量检验 ({{ $qualityChecks->count() }})</h2>
    @foreach($qualityChecks as $qc)
        <table style="margin-bottom: 8px;">
            <tr class="header-row">
                <td colspan="4">
                    <strong>检验 #{{ $loop->iteration }}</strong> — {{ $qc->checked_at->format('Y-m-d H:i') }}
                    | 检验人： {{ $qc->checkedBy?->name ?? '-' }}
                    | 产量： {{ $qc->production_quantity ? number_format($qc->production_quantity, 0) . ' 件' : '-' }}
                    | 结果： @if($qc->all_passed) <span class="pass">通过</span> @else <span class="fail">失败</span> @endif
                </td>
            </tr>
            <tr>
                <th>样本 #</th>
                <th>参数</th>
                <th>值</th>
                <th>结果</th>
            </tr>
            @foreach($qc->samples as $s)
                <tr>
                    <td>{{ $s->sample_number }}</td>
                    <td>{{ $s->parameter_name }}</td>
                    <td>{{ $s->parameter_type === 'measurement' ? $s->value_numeric : ($s->value_boolean ? '是' : '否') }}</td>
                    <td>@if($s->is_passed) <span class="pass">通过</span> @else <span class="fail">失败</span> @endif</td>
                </tr>
            @endforeach
        </table>
    @endforeach
</div>
@endif

@if($checklist)
<div class="section">
    <h2>包装检查表</h2>
    <table>
        <tr><td style="width:60%">UDI 码可读</td><td>@if($checklist->udi_readable) <span class="pass">通过</span> @else <span class="fail">失败</span> @endif</td></tr>
        <tr><td>包装完好</td><td>@if($checklist->packaging_condition) <span class="pass">通过</span> @else <span class="fail">失败</span> @endif</td></tr>
        <tr><td>标签可读</td><td>@if($checklist->labels_readable) <span class="pass">通过</span> @else <span class="fail">失败</span> @endif</td></tr>
        <tr><td>标签与产品一致</td><td>@if($checklist->label_matches_product) <span class="pass">通过</span> @else <span class="fail">失败</span> @endif</td></tr>
        <tr><td><strong>总体结果</strong></td><td>@if($checklist->all_passed) <span class="pass">全部通过</span> @else <span class="fail">未通过</span> @endif</td></tr>
    </table>
    <p class="meta">检查人： {{ $checklist->checkedBy?->name ?? '-' }} | {{ $checklist->checked_at?->format('Y-m-d H:i') }}</p>
</div>
@endif
