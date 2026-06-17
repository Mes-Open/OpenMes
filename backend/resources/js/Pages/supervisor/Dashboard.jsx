// Geist White restyle: light-only v1 — om-* tokens + @openmes/ui (props shape, line switch and stats untouched).
import { Head, router, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { Dropdown, StatusPill } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../layouts/AppLayout';
import { __ } from '../../lib/i18n';

// Issue status → Geist White pill state (labels stay the translated app statuses).
const ISSUE_PILL_STATUS = {
    OPEN: 'blocked',
    ACKNOWLEDGED: 'downtime',
    RESOLVED: 'running',
    CLOSED: 'done',
};

export default function SupervisorDashboard() {
    const { lines = [], selectedLineId, stats = {}, throughput = {}, issueStats = {}, recentIssues = [] } = usePage().props;

    const changeLine = (id) => router.get('/supervisor/dashboard', id ? { line_id: id } : {}, { preserveState: false });

    const issueColumns = useMemo(() => [
        {
            id: 'title',
            accessorKey: 'title',
            header: __('Issue'),
            cell: ({ row }) => <span className="font-medium text-om-ink">{row.original.title}</span>,
        },
        {
            id: 'type',
            accessorFn: (r) => r.type ?? '—',
            header: __('Type'),
            cell: ({ row }) => <span className="text-om-muted">{row.original.type ?? '—'}</span>,
        },
        {
            id: 'work_order',
            accessorFn: (r) => r.work_order ?? '—',
            header: __('WO'),
            cell: ({ row }) => <span className="font-mono text-[12px] text-om-muted">{row.original.work_order ?? '—'}</span>,
        },
        {
            id: 'reported_at',
            accessorFn: (r) => r.reported_at ?? '—',
            header: __('Reported'),
            cell: ({ row }) => <span className="text-om-faint">{row.original.reported_at ?? '—'}</span>,
        },
        {
            id: 'status',
            accessorKey: 'status',
            header: __('Status'),
            cell: ({ row }) => <StatusPill status={ISSUE_PILL_STATUS[row.original.status] ?? 'pending'} label={__(row.original.status)} />,
        },
    ], []);

    return (
        <>
            <Head title={__('Supervisor Dashboard')} />
            <div className="max-w-7xl mx-auto space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-[26px] font-semibold tracking-[-0.02em] text-om-ink">{__('Supervisor Dashboard')}</h1>
                    <Dropdown
                        options={lines.map((l) => ({ value: String(l.id), label: l.name }))}
                        value={selectedLineId == null ? '' : String(selectedLineId)}
                        onChange={(v) => changeLine(v)}
                        className="min-w-[180px]"
                    />
                </div>

                <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
                    <Kpi label={__('Total WOs')} value={stats.total_work_orders} />
                    <Kpi label={__('Active')} value={stats.active_work_orders} accent="blue" />
                    <Kpi label={__('Completed')} value={stats.completed_work_orders} accent="green" />
                    <Kpi label={__('Blocked')} value={stats.blocked_work_orders} accent="red" />
                    <Kpi label={__('Open Issues')} value={stats.open_issues} accent="yellow" />
                    <Kpi label={__('Blocking')} value={stats.blocking_issues} accent="red" />
                </div>

                <div className="grid lg:grid-cols-2 gap-6">
                    <Card title={__('Throughput (30 days) · avg :avg', { avg: throughput.average ?? 0 })}>
                        <BarList labels={throughput.labels} values={throughput.values} unit="" />
                    </Card>
                    <Card title={__('Issues by type (30 days)')}>
                        <BarList labels={issueStats.by_type?.labels} values={issueStats.by_type?.values} unit="" color="bg-om-downtime" />
                    </Card>
                </div>

                <Card title={__('Recent issues')}>
                    <DataTable
                        data={recentIssues}
                        columns={issueColumns}
                        searchable={false}
                        columnToggle={false}
                        paginated={false}
                        emptyLabel={`${__('No issues.')} 🎉`}
                    />
                </Card>
            </div>
        </>
    );
}

SupervisorDashboard.layout = (page) => <AppLayout>{page}</AppLayout>;

function Kpi({ label, value, accent }) {
    // Geist White state tints: accent orange (active), running green, blocked red, downtime amber.
    const colors = { blue: 'text-om-accent', green: 'text-om-running', red: 'text-om-blocked', yellow: 'text-om-downtime' };
    return (
        <div className="bg-om-card border border-om-line rounded-om p-5">
            <p className="mb-2 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{label}</p>
            <p className={`font-mono text-[27px] font-medium leading-none tracking-[-0.02em] ${colors[accent] ?? 'text-om-ink'}`}>{value ?? 0}</p>
        </div>
    );
}

function Card({ title, children }) {
    return (
        <div className="bg-om-card border border-om-line rounded-om p-5">
            <h2 className="text-[14px] font-semibold text-om-ink mb-3">{title}</h2>
            {children}
        </div>
    );
}

function BarList({ labels = [], values = [], unit = '', color = 'bg-om-accent' }) {
    if (!labels?.length) return <p className="text-om-faint text-sm">{__('No data.')}</p>;
    const max = Math.max(...values, 1);
    return (
        <div className="space-y-[15px]">
            {labels.map((label, i) => (
                <div key={i} className="flex items-center gap-2 text-xs">
                    <span className="w-20 shrink-0 text-[12.5px] text-om-muted truncate text-right">{label}</span>
                    <div className="flex-1 bg-om-chip rounded-[20px] h-[7px] overflow-hidden">
                        <div className={`${color} h-[7px] rounded-[20px]`} style={{ width: `${(values[i] / max) * 100}%` }} />
                    </div>
                    <span className="w-12 shrink-0 font-mono text-[12px] text-om-muted">{values[i]}{unit}</span>
                </div>
            ))}
        </div>
    );
}
