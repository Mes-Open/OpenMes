import { useEffect, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Button, IconButton, Dropdown, DatePicker, TextField, StatusPill, Modal, InlineAlert, ConfirmDialog } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

const STATUS_STYLES = {
    OPEN: 'bg-om-blocked-bg text-om-blocked',
    ACKNOWLEDGED: 'bg-om-downtime-bg text-om-downtime',
    RESOLVED: 'bg-om-running-bg text-om-running',
    CLOSED: 'bg-om-line2 text-om-muted',
};

// Non-conformance disposition (#11) — badge styling + labels.
const DISPOSITION_STYLES = {
    pending: 'bg-om-downtime-bg text-om-downtime',
    scrap: 'bg-om-blocked-bg text-om-blocked',
    rework: 'bg-om-chip text-om-accent',
    return_to_supplier: 'bg-om-chip text-om-accent',
    use_as_is: 'bg-om-running-bg text-om-running',
};
const DISPOSITION_LABELS = {
    pending: 'Pending',
    scrap: 'Scrap',
    rework: 'Rework',
    return_to_supplier: 'Return to supplier',
    use_as_is: 'Use as is',
};
const NC_SOURCE_LABELS = { internal: 'Internal', external: 'External', supplier: 'Supplier' };

// Action lifecycle (open → in_progress → done → verified) mapped onto the
// design system's StatusPill tones.
const ACTION_STATUS = {
    open: { tone: 'pending', label: __('Open') },
    in_progress: { tone: 'downtime', label: __('In progress') },
    done: { tone: 'running', label: __('Done') },
    verified: { tone: 'done', label: __('Verified') },
};
const ACTION_TYPE_LABELS = { corrective: 'Corrective', preventive: 'Preventive', containment: 'Containment' };

