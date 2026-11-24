<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>分析证书 {{ $coaNo }}</title>
    <style>
        @page { margin: 8mm 13mm 6mm; }
        body { font-family: "CoaNotoSans", "DejaVu Sans", sans-serif; font-size: 9.6px; color: #1f2937; }
        strong { font-weight: bold; color: #111827; }
        h1 { font-size: 18px; font-weight: bold; margin: 0 0 1px; letter-spacing: 1px; }
        h2 { font-size: 12px; font-weight: bold; margin: 6px 0 2px; color: #0f766e; border-bottom: 1.5px solid #0f766e; padding-bottom: 2px; }
        .company { font-size: 13px; font-weight: bold; color: #111827; }
        .sub { font-size: 9px; color: #6b7280; }
        .head { display: table; width: 100%; }
        .head > div { display: table-cell; vertical-align: top; }
        .head .right { text-align: right; }
        .coa-no { font-family: monospace; font-size: 10.5px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 1px 0 3px; }
        th, td { border: 1px solid #d1d5db; padding: 1px 3px; font-size: 8.7px; vertical-align: top; }
        th { background: #f0fdfa; color: #134e4a; text-align: left; font-weight: bold; }
        .pass { color: #15803d; font-weight: bold; }
        .fail { color: #b91c1c; font-weight: bold; }
        .muted { color: #6b7280; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 8.5px; font-weight: bold; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-amber { background: #fef3c7; color: #92400e; }
        .box { border: 1px solid #d1d5db; padding: 4px 8px; margin: 4px 0; }
        .sign { width: 100%; margin-top: 5px; }
        .sign td { border: none; padding: 4px 6px 1px; border-bottom: 1px solid #9ca3af; font-size: 8.3px; }
        .sign .label { border: none; color: #6b7280; padding-top: 2px; }
        .foot { margin-top: 1px; font-size: 7.6px; color: #6b7280; line-height: 1.3; }
        .watermark { position: fixed; top: 40%; left: 12%; font-size: 46px; color: rgba(185, 28, 28, 0.12); transform: rotate(-18deg); font-weight: bold; }
    </style>
</head>
<body>
@if (! $isIssued)
    <div class="watermark">{{ $isVoid ? 'COA 已作废' : ($isReleased ? 'COA 待签发' : 'COA 草案 · 未放行') }}</div>
@endif

<div class="head">
    <div>
        <div class="company">贵州逅唐茶业生物科技有限公司</div>
        <div class="sub">Guizhou Houtang Tea Biological Technology Co., Ltd.</div>
        <h1>分析证书</h1>
        <div class="sub">Certificate of Analysis (COA)</div>
    </div>
    <div class="right">
        <div class="coa-no">{{ $coaNo }}</div>
        <div class="sub">文件状态：{{ $isIssued ? '已签发' : ($isVoid ? '已作废' : '草案') }}</div>
        <div class="sub">签发日期：{{ $generatedAt->format('Y-m-d') }}</div>
        <div class="sub">版本：{{ $coaVersion }}</div>
        @if (! empty($changeReason))
            <div class="sub">重签原因：{{ \Illuminate\Support\Str::limit($changeReason, 44) }}</div>
        @endif
    </div>
</div>

<h2>一、产品与批次信息</h2>
<table>
    <tr>
        <th width="18%">客户/品牌</th>
        <td width="32%">{{ $workOrder?->customer?->name ?? $workOrder?->customer_name ?? '—' }}</td>
        <th width="18%">客户 SKU</th>
        <td>{{ $workOrder?->productRevision?->external_ref ?? '—' }}</td>
    </tr>
    <tr>
        <th>产品名称</th>
        <td>{{ $workOrder?->productType?->name ?? '—' }}</td>
        <th>配方版本</th>
        <td>{{ $workOrder?->productRevision?->revision_code ?? '—' }}</td>
    </tr>
    <tr>
        <th>生产批次</th>
        <td><strong>{{ $batch->lot_number ?? '—' }}</strong></td>
        <th>生产工单</th>
        <td>{{ $workOrder?->order_no ?? '—' }}</td>
    </tr>
    <tr>
        <th>产线</th>
        <td>{{ $workOrder?->line?->name ?? '—' }}</td>
        <th>生产日期</th>
        <td>{{ $batch->completed_at?->format('Y-m-d') ?? $batch->started_at?->format('Y-m-d') ?? '—' }}</td>
    </tr>
    <tr>
        <th>成品数量</th>
        <td>{{ number_format((float) $batch->produced_qty, 2) }} {{ $workOrder?->productType?->unit_of_measure ?? 'kg' }}</td>
        <th>放行状态</th>
        <td>
            @if ($isIssued)
                <span class="badge badge-green">已签发</span>
            @elseif ($isReleased)
                <span class="badge badge-amber">已放行待签发</span>
            @else
                <span class="badge badge-amber">待 QA 放行</span>
            @endif
        </td>
    </tr>
    <tr>
        <th>保质期至</th>
        <td>{{ $batch->expiry_date?->format('Y-m-d') ?? '—' }}</td>
        <th>贮存条件</th>
        <td>阴凉、干燥、避光、密封保存</td>
    </tr>
</table>

@if (! empty($specification['parameters']))
    <h2>二、规格要求</h2>
    @if (! empty($specification['standard_code']) || ! empty($specification['sensory_profile']))
        <div class="sub" style="margin-bottom:4px;">
            客户标准：{{ $specification['standard_code'] ?? '—' }} {{ $specification['standard_version'] ?? '' }}
            @if (! empty($specification['sensory_profile']))｜感官：{{ $specification['sensory_profile'] }}@endif
        </div>
    @endif
    <table>
        <thead>
        <tr>
            <th width="35%">检验项目</th>
            <th width="20%">类型</th>
            <th width="30%">限值</th>
            <th width="15%">单位</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($specification['parameters'] as $parameter)
            <tr>
                <td>{{ $parameter['name'] ?? $parameter['名称'] ?? '—' }}</td>
                <td>{{ ($parameter['type'] ?? '') === 'measurement' ? '测量值' : '合格/不合格' }}</td>
                <td>
                    @if (($parameter['type'] ?? '') === 'measurement')
                        {{ $parameter['min'] ?? '—' }} – {{ $parameter['max'] ?? '—' }}
                    @else
                        符合 / 不符合
                    @endif
                </td>
                <td>{{ $parameter['unit'] ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<h2>三、检验结果</h2>
@if ($qualitySnapshot)
    <div class="sub" style="margin-bottom:6px;">
        检验时间：{{ ! empty($qualitySnapshot['checked_at']) ? \Illuminate\Support\Carbon::parse($qualitySnapshot['checked_at'])->format('Y-m-d H:i') : '—' }}
        ｜ 检验人：{{ $qualitySnapshot['checked_by'] ?? '—' }}
        ｜ 样本数：{{ $samples->count() }}
    </div>
    <table>
        <thead>
        <tr>
            <th width="6%">序号</th>
            <th width="30%">检验项目</th>
            <th width="18%">类型</th>
            <th width="26%">实测值</th>
            <th width="20%">判定</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($samples as $i => $sample)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $sample->parameter_name }}</td>
                <td>{{ $sample->parameter_type === 'measurement' ? '测量值' : '合格/不合格' }}</td>
                <td>
                    @if ($sample->parameter_type === 'measurement')
                        {{ $sample->value_numeric !== null ? number_format((float) $sample->value_numeric, 2) : '—' }}
                    @else
                        {{ $sample->value_boolean === null ? '—' : ($sample->value_boolean ? '合格' : '不合格') }}
                    @endif
                </td>
                <td>
                    @if ($sample->is_passed === null)
                        <span class="muted">未判定</span>
                    @elseif ($sample->is_passed)
                        <span class="pass">合格</span>
                    @else
                        <span class="fail">不合格</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">暂无检验明细。</td></tr>
        @endforelse
        </tbody>
    </table>
@else
    <div class="box muted">本批次尚未记录成品检验，暂不能出具正式 COA。请完成成品检验并放行后重新生成。</div>
@endif

<h2>四、外检报告</h2>
@if ($labTest)
    <div class="sub" style="margin-bottom:4px;">
        {{ $labTest['lab_name'] ?? '—' }} ｜ 报告编号 {{ $labTest['report_number'] ?? '—' }}
        ｜ 签发 {{ $labTest['issued_at'] ?? '—' }}
        ｜ 结论
        @if (($labTest['overall_conclusion'] ?? '') === 'PASS')
            <span class="pass">合格</span>
        @else
            <span class="fail">不合格</span>
        @endif
    </div>
    <table>
        <thead>
        <tr>
            <th width="14%">类别</th>
            <th width="70%">项目 / 实测 / 限值</th>
            <th width="16%">判定</th>
        </tr>
        </thead>
        <tbody>
        @php $labGroups = collect($labTest['items'] ?? [])->groupBy('category'); @endphp
        @forelse ($labGroups as $category => $items)
            <tr>
                <td>{{ $category }}</td>
                <td>{{ $items->map(fn ($item) => ($item['name'] ?? '').' '.($item['result'] ?? '').'（限 '.($item['limit'] ?? '—').'）')->implode('；') }}</td>
                <td>
                    @if ($items->every(fn ($item) => ($item['conclusion'] ?? '') === '合格'))
                        <span class="pass">合格</span>
                    @else
                        <span class="fail">不合格</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="3" class="muted">报告未登记检测项目。</td></tr>
        @endforelse
        </tbody>
    </table>
@elseif ($externalLabRequired ?? false)
    <div class="box" style="border-color:#b23b3b;color:#8a2b2b;">客户标准要求农残、重金属、微生物外检，但尚未登记第三方报告；本 COA 不具备签发条件。</div>
@else
    <div class="box muted">客户标准未要求第三方外检，本项不适用。</div>
@endif

<h2>五、综合结论</h2>
<div class="box">
    <table style="margin:0;">
        <tr>
            <th width="20%">检验结论</th>
            <td>
                @if (! $qualitySnapshot)
                    <span class="badge badge-amber">待检</span>
                @elseif ($allPassed)
                    <span class="badge badge-green">合格</span>
                @else
                    <span class="badge badge-red">不合格</span>
                @endif
            </td>
            <th width="20%">放行结论</th>
            <td>
                @if ($isIssued)
                    <span class="badge badge-green">已签发</span>
                @elseif ($isReleased)
                    <span class="badge badge-amber">已放行待签发</span>
                @else
                    <span class="badge badge-amber">待 QA 放行</span>
                @endif
            </td>
        </tr>
        <tr>
            <th>投料称重</th>
            <td colspan="3">
                @php
                    $weighingPassed = (int) ($weighing['passed'] ?? 0);
                    $weighingTotal = (int) ($weighing['total'] ?? 0);
                @endphp
                @if ($weighingTotal === 0)
                    <span class="muted">本批 BOM 无称重原料</span>
                @elseif ($weighingPassed === $weighingTotal)
                    <span class="pass">{{ $weighingPassed.'/'.$weighingTotal }} 种原料在公差内</span>
                @else
                    <span class="fail">{{ $weighingPassed.'/'.$weighingTotal }} 种原料合格，存在超差或缺记录</span>
                @endif
            </td>
        </tr>
        <tr>
            <th>执行标准</th>
            <td colspan="3">{{ $specification['standard_reference'] ?? '客户标准 / 产品企业标准（以客户批准版本为准）' }}</td>
        </tr>
        <tr>
            <th>备注</th>
            <td colspan="3">
                @if (! $isReleased)
                    本文件为系统生成的 COA 草案，须经 QA 放行并签名后生效。
                @elseif (! $isIssued)
                    本文件已通过放行门，但尚未签发；不得作为对外正式 COA 使用。
                @else
                    本 COA 仅对应上述批次，版本与检验结果已冻结；涂改无效，如有异议请在收到后 7 日内提出。
                @endif
            </td>
        </tr>
    </table>
</div>

<table class="sign">
    <tr>
        <td width="33%">{{ $qualitySnapshot['checked_by'] ?? '—' }}</td>
        <td width="33%">{{ $document?->approvedBy?->name ?? '—' }}</td>
        <td width="33%">{{ $document?->issuedBy?->name ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">检验人 / 日期</td>
        <td class="label">质量负责人 / 日期</td>
        <td class="label">批准 / 签发人 / 日期</td>
    </tr>
</table>

<div class="foot">
    地址：贵州省遵义市湄潭县｜文件编号：{{ $coaNo }}｜版本：{{ $coaVersion }}｜状态：{{ $isIssued ? '已签发' : ($isVoid ? '已作废' : '草案') }}｜生成时间：{{ $generatedAt->format('Y-m-d H:i:s') }}<br>
    本证书由 OpenMES 质量模块生成，检验结果来源于批次质量检查记录；正式对外文件以本版本及电子签名记录为准。
</div>
</body>
</html>
