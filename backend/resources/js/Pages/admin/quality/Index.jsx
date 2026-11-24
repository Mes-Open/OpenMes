import { Head, Link, usePage } from '@inertiajs/react';
import React from 'react';
import AppLayout from '../../../layouts/AppLayout';
import { formatDateTime, formatNumber } from '../../../lib/i18n';

const BATCH_STATUS = {
    PENDING: { label: '待开始', className: 'bg-om-chip text-om-muted' },
    IN_PROGRESS: { label: '生产中', className: 'bg-om-accepted-bg text-om-accepted' },
    DONE: { label: '已完成', className: 'bg-om-done-bg text-om-done' },
    CANCELLED: { label: '已取消', className: 'bg-om-blocked-bg text-om-blocked' },
};

const COA_STATUS = {
    DRAFT: { label: '草稿', className: 'bg-om-chip text-om-muted' },
    QA_REVIEW: { label: 'QA 复核中', className: 'bg-om-accent-bg text-om-accent' },
    APPROVED: { label: '已批准', className: 'bg-om-accent-bg text-om-accent' },
    ISSUED: { label: '已签发', className: 'bg-om-running-bg text-om-running' },
    VOID: { label: '已作废', className: 'bg-om-blocked-bg text-om-blocked' },
};

const ISSUE_STATUS = {
    OPEN: { label: '未关闭', className: 'bg-om-blocked-bg text-om-blocked' },
    ACKNOWLEDGED: { label: '已确认', className: 'bg-om-downtime-bg text-om-downtime' },
    RESOLVED: { label: '已解决', className: 'bg-om-running-bg text-om-running' },
    CLOSED: { label: '已关闭', className: 'bg-om-chip text-om-muted' },
};

export default function QualityIndex() {
    const { summary = {}, queue = [], blockingIssues = [], recentIssued = [] } = usePage().props;

    return (
        <>
            <Head title="质量指挥台" />
            <div className="mx-auto w-full max-w-[1180px] space-y-4 px-4 pb-10 sm:px-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0">
                        <h1 className="text-2xl font-bold tracking-tight text-om-ink">质量指挥台</h1>
                        <p className="mt-1 max-w-[680px] text-sm leading-6 text-om-muted">
                            先看今天卡在哪，再进批次控制台补证据、签字和签发。队列按下一步动作排序，不用逐页翻批次。
                        </p>
                        <p className="mt-2 text-xs text-om-faint">
                            今日已签发 {summary.issued_today ?? 0} 份 · 队列 {queue.length} 个批次 · {summary.blocking_issues ?? 0} 个阻断问题
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Link href="/admin/coa" className="btn-touch btn-primary text-sm">COA 工作台</Link>
                        <Link href="/admin/issues" className="btn-touch btn-secondary text-sm">问题列表</Link>
                    </div>
                </div>

                <SummaryStrip summary={summary} />

                <div className="grid gap-4 xl:grid-cols-[minmax(0,1.6fr)_minmax(300px,0.9fr)]">
                    <Panel
                        title="质量队列"
                        subtitle="按下一步动作排序；点批次号进入批次控制台"
                        action={<span className="font-mono text-xs font-bold text-om-muted">{queue.length} 个批次</span>}
                    >
                        {queue.length === 0 ? (
                            <Empty text="今天没有需要处理的批次。" />
                        ) : (
                            <div className="divide-y divide-om-line2">
                                {queue.map((row) => <QueueRow key={row.id} row={row} />)}
                            </div>
                        )}
                    </Panel>

                    <div className="min-w-0 space-y-4 xl:sticky xl:top-4 xl:self-start">
                        <Panel
                            title="阻断问题"
                            subtitle="未关闭且阻断工单或来料"
                            action={<Link href="/admin/issues" className="text-[11px] font-bold text-om-accent hover:underline">全部 →</Link>}
                        >
                            {blockingIssues.length === 0 ? (
                                <Empty text="没有未关闭的阻断问题。" />
                            ) : (
                                <div className="divide-y divide-om-line2">
                                    {blockingIssues.map((issue) => <IssueRow key={issue.id} issue={issue} />)}
                                </div>
                            )}
                        </Panel>

                        <Panel
                            title="最近签发"
                            subtitle="已冻结版本并写入签名"
                            action={<Link href="/admin/coa" className="text-[11px] font-bold text-om-accent hover:underline">查看 →</Link>}
                        >
                            {recentIssued.length === 0 ? (
                                <Empty text="还没有已签发的 COA。" />
                            ) : (
                                <div className="divide-y divide-om-line2">
                                    {recentIssued.map((document) => <IssuedRow key={document.id} document={document} />)}
                                </div>
                            )}
                        </Panel>
                    </div>
                </div>
            </div>
        </>
    );
}