export default function IssuesIndex() {
    const {
        issueTypeNames = {},
        lineNames = {},
        reporterNames = {},
        workOrderNos = {},
        csrf_token: csrf,
    } = usePage().props;

    const base =
        typeof window !== 'undefined' && window.location.pathname.startsWith('/admin')
            ? '/admin'
            : '/supervisor';

    const post = (id, verb, data = {}) =>
        router.post(`${base}/issues/${id}/${verb}`, data, { preserveScroll: true });

    const [actionsFor, setActionsFor] = useState(null); // issue row whose actions modal is open
    const [dispositionFor, setDispositionFor] = useState(null); // issue row whose disposition modal is open

    const columns = [
        { key: 'title', label: __('Issue'), className: 'font-medium text-om-ink' },
        { key: 'type', label: __('Type'), className: 'text-om-muted', render: (r) => issueTypeNames[r.issue_type_id] ?? '—' },
        { key: 'wo', label: __('Work Order'), className: 'text-om-muted', render: (r) => workOrderNos[r.work_order_id] ?? '—' },
        { key: 'reporter', label: __('Reported by'), className: 'text-om-muted', render: (r) => reporterNames[r.reported_by_id] ?? '—' },
        { key: 'reported_at', label: __('Reported'), className: 'text-om-muted', render: (r) => (r.reported_at ? r.reported_at.slice(0, 16).replace('T', ' ') : '—') },
        {
            key: 'status', label: __('Status'),
            render: (r) => <span className={`text-xs px-2 py-0.5 rounded font-medium ${STATUS_STYLES[r.status] ?? 'bg-om-chip text-om-muted'}`}>{r.status}</span>,
        },
        {
            key: 'disposition', label: __('Disposition'),
            filter: true,
            allLabel: __('All dispositions'),
            options: Object.keys(DISPOSITION_LABELS).map((value) => ({ value, label: __(DISPOSITION_LABELS[value]) })),
            render: (r) => (
                <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${DISPOSITION_STYLES[r.disposition] ?? 'bg-om-chip text-om-muted'}`}>
                    {__(DISPOSITION_LABELS[r.disposition] ?? r.disposition ?? 'Pending')}
                </span>
            ),
        },
    ];

    const resolveAction = (r) => ({
        label: __('Resolve'),
        onClick: () => {
            const notes = prompt(__('Resolution notes:'));
            if (notes !== null) post(r.id, 'resolve', { resolution_notes: notes });
        },
    });

    const actions = (r) => {
        const list = [
            { label: __('Disposition'), onClick: () => setDispositionFor(r) },
            { label: __('Actions'), onClick: () => setActionsFor(r) },
        ];
        const s = r.status;
        if (s === 'OPEN') {
            list.push({ label: __('Acknowledge'), onClick: () => post(r.id, 'acknowledge') }, resolveAction(r));
        } else if (s === 'ACKNOWLEDGED') {
            list.push(resolveAction(r));
        } else if (s === 'RESOLVED') {
            list.push({ label: __('Close'), onClick: () => post(r.id, 'close') });
        }
        return list;
    };

    return (
        <>
            <Head title={__('Issues')} />
            <ResourceTable
                shape="issues_all"
                title={__('Issues')}
                columns={columns}
                orderBy="reported_at"
                orderDir="desc"
                actions={actions}
                emptyText={__('No issues.')}
            />
            {dispositionFor && (
                <DispositionModal
                    issue={dispositionFor}
                    base={base}
                    onClose={() => setDispositionFor(null)}
                />
            )}
            {actionsFor && (
                <ActionsModal
                    issue={actionsFor}
                    base={base}
                    csrf={csrf}
                    users={reporterNames}
                    onClose={() => setActionsFor(null)}
                />
            )}
        </>
    );
}

IssuesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;

// ── Non-conformance disposition modal (#11) ─────────────────────────────────

function DispositionModal({ issue, base, onClose }) {
    const { data, setData, post, processing, errors } = useForm({
        disposition: issue.disposition ?? 'pending',
        non_conforming_qty: issue.non_conforming_qty ?? '',
        nc_source: issue.nc_source ?? '',
        root_cause: issue.root_cause ?? '',
        containment_action: issue.containment_action ?? '',
    });

    const submit = () => {
        post(`${base}/issues/${issue.id}/disposition`, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    const dispositionOptions = Object.keys(DISPOSITION_LABELS).map((value) => ({ value, label: __(DISPOSITION_LABELS[value]) }));
    const sourceOptions = [
        { value: '', label: __('— Source —') },
        ...Object.keys(NC_SOURCE_LABELS).map((value) => ({ value, label: __(NC_SOURCE_LABELS[value]) })),
    ];

    return (
        <Modal
            open
            onClose={onClose}
            title={__('Disposition')}
            subtitle={issue.title}
            closeLabel={__('Close')}
            className="max-w-[520px]"
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>{__('Cancel')}</Button>
                    <Button variant="primary" onClick={submit} disabled={processing}>{__('Record disposition')}</Button>
                </>
            }
        >
            <div className="space-y-4">
                <div>
                    <label className="mb-1 block text-[12.5px] font-medium text-om-ink">{__('Disposition')}</label>
                    <Dropdown
                        className="w-full"
                        options={dispositionOptions}
                        value={data.disposition}
                        onChange={(v) => setData('disposition', v)}
                    />
                    {errors.disposition && <p className="mt-1 text-[11.5px] text-om-blocked">{errors.disposition}</p>}
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="mb-1 block text-[12.5px] font-medium text-om-ink">{__('Non-conforming quantity')}</label>
                        <TextField
                            inputMode="decimal"
                            value={String(data.non_conforming_qty ?? '')}
                            onChange={(v) => setData('non_conforming_qty', v)}
                            placeholder="0"
                            error={errors.non_conforming_qty}
                        />
                    </div>
                    <div>
                        <label className="mb-1 block text-[12.5px] font-medium text-om-ink">{__('Source')}</label>
                        <Dropdown
                            className="w-full"
                            options={sourceOptions}
                            value={data.nc_source}
                            onChange={(v) => setData('nc_source', v)}
                        />
                        {errors.nc_source && <p className="mt-1 text-[11.5px] text-om-blocked">{errors.nc_source}</p>}
                    </div>
                </div>

                <div>
                    <label className="mb-1 block text-[12.5px] font-medium text-om-ink">{__('Root cause')}</label>
                    <TextField
                        multiline
                        value={data.root_cause ?? ''}
                        onChange={(v) => setData('root_cause', v)}
                        placeholder={__('Optional')}
                        error={errors.root_cause}
                    />
                </div>
                <div>
                    <label className="mb-1 block text-[12.5px] font-medium text-om-ink">{__('Containment action')}</label>
                    <TextField
                        multiline
                        value={data.containment_action ?? ''}
                        onChange={(v) => setData('containment_action', v)}
                        placeholder={__('Optional')}
                        error={errors.containment_action}
                    />
                </div>
            </div>
        </Modal>
    );
}

// ── Corrective / preventive actions modal ───────────────────────────────────

function ActionsModal({ issue, base, csrf, users, onClose }) {
    const [actions, setActions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [form, setForm] = useState({ type: 'corrective', title: '', assigned_to_id: '', due_date: '' });
    const [confirmDelete, setConfirmDelete] = useState(null); // action pending deletion

    const api = async (url, method = 'GET', body) => {
        const res = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            credentials: 'same-origin',
            body: body ? JSON.stringify(body) : undefined,
        });
        const json = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(json.message || __('Request failed'));
        return json;
    };

    const load = () => {
        setLoading(true);
        api(`${base}/issues/${issue.id}/actions`)
            .then((d) => setActions(d.actions ?? []))
            .catch((e) => setError(e.message))
            .finally(() => setLoading(false));
    };
    useEffect(load, []);

    const run = async (fn) => {
        setError(null);
        try {
            const d = await fn();
            setActions(d.actions ?? []);
        } catch (e) { setError(e.message); }
    };

    const add = (e) => {
        e.preventDefault();
        if (!form.title.trim()) return;
        run(() => api(`${base}/issues/${issue.id}/actions`, 'POST', {
            type: form.type,
            title: form.title.trim(),
            assigned_to_id: form.assigned_to_id || null,
            due_date: form.due_date || null,
        })).then(() => setForm({ type: 'corrective', title: '', assigned_to_id: '', due_date: '' }));
    };

    const start = (a) => run(() => api(`${base}/issues/actions/${a.id}/start`, 'POST'));
    const complete = (a) => {
        const notes = prompt(__('Completion notes (optional):')) ?? undefined;
        run(() => api(`${base}/issues/actions/${a.id}/complete`, 'POST', { notes }));
    };
    const verify = (a) => run(() => api(`${base}/issues/actions/${a.id}/verify`, 'POST'));
    const remove = (a) => run(() => api(`${base}/issues/actions/${a.id}`, 'DELETE'));

    const typeOptions = [
        { value: 'corrective', label: __('Corrective') },
        { value: 'preventive', label: __('Preventive') },
        { value: 'containment', label: __('Containment') },
    ];
    const assigneeOptions = [
        { value: '', label: __('— Assignee —') },
        ...Object.entries(users).map(([id, name]) => ({ value: id, label: name })),
    ];

    return (
        <>
            <Modal
                open
                onClose={onClose}
                title={__('Corrective / preventive actions')}
                subtitle={issue.title}
                closeLabel={__('Close')}
                className="max-w-[640px]"
            >
                <div className="space-y-4">
                    {error && (
                        <InlineAlert severity="error" title={__('Something went wrong')}>
                            {error}
                        </InlineAlert>
                    )}

                    {loading ? (
                        <p className="py-4 text-[12.5px] text-om-faint">{__('Loading…')}</p>
                    ) : actions.length === 0 ? (
                        <p className="py-3 text-[12.5px] text-om-faint">
                            {__('No actions yet. The issue can only be closed once all actions are verified.')}
                        </p>
                    ) : (
                        <ul className="max-h-72 space-y-2 overflow-y-auto">
                            {actions.map((a) => {
                                const st = ACTION_STATUS[a.status] ?? { tone: 'pending', label: a.status };
                                return (
                                    <li
                                        key={a.id}
                                        className="flex items-center gap-3 rounded-om border border-om-line bg-om-bg px-3 py-3"
                                    >
                                        <span className="font-mono text-[9.5px] uppercase tracking-[0.06em] rounded-[20px] bg-om-chip px-[10px] py-1 text-om-muted">
                                            {__(ACTION_TYPE_LABELS[a.type] ?? a.type)}
                                        </span>
                                        <span className="flex-1 text-[13px] font-medium text-om-ink">
                                            {a.title}
                                            {a.assigned_to && <span className="ml-2 text-[11.5px] text-om-faint">→ {a.assigned_to}</span>}
                                            {a.due_date && (
                                                <span className={`ml-2 text-[11.5px] ${a.is_overdue ? 'font-semibold text-om-blocked' : 'text-om-faint'}`}>
                                                    {__('due')} {a.due_date}{a.is_overdue ? ` · ${__('Overdue')}` : ''}
                                                </span>
                                            )}
                                        </span>
                                        <StatusPill status={st.tone} label={st.label} pulse={false} />
                                        {a.status === 'open' && (
                                            <Button variant="secondary" className="px-3 py-1.5 text-[12px]" onClick={() => start(a)}>
                                                {__('Start')}
                                            </Button>
                                        )}
                                        {(a.status === 'open' || a.status === 'in_progress') && (
                                            <Button variant="primary" className="px-3 py-1.5 text-[12px]" onClick={() => complete(a)}>
                                                {__('Complete')}
                                            </Button>
                                        )}
                                        {a.status === 'done' && (
                                            <Button variant="accent" className="px-3 py-1.5 text-[12px]" onClick={() => verify(a)}>
                                                {__('Verify')}
                                            </Button>
                                        )}
                                        <IconButton variant="danger" aria-label={__('Delete')} onClick={() => setConfirmDelete(a)}>
                                            ×
                                        </IconButton>
                                    </li>
                                );
                            })}
                        </ul>
                    )}

                    <form onSubmit={add} className="flex flex-wrap items-end gap-2 border-t border-om-line2 pt-4">
                        <Dropdown
                            className="min-w-[9rem]"
                            options={typeOptions}
                            value={form.type}
                            onChange={(v) => setForm({ ...form, type: v })}
                        />
                        <TextField
                            className="min-w-[12rem] flex-1"
                            value={form.title}
                            onChange={(v) => setForm({ ...form, title: v })}
                            placeholder={__('Action title')}
                            maxLength={255}
                            required
                        />
                        <Dropdown
                            className="min-w-[9rem]"
                            options={assigneeOptions}
                            value={form.assigned_to_id}
                            onChange={(v) => setForm({ ...form, assigned_to_id: v })}
                        />
                        <DatePicker
                            className="min-w-[10rem]"
                            value={form.due_date}
                            onChange={(v) => setForm({ ...form, due_date: v || '' })}
                            placeholder={__('Due date')}
                        />
                        <Button type="submit" variant="primary">{__('Add')}</Button>
                    </form>
                </div>
            </Modal>

            <ConfirmDialog
                open={!!confirmDelete}
                onClose={() => setConfirmDelete(null)}
                onConfirm={() => {
                    const a = confirmDelete;
                    setConfirmDelete(null);
                    if (a) remove(a);
                }}
                title={__('Delete this action?')}
                confirmLabel={__('Delete')}
                cancelLabel={__('Cancel')}
            >
                {confirmDelete?.title}
            </ConfirmDialog>
        </>
    );
}
