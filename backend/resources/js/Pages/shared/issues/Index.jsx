import { useCallback, useEffect, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Button, IconButton, Dropdown, TextField, StatusPill, Modal, InlineAlert, ConfirmDialog, RadioGroup } from '@openmes/ui';
import AppDatePicker from '../../../components/AppDatePicker';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import usePrompt from '../../../components/usePrompt';
import DueCountdown from '../../../components/DueCountdown';
import CustomFields from '../../../components/CustomFields';
import { customFieldInitial, customFieldProps, submitForm } from '../../../lib/customFieldForm';
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
// An action past 'in_progress' has settled: its deadline is a fact about the
// past, so the countdown stops shouting about it. Mirrors the server's
// IssueAction::OPEN_STATUSES, which is what drives the `is_overdue` flag.
const SETTLED_ACTION_STATUSES = ['done', 'verified'];

// `?issue=<id>` narrows the list to one issue — the target of the dashboard's
// "Needs action" links, as issues have no page of their own. Read-only and
// client-side: it only filters rows the page already receives.
function focusedIssueId() {
    if (typeof window === 'undefined') return null;
    const raw = new URLSearchParams(window.location.search).get('issue');
    if (!raw || !/^\d+$/.test(raw)) return null;
    const id = Number.parseInt(raw, 10);
    return id > 0 ? id : null;
}