function SummaryStrip({ summary }) {
    const metrics = [
        { label: '待放行', value: summary.pending_release ?? 0, hint: '已完成、未签字', href: '/admin/coa', tone: 'text-om-downtime' },
        { label: 'QA 复核', value: summary.qa_review ?? 0, hint: '等 QA 决定', href: '/admin/coa', tone: 'text-om-accent' },
        { label: '待签发', value: summary.approved ?? 0, hint: '已批准、未签发', href: '/admin/coa', tone: 'text-om-ink' },
        { label: '阻断问题', value: summary.blocking_issues ?? 0, hint: '未关闭', href: '/admin/issues', tone: 'text-om-blocked' },
    ];

    return (
        <div className="grid overflow-hidden rounded-om-sm border border-om-line bg-om-card sm:grid-cols-2 xl:grid-cols-4">
            {metrics.map((metric, index) => (
                <Link
                    key={metric.label}
                    href={metric.href}
                    className={`group min-w-0 px-4 py-3 transition-colors hover:bg-om-chip/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-om-accent/40 ${index < metrics.length - 1 ? 'border-b border-om-line2 sm:border-b-0 sm:border-r' : ''}`}
                >
                    <div className="flex items-center justify-between gap-2">
                        <span className="text-[11px] font-bold tracking-wide text-om-faint">{metric.label}</span>
                        <svg viewBox="0 0 20 20" aria-hidden="true" className="h-3 w-3 text-om-faint transition-colors group-hover:text-om-accent" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round">
                            <path d="M4 10h11M11 5l5 5-5 5" />
                        </svg>
                    </div>
                    <div className={`mt-1 font-mono text-xl font-bold ${metric.value > 0 ? metric.tone : 'text-om-ink'}`}>{metric.value}</div>
                    <div className="mt-0.5 text-[11px] text-om-muted">{metric.hint}</div>
                </Link>
            ))}
        </div>
    );
}

function QueueRow({ row }) {
    const action = row.next_action ?? {};
    const release = row.release_gate ?? {};
    const issueGate = row.issue_gate ?? {};
    const coa = row.coa;

    return (
        <div className="px-4 py-4 transition-colors hover:bg-om-chip/20">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <Link href={`/admin/coa/${row.id}`} className="truncate font-mono text-sm font-bold text-om-ink hover:text-om-accent">
                            {row.lot_number ?? `B${row.batch_number}`}
                        </Link>
                        <BatchPill status={row.status} />
                        {coa ? <CoaPill status={coa.status} /> : <span className="rounded bg-om-chip px-2 py-0.5 text-[11px] font-bold text-om-faint">COA 未生成</span>}
                    </div>
                    <div className="mt-1 truncate text-xs text-om-muted">
                        {row.product ?? '—'} · {row.customer ?? '—'} · {row.order_no ?? '—'}
                    </div>
                </div>
                <Link
                    href={action.href ?? `/admin/coa/${row.id}`}
                    className={`btn-touch shrink-0 text-xs ${action.tone === 'primary' ? 'btn-primary' : 'btn-secondary'}`}
                >
                    {action.label ?? '打开'}
                </Link>
            </div>

            <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <GateMetric label="放行门" passed={release.passed} passedCount={release.passed_count} total={release.total} />
                <GateMetric label="签发门" passed={issueGate.passed} passedCount={issueGate.passed_count} total={issueGate.total} />
                <div className="min-w-0 rounded border border-om-line2 bg-om-bg px-2.5 py-2">
                    <div className="text-[10px] font-bold text-om-faint">COA 版本</div>
                    <div className="mt-0.5 font-mono text-sm font-bold text-om-ink">{coa ? `V${String(coa.version).padStart(2, '0')}` : '—'}</div>
                    <div className="mt-0.5 text-[10px] text-om-muted">{coa ? (COA_STATUS[coa.status]?.label ?? coa.status) : '未生成'}</div>
                </div>
                <div className="min-w-0 rounded border border-om-line2 bg-om-bg px-2.5 py-2">
                    <div className="text-[10px] font-bold text-om-faint">产出</div>
                    <div className="mt-0.5 truncate font-mono text-sm font-bold text-om-ink">{formatNumber(row.produced_qty)} {row.unit_of_measure}</div>
                    <div className="mt-0.5 text-[10px] text-om-muted">{row.completed_at ? '生产完成' : '未完成'}</div>
                </div>
            </div>

            <div className={`mt-3 flex items-start gap-2 rounded-om-sm px-3 py-2 text-xs ${action.tone === 'warning' ? 'bg-om-downtime-bg text-om-downtime' : 'bg-om-chip/60 text-om-muted'}`}>
                <span className={`mt-1 h-1.5 w-1.5 shrink-0 rounded-full ${action.tone === 'warning' ? 'bg-om-downtime' : action.tone === 'primary' ? 'bg-om-accent' : 'bg-om-faint'}`} />
                <span className="min-w-0 break-words">{action.hint ?? '打开批次控制台查看证据。'}</span>
            </div>
        </div>
    );
}

