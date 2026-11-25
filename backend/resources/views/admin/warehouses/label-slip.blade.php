<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title>标签领用单 · {{ $batch->batch_number }}</title>
    <meta name="robots" content="noindex">
    <style>
        :root {
            --paper: #fffdf9;
            --ink: #1a1917;
            --muted: #6f6c66;
            --faint: #9b9892;
            --line: #ddd8cf;
            --line-strong: #b9b3a8;
            --accent: #ea5a2b;
            --pass: #17805b;
            --pass-bg: #eaf5ef;
            --block: #c8352a;
            --block-bg: #fcece9;
            --missing: #9a6209;
            --missing-bg: #fbf3e2;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #e9e8e3;
            color: var(--ink);
            font-family: "Geist", -apple-system, BlinkMacSystemFont, "PingFang SC", "Noto Sans SC", sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .screen-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            max-width: 148mm;
            margin: 0 auto;
            padding: 18px 0 10px;
        }

        .screen-bar a {
            color: var(--muted);
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
        }

        .screen-bar a:hover { color: var(--ink); }

        .screen-bar button {
            min-height: 40px;
            padding: 0 18px;
            border: 0;
            border-radius: 8px;
            background: var(--accent);
            color: #fff;
            font: inherit;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .screen-bar button:hover { filter: brightness(.95); }

        .sheet {
            width: 148mm;
            min-height: 210mm;
            margin: 0 auto 28px;
            padding: 11mm 11mm 9mm;
            background: var(--paper);
            border: 1px solid #d4cfc5;
            box-shadow: 0 16px 40px rgba(32, 29, 24, .12);
        }

        .rule { height: 3px; background: var(--accent); margin: 0 0 5mm; }

        .head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 6mm;
        }

        .brand {
            font-size: 9pt;
            font-weight: 700;
            letter-spacing: .02em;
        }

        .brand-en {
            margin-top: 2px;
            color: var(--faint);
            font-size: 6.6pt;
            letter-spacing: .02em;
        }

        h1 {
            margin: 2.8mm 0 1mm;
            font-size: 17pt;
            line-height: 1;
            letter-spacing: -.02em;
        }

        .sub {
            color: var(--muted);
            font-size: 8pt;
        }

        .doc-meta {
            min-width: 42mm;
            padding-top: 1mm;
            text-align: right;
        }

        .slip-no {
            font-family: "Geist Mono", ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 9pt;
            font-weight: 700;
            white-space: nowrap;
        }

        .doc-meta div + div { margin-top: 2.2mm; }

        .identity {
            margin-top: 4mm;
            border-top: 1px solid var(--line-strong);
            border-left: 1px solid var(--line);
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        .identity-cell {
            min-width: 0;
            padding: 1.7mm 2.6mm 1.9mm;
            border-right: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
        }

        .identity-cell.wide { grid-column: 1 / -1; }

        .label {
            color: var(--muted);
            font-size: 6.5pt;
            font-weight: 700;
            letter-spacing: .05em;
        }

        .value {
            margin-top: 1.1mm;
            font-size: 9pt;
            font-weight: 600;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .value.mono {
            font-family: "Geist Mono", ui-monospace, SFMono-Regular, Menlo, monospace;
            letter-spacing: -.02em;
        }

        .value.big { font-size: 12pt; }

        .section-title {
            margin: 4mm 0 1.6mm;
            color: var(--muted);
            font-size: 7pt;
            font-weight: 700;
            letter-spacing: .12em;
        }

        .counts {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            border-top: 1px solid var(--line-strong);
            border-left: 1px solid var(--line-strong);
        }

        .count {
            padding: 2mm 2.6mm 2.2mm;
            border-right: 1px solid var(--line-strong);
            border-bottom: 1px solid var(--line-strong);
        }

        .count-label {
            color: var(--muted);
            font-size: 7pt;
            font-weight: 700;
        }

        .count-value {
            margin-top: 1.5mm;
            font-family: "Geist Mono", ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 15pt;
            font-weight: 700;
            line-height: 1;
            letter-spacing: -.03em;
        }

        .count-unit {
            margin-top: 1.2mm;
            color: var(--faint);
            font-size: 6pt;
        }

        .variance {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 3mm;
            padding: 2.2mm 2.6mm;
            border: 1px solid var(--line-strong);
            border-top: 0;
        }

        .variance-left {
            color: var(--muted);
            font-size: 7.2pt;
            line-height: 1.4;
        }

        .variance-value {
            font-family: "Geist Mono", ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 15pt;
            font-weight: 700;
            white-space: nowrap;
        }

        .gate {
            margin-top: 2.6mm;
            padding: 2.4mm 2.8mm;
            border: 1px solid;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 4mm;
        }

        .gate-pass { background: var(--pass-bg); border-color: #bfdccd; }
        .gate-block { background: var(--block-bg); border-color: #edc4bd; }
        .gate-missing { background: var(--missing-bg); border-color: #e6d2a8; }

        .gate-title {
            font-size: 10.5pt;
            font-weight: 700;
            line-height: 1.2;
        }

        .gate-pass .gate-title, .gate-pass .gate-side { color: var(--pass); }
        .gate-block .gate-title, .gate-block .gate-side { color: var(--block); }
        .gate-missing .gate-title, .gate-missing .gate-side { color: var(--missing); }

        .gate-note {
            margin-top: 1.2mm;
            color: var(--muted);
            font-size: 7pt;
            line-height: 1.45;
        }

        .gate-side {
            font-family: "Geist Mono", ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 8pt;
            font-weight: 700;
            text-align: right;
            white-space: nowrap;
        }

        .notes {
            margin-top: 3mm;
            min-height: 12mm;
            padding: 2mm 2.6mm;
            border: 1px solid var(--line);
            background: #faf8f4;
        }

        .notes .value { font-weight: 500; line-height: 1.55; }

        .signatures {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 5mm;
            margin-top: 6mm;
        }

        .signature {
            min-height: 10mm;
            border-top: 1px solid var(--line-strong);
            padding-top: 1.5mm;
        }

        .signature div { color: var(--muted); font-size: 6.5pt; }
        .signature .line { margin-top: 5mm; color: var(--faint); font-size: 6pt; }

        footer {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 4mm;
            margin-top: 4mm;
            padding-top: 2mm;
            border-top: 1px solid var(--line);
            color: var(--faint);
            font-size: 6.2pt;
            line-height: 1.5;
        }

        .foot-mark {
            font-family: "Geist Mono", ui-monospace, SFMono-Regular, Menlo, monospace;
            color: var(--muted);
            white-space: nowrap;
        }

        @page { size: A5 portrait; margin: 8mm; }

        @media print {
            body { background: #fff; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .screen-bar { display: none; }
            .sheet {
                width: auto;
                max-width: none;
                min-height: auto;
                margin: 0;
                padding: 0;
                border: 0;
                box-shadow: none;
                background: #fff;
            }
        }
    </style>
</head>
<body>
@php
    $gateMeta = [
        'PASS' => ['class' => 'gate-pass', 'title' => 'G09 已确认通过', 'side' => '可放行', 'note' => '领用 = 消耗 + 报废 + 退回，标签数量已闭环。'],
        'BLOCK' => ['class' => 'gate-block', 'title' => 'G09 阻断 · 差异未平', 'side' => '不得放行', 'note' => '差异未查清前，批次不得放行；请核对实物标签后重新登记。'],
        'MISSING' => ['class' => 'gate-missing', 'title' => '待登记', 'side' => '无记录', 'note' => '尚无标签平衡记录；本单只作领用凭证，不能据此放行。'],
    ][$gate] ?? ['class' => 'gate-missing', 'title' => '待登记', 'side' => '无记录', 'note' => '尚无标签平衡记录。'];

    $statusLabel = [
        'IN_PROGRESS' => '生产中',
        'DONE' => '已完成',
        'ON_HOLD' => '暂停',
        'CANCELLED' => '已取消',
    ][$batch->status] ?? $batch->status;

    $fmt = fn ($value) => $counts ? number_format((float) $value, 0) : '—';
@endphp

<div class="screen-bar">
    <a href="/admin/warehouses">← 返回标签平衡</a>
    <button type="button" onclick="window.print()">打印 / 另存 PDF</button>
</div>

<main class="sheet">
    <div class="rule"></div>

    <header class="head">
        <div>
            <div class="brand">贵州逅唐茶业生物科技有限公司</div>
            <div class="brand-en">Guizhou Houtang Tea Biological Technology Co., Ltd.</div>
            <h1>标签领用单</h1>
            <div class="sub">Label Issue Slip · 仓储作业</div>
        </div>
        <div class="doc-meta">
            <div class="slip-no">{{ $slipNo }}</div>
            <div class="sub">打印日期 {{ $generatedAt->format('Y-m-d') }}</div>
            <div class="sub">登记人 {{ $check?->checkedBy?->name ?? '—' }}</div>
        </div>
    </header>

    <div class="identity">
        <div class="identity-cell">
            <div class="label">批次</div>
            <div class="value mono big">{{ $batch->batch_number }}</div>
        </div>
        <div class="identity-cell">
            <div class="label">生产工单</div>
            <div class="value mono">{{ $batch->workOrder?->order_no ?? '—' }}</div>
        </div>
        <div class="identity-cell">
            <div class="label">产品</div>
            <div class="value">{{ $batch->workOrder?->productType?->name ?? '—' }}</div>
        </div>
        <div class="identity-cell">
            <div class="label">客户 / 品牌</div>
            <div class="value">{{ $batch->workOrder?->customer?->name ?? '—' }}</div>
        </div>
        <div class="identity-cell">
            <div class="label">批次状态</div>
            <div class="value">{{ $statusLabel }}</div>
        </div>
        <div class="identity-cell">
            <div class="label">计划 / 产出</div>
            <div class="value mono">{{ number_format((float) $batch->target_qty, 0) }} / {{ number_format((float) $batch->produced_qty, 0) }}</div>
        </div>
        <div class="identity-cell wide">
            <div class="label">最近登记</div>
            <div class="value">
                @if ($check)
                    {{ $check->checked_at?->format('Y-m-d H:i') ?? '—' }} · {{ $check->checkedBy?->name ?? '未记录' }}
                    <span style="color:var(--faint);font-weight:500;"> · {{ $check->status === 'PASS' ? 'PASS' : 'FAIL' }}</span>
                @else
                    尚无标签平衡记录
                @endif
            </div>
        </div>
    </div>

    <div class="section-title">标签数量</div>
    <div class="counts">
        <div class="count">
            <div class="count-label">领用</div>
            <div class="count-value">{{ $fmt($counts['issued'] ?? 0) }}</div>
            <div class="count-unit">张</div>
        </div>
        <div class="count">
            <div class="count-label">消耗</div>
            <div class="count-value">{{ $fmt($counts['consumed'] ?? 0) }}</div>
            <div class="count-unit">张</div>
        </div>
        <div class="count">
            <div class="count-label">报废</div>
            <div class="count-value">{{ $fmt($counts['scrapped'] ?? 0) }}</div>
            <div class="count-unit">张</div>
        </div>
        <div class="count">
            <div class="count-label">退回</div>
            <div class="count-value">{{ $fmt($counts['returned'] ?? 0) }}</div>
            <div class="count-unit">张</div>
        </div>
    </div>

    <div class="variance">
        <div class="variance-left">
            差异 = 领用 − 消耗 − 报废 − 退回<br>
            <span style="color:var(--faint);font-size:6pt;">未平差异必须查清后重新登记，不能用旧记录覆盖。</span>
        </div>
        <div class="variance-value" style="color:{{ $counts && abs((float) $variance) >= 0.0001 ? 'var(--block)' : 'var(--pass)' }};">
            @if ($counts)
                {{ $variance > 0 ? '+' : ($variance < 0 ? '−' : '') }}{{ number_format(abs((float) $variance), 0) }}
            @else
                —
            @endif
        </div>
    </div>

    <div class="gate {{ $gateMeta['class'] }}">
        <div>
            <div class="gate-title">{{ $gateMeta['title'] }}</div>
            <div class="gate-note">{{ $gateMeta['note'] }}</div>
        </div>
        <div class="gate-side">{{ $gateMeta['side'] }}</div>
    </div>

    <div class="notes">
        <div class="label">备注</div>
        <div class="value">{{ $notes ?: '无' }}</div>
    </div>

    <div class="signatures">
        <div class="signature">
            <div>发料人</div>
            <div class="line">签字 / 日期</div>
        </div>
        <div class="signature">
            <div>领用人</div>
            <div class="line">签字 / 日期</div>
        </div>
        <div class="signature">
            <div>复核人</div>
            <div class="line">签字 / 日期</div>
        </div>
    </div>

    <footer>
        <div>本单与仓储标签平衡同源；差异未平时 G09 阻断批次放行。<br>纸质签字后由仓储归档，系统记录以 OpenMES 为准。</div>
        <div class="foot-mark">{{ $slipNo }}</div>
    </footer>
</main>
</body>
</html>