export default function IssuesIndex() {
    const { prompt, dialog: promptDialog } = usePrompt();
    const {
        issueTypeNames = {},
        lineNames = {},
        reporterNames = {},
        workOrderNos = {},
        issueTypes = [],
        scrapReasons = [],
        reportableWorkOrders = [],
        issueCustomFields = [],
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
    const [reportOpen, setReportOpen] = useState(false);
    // Bumped after a save so the next opening starts empty; closing without
    // saving keeps what was typed (the drawer stays mounted).
    const [reportRun, setReportRun] = useState(0);
    const [focusId] = useState(focusedIssueId);
    const focusFilter = useCallback((r) => Number(r.id) === focusId, [focusId]);

    const columns = [
        { key: 'title', label: __('Issue'), className: 'font-medium text-om-ink', filter: 'text' },
        { key: 'type', label: __('Type'), className: 'text-om-muted', value: (r) => issueTypeNames[r.issue_type_id] ?? '—', render: (r) => issueTypeNames[r.issue_type_id] ?? '—' },
        { key: 'wo', label: __('Work Order'), className: 'text-om-muted', value: (r) => workOrderNos[r.work_order_id] ?? '—', render: (r) => workOrderNos[r.work_order_id] ?? '—' },
        { key: 'reporter', label: __('Reported by'), className: 'text-om-muted', value: (r) => reporterNames[r.reported_by_id] ?? '—', render: (r) => reporterNames[r.reported_by_id] ?? '—' },
        { key: 'reported_at', filter: 'date', label: __('Reported'), className: 'text-om-muted', render: (r) => (r.reported_at ? r.reported_at.slice(0, 16).replace('T', ' ') : '—') },
        {
            key: 'status', label: __('Status'),
           
            render: (r) => <span className={`text-xs px-2 py-0.5 rounded font-medium ${STATUS_STYLES[r.status] ?? 'bg-om-chip text-om-muted'}`}>{__(r.status)}</span>,
        },
        {
            key: 'disposition', label: __('Disposition'),
           
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
        onClick: () => prompt(
            { title: __('Resolution notes:'), label: __('Notes'), required: false, confirmLabel: __('Resolve'), multiline: true },
            (notes) => post(r.id, 'resolve', { resolution_notes: notes }),
        ),
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
            {focusId && (
                <div className="mb-4">
                    <InlineAlert severity="info" title={__('Showing issue #:id only', { id: focusId })}>
                        <Link href={`${base}/issues`} className="font-medium text-om-accent hover:underline">
                            {__('Show all issues')}
                        </Link>
                    </InlineAlert>
                </div>
            )}
            <ResourceTable
                shape="issues_all"
                title={__('Issues')}
                columns={columns}
                orderBy="reported_at"
                orderDir="desc"
                actions={actions}
                filterFn={focusId ? focusFilter : undefined}
                emptyText={__('No issues.')}
                onCreate={() => setReportOpen(true)}
                createLabel="Report issue"
            />
            <ReportIssueDrawer
                key={reportRun}
                open={reportOpen}
                base={base}
                lineNames={lineNames}
                workOrders={reportableWorkOrders}
                issueTypes={issueTypes}
                scrapReasons={scrapReasons}
                customFields={issueCustomFields}
                onClose={() => setReportOpen(false)}
                onSaved={() => {
                    setReportOpen(false);
                    setReportRun((n) => n + 1);
                }}
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
            {promptDialog}
        </>
    );
}

IssuesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;

// ── Report issue drawer ─────────────────────────────────────────────────────

// What the issue does to production once filed — mutually exclusive. Mirrors
// StoreManagedIssueRequest::EFFECTS.
const EFFECT_OPTIONS = [
    { value: 'none', label: 'No effect' },
    { value: 'block', label: 'Blocks the work order' },
    { value: 'scrap', label: 'Records scrap' },
];

const fieldLabelCls = 'mb-1 block text-[12.5px] font-medium text-om-ink';
const fieldErrorCls = 'mt-1 text-[11.5px] text-om-blocked';

/**
 * The operator's "Report Issue" dialog for the Issues page: the same fields,
 * plus the line + work order (an operator gets those from their station) and
 * the issue's effect, which here is chosen instead of following the type.
 */
function ReportIssueDrawer({ open, base, lineNames, workOrders, issueTypes, scrapReasons, customFields = [], onClose, onSaved }) {
    const [lineId, setLineId] = useState('');
    const form = useForm({
        work_order_id: '',
        issue_type_id: '',
        title: '',
        description: '',
        effect: 'none',
        scrap_reason_id: '',
        quantity: '',
        ...customFieldInitial(),
    });
    const { data, setData, errors, processing } = form;

    const lineOptions = Object.entries(lineNames).map(([id, name]) => ({ value: String(id), label: name }));
    const orderOptions = workOrders
        .filter((wo) => !lineId || String(wo.line_id) === lineId)
        .map((wo) => ({ value: String(wo.id), label: wo.order_no }));

    // The type's own blocking flag is the starting point; the reporter can
    // still pick another effect.
    const pickType = (id) => {
        const type = issueTypes.find((t) => String(t.id) === id);
        form.setData((prev) => ({
            ...prev,
            issue_type_id: id,
            effect: prev.effect === 'scrap' ? 'scrap' : (type?.is_blocking ? 'block' : 'none'),
        }));
    };

    const submit = () => {
        submitForm(form, 'post', `${base}/issues`, { preserveScroll: true, onSuccess: onSaved });
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            side="right"
            title={__('Report issue')}
            closeLabel={__('Close')}
            width={560}
            // A misclick on the scrim shouldn't cost a half-filled report.
            keepMounted
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>{__('Cancel')}</Button>
                    <Button variant="danger" onClick={submit} disabled={processing}>{__('Report issue')}</Button>
                </>
            }
        >
            <div className="space-y-4">
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <div className={fieldLabelCls}>{__('Line')}</div>
                        <Dropdown
                            aria-label={__('Line')}
                            className="w-full"
                            options={[{ value: '', label: __('All lines') }, ...lineOptions]}
                            value={lineId}
                            onChange={(v) => {
                                setLineId(v);
                                setData('work_order_id', '');
                            }}
                        />
                    </div>
                    <div>
                        <div className={fieldLabelCls}>
                            {__('Work Order')} <span className="text-om-blocked">*</span>
                        </div>
                        <Dropdown
                            aria-label={__('Work Order')}
                            className="w-full"
                            options={orderOptions}
                            value={String(data.work_order_id)}
                            onChange={(v) => setData('work_order_id', v)}
                            placeholder={__('— Select work order —')}
                        />
                        {errors.work_order_id && <p className={fieldErrorCls}>{errors.work_order_id}</p>}
                    </div>
                </div>

                <div>
                    <div className={fieldLabelCls}>
                        {__('Issue Type')} <span className="text-om-blocked">*</span>
                    </div>
                    <Dropdown
                        aria-label={__('Issue Type')}
                        className="w-full"
                        options={issueTypes.map((type) => ({ value: String(type.id), label: type.name }))}
                        value={String(data.issue_type_id)}
                        onChange={pickType}
                        placeholder={__('— Select type —')}
                    />
                    {errors.issue_type_id && <p className={fieldErrorCls}>{errors.issue_type_id}</p>}
                </div>

                <div>
                    <div className={fieldLabelCls}>
                        {__('Title')} <span className="text-om-blocked">*</span>
                    </div>
                    <TextField
                        aria-label={__('Title')}
                        value={data.title}
                        onChange={(v) => setData('title', v)}
                        placeholder={__('Brief summary of the issue')}
                        maxLength={255}
                        error={errors.title}
                    />
                </div>

                <div>
                    <div className={fieldLabelCls}>{__('Description')}</div>
                    <TextField
                        aria-label={__('Description')}
                        multiline
                        value={data.description}
                        onChange={(v) => setData('description', v)}
                        placeholder={__('Additional details…')}
                        maxLength={2000}
                        error={errors.description}
                    />
                </div>

                <div className="border-t border-om-line2 pt-4">
                    <div className={fieldLabelCls}>{__('Effect on production')}</div>
                    <RadioGroup
                        label={__('Effect on production')}
                        options={EFFECT_OPTIONS.map((o) => ({ value: o.value, label: __(o.label) }))}
                        value={data.effect}
                        onChange={(v) => setData('effect', v)}
                    />
                    {errors.effect && <p className={fieldErrorCls}>{errors.effect}</p>}
                    {data.effect === 'block' && (
                        <p className="mt-2 text-[11.5px] text-om-faint">
                            {__('The work order stays blocked until this issue is resolved.')}
                        </p>
                    )}
                </div>

                {data.effect === 'scrap' && (
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <div className={fieldLabelCls}>
                                {__('Scrap reason')} <span className="text-om-blocked">*</span>
                            </div>
                            <Dropdown
                                aria-label={__('Scrap reason')}
                                className="w-full"
                                options={scrapReasons.map((r) => ({ value: String(r.id), label: `${r.code} — ${r.name}` }))}
                                value={String(data.scrap_reason_id)}
                                onChange={(v) => setData('scrap_reason_id', v)}
                                placeholder={__('— Select reason —')}
                            />
                            {errors.scrap_reason_id && <p className={fieldErrorCls}>{errors.scrap_reason_id}</p>}
                        </div>
                        <div>
                            <div className={fieldLabelCls}>
                                {__('Quantity')} <span className="text-om-blocked">*</span>
                            </div>
                            <TextField
                                aria-label={__('Quantity')}
                                inputMode="decimal"
                                value={String(data.quantity)}
                                onChange={(v) => setData('quantity', v)}
                                placeholder="0"
                                error={errors.quantity}
                            />
                        </div>
                    </div>
                )}

                {customFields.length > 0 && <CustomFields {...customFieldProps(form, customFields)} />}
            </div>
        </Modal>
    );
}

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
            width={520}
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>{__('Cancel')}</Button>
                    <Button variant="primary" onClick={submit} disabled={processing}>{__('Record disposition')}</Button>
                </>
            }
        >
            <div className="space-y-4">
                <div>
                    <div className="mb-1 block text-[12.5px] font-medium text-om-ink">{__('Disposition')}</div>
                    <Dropdown
                        aria-label={__('Disposition')}
                        className="w-full"
                        options={dispositionOptions}
                        value={data.disposition}
                        onChange={(v) => setData('disposition', v)}
                    />
                    {errors.disposition && <p className="mt-1 text-[11.5px] text-om-blocked">{errors.disposition}</p>}
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <div className="mb-1 block text-[12.5px] font-medium text-om-ink">{__('Non-conforming quantity')}</div>
                        <TextField
                            aria-label={__('Non-conforming quantity')}
                            inputMode="decimal"
                            value={String(data.non_conforming_qty ?? '')}
                            onChange={(v) => setData('non_conforming_qty', v)}
                            placeholder="0"
                            error={errors.non_conforming_qty}
                        />
                    </div>
                    <div>
                        <div className="mb-1 block text-[12.5px] font-medium text-om-ink">{__('Source')}</div>
                        <Dropdown
                            aria-label={__('Source')}
                            className="w-full"
                            options={sourceOptions}
                            value={data.nc_source}
                            onChange={(v) => setData('nc_source', v)}
                        />
                        {errors.nc_source && <p className="mt-1 text-[11.5px] text-om-blocked">{errors.nc_source}</p>}
                    </div>
                </div>

                <div>
                    <div className="mb-1 block text-[12.5px] font-medium text-om-ink">{__('Root cause')}</div>
                    <TextField
                        aria-label={__('Root cause')}
                        multiline
                        value={data.root_cause ?? ''}
                        onChange={(v) => setData('root_cause', v)}
                        placeholder={__('Optional')}
                        error={errors.root_cause}
                    />
                </div>
                <div>
                    <div className="mb-1 block text-[12.5px] font-medium text-om-ink">{__('Containment action')}</div>
                    <TextField
                        aria-label={__('Containment action')}
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
    const { prompt, dialog: promptDialog } = usePrompt();
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
    const complete = (a) => prompt(
        { title: __('Completion notes (optional):'), label: __('Notes'), required: false, confirmLabel: __('Complete'), multiline: true },
        (notes) => run(() => api(`${base}/issues/actions/${a.id}/complete`, 'POST', { notes: notes || undefined })),
    );
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
                width={640}
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
                                                <span className="ml-2 text-[11.5px] text-om-faint">
                                                    {__('due')} {a.due_date}
                                                    {' · '}
                                                    {/* Was a flat "Overdue" word, which said an action
                                                        was late but never by how much — the thing that
                                                        decides which one to pick up first. A done or
                                                        cancelled action keeps its date, muted. */}
                                                    <DueCountdown
                                                        due={a.due_date}
                                                        settled={SETTLED_ACTION_STATUSES.includes(a.status)}
                                                        className="font-semibold"
                                                    />
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
                        <AppDatePicker
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
            {promptDialog}
        </>
    );
}
