import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';

const STATUS_STYLES = {
    OPEN: 'bg-om-blocked-bg text-om-blocked',
    ACKNOWLEDGED: 'bg-om-downtime-bg text-om-downtime',
    RESOLVED: 'bg-om-running-bg text-om-running',
    CLOSED: 'bg-om-line2 text-om-muted',
};

export default function IssuesIndex() {
    const {
        issueTypeNames = {},
        lineNames = {},
        reporterNames = {},
        workOrderNos = {},
    } = usePage().props;

    const base =
        typeof window !== 'undefined' && window.location.pathname.startsWith('/admin')
            ? '/admin'
            : '/supervisor';

    const post = (id, verb, data = {}) =>
        router.post(`${base}/issues/${id}/${verb}`, data, { preserveScroll: true });

    const columns = [
        { key: 'title', label: 'Issue', className: 'font-medium text-om-ink' },
        { key: 'type', label: 'Type', className: 'text-om-muted', render: (r) => issueTypeNames[r.issue_type_id] ?? '—' },
        { key: 'wo', label: 'Work Order', className: 'text-om-muted', render: (r) => workOrderNos[r.work_order_id] ?? '—' },
        { key: 'reporter', label: 'Reported by', className: 'text-om-muted', render: (r) => reporterNames[r.reported_by_id] ?? '—' },
        { key: 'reported_at', label: 'Reported', className: 'text-om-muted', render: (r) => (r.reported_at ? r.reported_at.slice(0, 16).replace('T', ' ') : '—') },
        {
            key: 'status', label: 'Status',
            render: (r) => <span className={`text-xs px-2 py-0.5 rounded font-medium ${STATUS_STYLES[r.status] ?? 'bg-om-chip text-om-muted'}`}>{r.status}</span>,
        },
    ];

    const resolveAction = (r) => ({
        label: 'Resolve',
        onClick: () => {
            const notes = prompt('Resolution notes:');
            if (notes !== null) post(r.id, 'resolve', { resolution_notes: notes });
        },
    });

    const actions = (r) => {
        const s = r.status;
        if (s === 'OPEN') {
            return [
                { label: 'Acknowledge', onClick: () => post(r.id, 'acknowledge') },
                resolveAction(r),
            ];
        }
        if (s === 'ACKNOWLEDGED') {
            return [resolveAction(r)];
        }
        if (s === 'RESOLVED') {
            return [{ label: 'Close', onClick: () => post(r.id, 'close') }];
        }
        return [];
    };

    return (
        <>
            <Head title="Issues" />
            <ResourceTable
                shape="issues_all"
                title="Issues"
                columns={columns}
                orderBy="reported_at"
                orderDir="desc"
                actions={actions}
                emptyText="No issues."
            />
        </>
    );
}

IssuesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