function GateMetric({ label, passed, passedCount = 0, total = 0 }) {
    return (
        <div className="min-w-0 rounded border border-om-line2 bg-om-bg px-2.5 py-2">
            <div className="text-[10px] font-bold text-om-faint">{label}</div>
            <div className={`mt-0.5 font-mono text-sm font-bold ${passed ? 'text-om-running' : 'text-om-blocked'}`}>
                {passedCount}/{total}
            </div>
            <div className="mt-0.5 h-1 overflow-hidden rounded-full bg-om-chip">
                <div
                    className={`h-full rounded-full ${passed ? 'bg-om-running' : 'bg-om-blocked'}`}
                    style={{ width: `${Math.round((passedCount / Math.max(total, 1)) * 100)}%` }}
                />
            </div>
        </div>
    );
}

function IssueRow({ issue }) {
    return (
        <div className="px-4 py-3">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <IssuePill status={issue.status} />
                        <span className="text-[11px] text-om-faint">{issue.type ?? '未分类'}</span>
                    </div>
                    <div className="mt-1 line-clamp-2 text-sm font-semibold text-om-ink" title={issue.title}>{issue.title}</div>
                    <div className="mt-1 text-[11px] text-om-muted">
                        {issue.source}{issue.order_no ? ` · ${issue.order_no}` : ''}
                        {issue.assigned_to ? ` · ${issue.assigned_to}` : ' · 未指派'}
                    </div>
                    <div className="mt-0.5 font-mono text-[10px] text-om-faint">{issue.reported_at ? formatDateTime(issue.reported_at) : '—'}</div>
                </div>
                <Link href={issue.href ?? '/admin/issues'} className="shrink-0 text-[11px] font-bold text-om-accent hover:underline">处置 →</Link>
            </div>
        </div>
    );
}

function IssuedRow({ document }) {
    return (
        <div className="px-4 py-3">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="truncate font-mono text-sm font-bold text-om-ink" title={document.coa_number}>{document.coa_number}</div>
                    <div className="mt-1 truncate text-xs text-om-muted">
                        {document.lot_number ?? '—'} · {document.product ?? '—'} · {document.customer ?? '—'}
                    </div>
                </div>
                <div className="shrink-0 text-right">
                    <div className="font-mono text-[10px] text-om-faint">V{String(document.version).padStart(2, '0')}</div>
                    <div className="mt-0.5 font-mono text-[10px] text-om-muted">{document.issued_at ? formatDateTime(document.issued_at) : '—'}</div>
                    {document.batch_id && <Link href={`/admin/coa/${document.batch_id}`} className="mt-1 inline-block text-[11px] font-bold text-om-accent hover:underline">打开 →</Link>}
                </div>
            </div>
        </div>
    );
}

function Panel({ title, subtitle, action, children }) {
    return (
        <div className="overflow-hidden rounded-om-sm border border-om-line bg-om-card">
            <div className="flex flex-wrap items-start justify-between gap-2 border-b border-om-line2 px-4 py-3">
                <div>
                    <h2 className="text-sm font-bold text-om-ink">{title}</h2>
                    {subtitle && <p className="mt-0.5 text-xs text-om-muted">{subtitle}</p>}
                </div>
                {action}
            </div>
            {children}
        </div>
    );
}

function Empty({ text }) {
    return <p className="px-4 py-8 text-center text-sm text-om-faint">{text}</p>;
}

function BatchPill({ status }) {
    const item = BATCH_STATUS[status] ?? BATCH_STATUS.PENDING;
    return <span className={`inline-block rounded px-2 py-0.5 text-[11px] font-bold ${item.className}`}>{item.label}</span>;
}

function CoaPill({ status }) {
    const item = COA_STATUS[status] ?? COA_STATUS.DRAFT;
    return <span className={`inline-block rounded px-2 py-0.5 text-[11px] font-bold ${item.className}`}>{item.label}</span>;
}

function IssuePill({ status }) {
    const item = ISSUE_STATUS[status] ?? ISSUE_STATUS.OPEN;
    return <span className={`inline-block rounded px-2 py-0.5 text-[11px] font-bold ${item.className}`}>{item.label}</span>;
}

QualityIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
