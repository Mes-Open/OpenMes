import { Head, router } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import { formatDateTime } from '../../../lib/i18n';

function qty(value) {
    return Number(value ?? 0).toFixed(3).replace(/\.000$/, '');
}

export default function SupplierQualityIndex({ rows = [], summary = {}, month, monthOptions = [] }) {
    const changeMonth = (value) => {
        router.get('/admin/supplier-quality', { month: value }, { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title="供应商质量" />

            <div className="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-5">
                {[
                    { label: '供应商', value: summary.suppliers ?? 0, hint: '本期有到货记录' },
                    { label: '到货批次', value: summary.lots ?? 0, hint: '按物料批计' },
                    { label: '受检批次', value: summary.inspected ?? 0, hint: '已完成来料检验' },
                    { label: '检验合格率', value: summary.pass_rate === null || summary.pass_rate === undefined ? '—' : `${summary.pass_rate}%`, hint: '合格 + 让步接收 / 已检' },
                    { label: '退货数量', value: qty(summary.returned_qty), hint: '处置方式为退货' },
                ].map((card) => (
                    <div key={card.label} className="rounded-om-md border border-om-line bg-om-bg px-4 py-3">
                        <div className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{card.label}</div>
                        <div className="mt-1 text-[24px] font-semibold tracking-[-0.02em] text-om-ink">{card.value}</div>
                        <div className="mt-1 text-xs text-om-muted">{card.hint}</div>
                    </div>
                ))}
            </div>

            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-om-ink">供应商质量</h1>
                    <p className="mt-1 max-w-2xl text-sm text-om-muted">
                        按月汇总来料检验、质量冻结和处置结果。退货、报废、拒收按实际出库数量统计，可直接用于供应商月度评分和索赔对账。
                    </p>
                </div>
                <label className="flex items-center gap-2 text-sm text-om-muted">
                    统计月份
                    <select
                        value={month}
                        onChange={(event) => changeMonth(event.target.value)}
                        className="rounded border border-om-line bg-om-card px-3 py-2 text-sm text-om-ink outline-none focus:border-om-accent"
                    >
                        {monthOptions.map((option) => (
                            <option key={option.value} value={option.value}>{option.label}</option>
                        ))}
                    </select>
                </label>
            </div>

            <div className="overflow-hidden rounded-om-md border border-om-line bg-om-card">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[980px] text-left text-sm">
                        <thead>
                            <tr className="border-b border-om-line bg-om-bg text-[11px] uppercase tracking-[0.06em] text-om-faint">
                                <th className="px-4 py-3 font-semibold">供应商</th>
                                <th className="px-3 py-3 text-right font-semibold">到货批</th>
                                <th className="px-3 py-3 text-right font-semibold">受检</th>
                                <th className="px-3 py-3 text-right font-semibold">合格 / 不合格</th>
                                <th className="px-3 py-3 text-right font-semibold">合格率</th>
                                <th className="px-3 py-3 text-right font-semibold">退货</th>
                                <th className="px-3 py-3 text-right font-semibold">报废 / 拒收</th>
                                <th className="px-3 py-3 text-right font-semibold">冻结中</th>
                                <th className="px-4 py-3 font-semibold">主要不合格项</th>
                                <th className="px-4 py-3 font-semibold">最近到货</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => {
                                const noFail = row.fail === 0;
                                return (
                                    <tr key={row.supplier} className="border-b border-om-line last:border-0 align-top">
                                        <td className="px-4 py-3 font-medium text-om-ink">
                                            {row.supplier}
                                            {row.supplier === '未指定供应商' && (
                                                <span className="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800">待补 supplier</span>
                                            )}
                                        </td>
                                        <td className="px-3 py-3 text-right font-mono">{row.lots}</td>
                                        <td className="px-3 py-3 text-right font-mono">{row.inspected}</td>
                                        <td className="px-3 py-3 text-right font-mono">
                                            <span className="text-emerald-700">{row.pass}</span>
                                            <span className="text-om-faint"> / </span>
                                            <span className={row.fail > 0 ? 'text-om-blocked font-semibold' : 'text-om-faint'}>{row.fail}</span>
                                        </td>
                                        <td className="px-3 py-3 text-right font-mono">
                                            {row.pass_rate === null ? (
                                                <span className="text-om-faint">未检</span>
                                            ) : (
                                                <span className={noFail ? 'font-semibold text-emerald-700' : 'font-semibold text-om-blocked'}>{row.pass_rate}%</span>
                                            )}
                                        </td>
                                        <td className="px-3 py-3 text-right font-mono">
                                            <span className={row.returned_qty > 0 ? 'text-om-blocked font-semibold' : 'text-om-faint'}>{qty(row.returned_qty)}</span>
                                        </td>
                                        <td className="px-3 py-3 text-right font-mono">
                                            <span className={row.scrapped_qty + row.rejected_qty > 0 ? 'text-om-blocked' : 'text-om-faint'}>
                                                {qty(row.scrapped_qty)} / {qty(row.rejected_qty)}
                                            </span>
                                        </td>
                                        <td className="px-3 py-3 text-right font-mono">
                                            {row.held_lots > 0 ? (
                                                <span className="font-semibold text-amber-700">{row.held_lots} 批 / {qty(row.held_qty)}</span>
                                            ) : (
                                                <span className="text-om-faint">0</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.top_defects.length === 0 ? (
                                                <span className="text-om-faint">—</span>
                                            ) : (
                                                <div className="flex max-w-[260px] flex-wrap gap-1">
                                                    {row.top_defects.map((defect) => (
                                                        <span key={defect} className="rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-medium text-red-700">{defect}</span>
                                                    ))}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-xs text-om-muted">{row.last_received_at ? formatDateTime(row.last_received_at) : '—'}</td>
                                    </tr>
                                );
                            })}
                            {rows.length === 0 && (
                                <tr>
                                    <td colSpan={10} className="px-4 py-12 text-center text-sm text-om-muted">
                                        本期没有到货批次。换一个月份，或先完成原料入库。
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <p className="mt-3 text-xs text-om-muted">
                合格率分母只算已完成检验的批次；未检批次单列在“到货批/受检”差额中，不会被算成合格。质量冻结数量按剩余物理量统计。
            </p>
        </>
    );
}

SupplierQualityIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
