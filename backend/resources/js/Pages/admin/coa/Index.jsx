import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import { formatDateTime, formatNumber } from '../../../lib/i18n';

const COA_STATUS = {
    DRAFT: { label: 'COA 草稿', className: 'bg-om-chip text-om-muted' },
    QA_REVIEW: { label: 'QA 复核中', className: 'bg-om-accent-bg text-om-accent' },
    APPROVED: { label: 'COA 已批准', className: 'bg-om-accent-bg text-om-accent' },
    ISSUED: { label: 'COA 已签发', className: 'bg-om-running-bg text-om-running' },
    VOID: { label: 'COA 已作废', className: 'bg-om-blocked-bg text-om-blocked' },
};

const BATCH_STATUS = {
    DONE: '已完成',
    IN_PROGRESS: '生产中',
    PENDING: '待开始',
    CANCELLED: '已取消',
};

export default function CoaIndex() {
    const { batches, summary } = usePage().props;
    const rows = batches?.data ?? [];

    return (
        <>
            <Head title="COA 与批次放行" />
            <div className="max-w-[1180px] mx-auto space-y-5">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p className="text-xs font-bold tracking-[0.16em] text-om-faint uppercase">Quality release</p>
                        <h1 className="mt-1 text-2xl font-bold text-om-ink">COA 与批次放行</h1>
                        <p className="mt-1 text-sm text-om-muted">
                            先过放行门，再签发 COA。批次、检验、包装、问题单、版本与签名都留痕。
                        </p>
                    </div>
                    <Link href="/admin/work-orders" className="btn-touch btn-secondary text-sm">
                        工单列表
                    </Link>
                </div>

                <div className="grid gap-3 sm:grid-cols-3">
                    <SummaryCard label="待放行批次" value={summary?.pending_release ?? 0} hint="状态 DONE 且未记录放行" />
                    <SummaryCard label="COA 待处理" value={summary?.in_review ?? 0} hint="复核中 + 已批准待签发" />
                    <SummaryCard label="已签发" value={summary?.issued ?? 0} hint="已冻结版本并记录签名" />
                </div>

                <div className="overflow-hidden rounded-om-sm bg-om-card shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-om-line2 px-5 py-4">
                        <h2 className="text-base font-bold text-om-ink">最近批次</h2>
                        <span className="text-xs text-om-faint">共 {batches?.total ?? rows.length} 个批次</span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[960px] text-sm">
                            <thead>
                                <tr className="border-b border-om-line2 bg-om-chip/40 text-left text-xs font-bold tracking-wide text-om-muted">
                                    <th className="px-5 py-3">批次 / 工单</th>
                                    <th className="px-3 py-3">产品 / 客户</th>
                                    <th className="px-3 py-3">批次状态</th>
                                    <th className="px-3 py-3">质量检查</th>
                                    <th className="px-3 py-3">放行门</th>
                                    <th className="px-3 py-3">COA</th>
                                    <th className="px-5 py-3 text-right">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 && (
                                    <tr>
                                        <td colSpan={7} className="px-5 py-12 text-center text-sm text-om-faint">
                                            还没有可放行的批次。先完成一个工单和成品检验。
                                        </td>
                                    </tr>
                                )}
                                {rows.map((row) => (
                                    <tr key={row.id} className="border-b border-om-line2 last:border-0 hover:bg-om-chip/30">
                                        <td className="px-5 py-4">
                                            <Link href={`/admin/coa/${row.id}`} className="font-bold text-om-ink hover:text-om-accent">
                                                {row.lot_number ?? `B${row.batch_number}`}
                                            </Link>
                                            <div className="mt-1 font-mono text-xs text-om-faint">{row.order_no ?? '—'}</div>
                                        </td>
                                        <td className="px-3 py-4">
                                            <div className="font-medium text-om-ink">{row.product ?? '—'}</div>
                                            <div className="mt-1 text-xs text-om-muted">{row.customer ?? '—'}</div>
                                        </td>
                                        <td className="px-3 py-4">
                                            <div className="font-medium text-om-ink">{BATCH_STATUS[row.status] ?? row.status}</div>
                                            <div className="mt-1 text-xs text-om-muted">
                                                {row.released_at ? `已放行 ${formatDateTime(row.released_at)}` : '未放行'}
                                            </div>
                                        </td>
                                        <td className="px-3 py-4">
                                            {row.latest_check ? (
                                                <div>
                                                    <span className={`inline-block rounded px-2 py-0.5 text-xs font-bold ${row.latest_check.all_passed ? 'bg-om-running-bg text-om-running' : 'bg-om-blocked-bg text-om-blocked'}`}>
                                                        {row.latest_check.all_passed ? '合格' : '存在不合格'}
                                                    </span>
                                                    <div className="mt-1 text-xs text-om-muted">{formatDateTime(row.latest_check.checked_at)}</div>
                                                </div>
                                            ) : (
                                                <span className="text-xs text-om-faint">未检验</span>
                                            )}
                                        </td>
                                        <td className="px-3 py-4">
                                            <GateMini gate={row.release_gate} />
                                            <div className="mt-1 text-xs text-om-muted">
                                                产出 {formatNumber(row.produced_qty)} {row.unit_of_measure || 'kg'}
                                            </div>
                                        </td>
                                        <td className="px-3 py-4">
                                            {row.coa ? (
                                                <div>
                                                    <span className={`inline-block rounded px-2 py-0.5 text-xs font-bold ${(COA_STATUS[row.coa.status] ?? COA_STATUS.DRAFT).className}`}>
                                                        {(COA_STATUS[row.coa.status] ?? COA_STATUS.DRAFT).label}
                                                    </span>
                                                    <div className="mt-1 font-mono text-[11px] text-om-faint">{row.coa.number} · V{row.coa.version}</div>
                                                </div>
                                            ) : (
                                                <span className="text-xs text-om-faint">尚未生成</span>
                                            )}
                                        </td>
                                        <td className="px-5 py-4 text-right">
                                            <Link href={`/admin/coa/${row.id}`} className="btn-touch btn-secondary text-xs">
                                                打开
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {batches?.links?.length > 3 && (
                        <div className="flex flex-wrap gap-1 border-t border-om-line2 px-5 py-3">
                            {batches.links.map((link, index) => (
                                link.url ? (
                                    <Link
                                        key={index}
                                        href={link.url}
                                        className={`rounded px-3 py-1.5 text-xs font-medium ${link.active ? 'bg-om-accent text-white' : 'bg-om-chip text-om-muted hover:text-om-ink'}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span key={index} className="rounded px-3 py-1.5 text-xs text-om-faint" dangerouslySetInnerHTML={{ __html: link.label }} />
                                )
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

function SummaryCard({ label, value, hint }) {
    return (
        <div className="rounded-om-sm border border-om-line bg-om-card px-5 py-4 shadow-sm">
            <div className="text-xs font-bold tracking-wide text-om-muted">{label}</div>
            <div className="mt-2 font-mono text-3xl font-bold text-om-ink">{value}</div>
            <div className="mt-1 text-xs text-om-faint">{hint}</div>
        </div>
    );
}

function GateMini({ gate }) {
    if (!gate) return <span className="text-xs text-om-faint">—</span>;
    const tone = gate.passed ? 'text-om-running' : 'text-om-blocked';
    return (
        <div>
            <div className={`font-mono text-sm font-bold ${tone}`}>{gate.passed_count}/{gate.total}</div>
            <div className="mt-1 h-1.5 w-24 overflow-hidden rounded-full bg-om-chip">
                <div
                    className={`h-full rounded-full ${gate.passed ? 'bg-om-running' : 'bg-om-blocked'}`}
                    style={{ width: `${Math.round((gate.passed_count / Math.max(gate.total, 1)) * 100)}%` }}
                />
            </div>
        </div>
    );
}

CoaIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
