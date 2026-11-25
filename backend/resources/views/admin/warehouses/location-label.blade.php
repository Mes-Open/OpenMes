<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex">
    <title>库位标签</title>
    <style>
        @page { size: 283pt 198pt; margin: 0; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            color: #1a1917;
            font-family: "CoaNotoSans", "DejaVu Sans", sans-serif;
        }

        .label-page {
            width: 283pt;
            height: 198pt;
            padding: 0;
            background: #fffdf9;
            overflow: hidden;
            position: relative;
        }

        .rail {
            width: 5pt;
            height: 198pt;
            background: #ea5a2b;
            position: absolute;
            left: 0;
            top: 0;
        }

        .inner {
            padding: 11pt 13pt 9pt 18pt;
        }

        .head {
            width: 100%;
            border-bottom: 0.8pt solid #d8d2c8;
            padding-bottom: 5pt;
        }

        .brand {
            font-size: 7.4pt;
            font-weight: bold;
            letter-spacing: 0.4pt;
            color: #1a1917;
        }

        .brand-sub {
            margin-top: 1.2pt;
            font-size: 5.4pt;
            letter-spacing: 0.3pt;
            color: #9b9892;
        }

        .type {
            text-align: right;
            font-size: 7pt;
            font-weight: bold;
            color: #ea5a2b;
            letter-spacing: 0.8pt;
        }

        .main {
            width: 100%;
            margin-top: 8pt;
        }

        .left {
            width: 194pt;
            vertical-align: top;
            padding-right: 6pt;
        }

        .zone {
            font-size: 6.6pt;
            font-weight: bold;
            color: #6f6c66;
            letter-spacing: 0.2pt;
            white-space: nowrap;
            overflow: hidden;
        }

        .code {
            margin-top: 3pt;
            font-family: "DejaVu Sans Mono", monospace;
            font-size: 27pt;
            font-weight: bold;
            line-height: 0.95;
            letter-spacing: -0.6pt;
            color: #1a1917;
        }

        .name {
            margin-top: 4pt;
            font-size: 11.6pt;
            font-weight: bold;
            line-height: 1.15;
            color: #ea5a2b;
        }

        .desc {
            margin-top: 4pt;
            font-size: 6.3pt;
            line-height: 1.35;
            color: #6f6c66;
        }

        .qr-cell {
            width: 72pt;
            vertical-align: top;
            text-align: center;
        }

        .qr {
            width: 64pt;
            height: 64pt;
            padding: 2pt;
            border: 0.6pt solid #d8d2c8;
            background: #ffffff;
        }

        .qr-caption {
            margin-top: 3pt;
            font-size: 5.6pt;
            font-weight: bold;
            color: #6f6c66;
            letter-spacing: 0.3pt;
        }

        .foot {
            margin-top: 9pt;
            padding-top: 4pt;
            border-top: 0.6pt solid #d8d2c8;
            font-size: 5.2pt;
            color: #9b9892;
        }

        .foot-right {
            text-align: right;
        }
    </style>
</head>
<body>
@foreach ($labels as $label)
    <div class="label-page" @if (! $loop->last) style="page-break-after: always;" @endif>
        <div class="rail"></div>
        <div class="inner">
            <table class="head" cellpadding="0" cellspacing="0">
                <tr>
                    <td>
                        <div class="brand">逅唐茶业 · OpenMES</div>
                        <div class="brand-sub">HOUTANG TEA · WAREHOUSE</div>
                    </td>
                    <td class="type">库位 / BIN LOCATION</td>
                </tr>
            </table>

            <table class="main" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="left">
                        <div class="zone">{{ $label['warehouse_code'] }} / {{ $label['zone_code'] }} · {{ $label['zone_name'] }}</div>
                        <div class="code">{{ $label['code'] }}</div>
                        <div class="name">{{ $label['name'] }}</div>
                        @if ($label['description'])
                            <div class="desc">{{ $label['description'] }}</div>
                        @endif
                    </td>
                    <td class="qr-cell">
                        <img class="qr" src="{{ $label['qr'] }}" alt="库位二维码">
                        <div class="qr-caption">扫码查看库位</div>
                    </td>
                </tr>
            </table>

            <table class="foot" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td>{{ $label['warehouse_name'] }} · {{ $label['warehouse_code'] }}</td>
                    <td class="foot-right">生成 {{ $label['generated_at'] }}</td>
                </tr>
            </table>
        </div>
    </div>
@endforeach
</body>
</html>
