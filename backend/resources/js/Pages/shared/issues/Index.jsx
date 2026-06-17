import { useEffect, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';

const STATUS_STYLES = {
    OPEN: 'bg-red-100 text-red-800',
    ACKNOWLEDGED: 'bg-yellow-100 text-yellow-800',
    RESOLVED: 'bg-green-100 text-green-800',
    CLOSED: 'bg-gray-200 text-gray-600',
};

const ACTION_STATUS_STYLES = {
    open: 'bg-gray-100 text-gray-700',
    in_progress: 'bg-blue-100 text-blue-800',
    done: 'bg-amber-100 text-amber-800',
    verified: 'bg-green-100 text-green-800',
};

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

    const columns = [
        { key: 'title', label: 'Issue', className: 'font-medium text-gray-800' },
        { key: 'type', label: 'Type', className: 'text-gray-600', render: (r) => issueTypeNames[r.issue_type_id] ?? '—' },
        { key: 'wo', label: 'Work Order', className: 'text-gray-600', render: (r) => workOrderNos[r.work_order_id] ?? '—' },
        { key: 'reporter', label: 'Reported by', className: 'text-gray-600', render: (r) => reporterNames[r.reported_by_id] ?? '—' },
        { key: 'reported_at', label: 'Reported', className: 'text-gray-500', render: (r) => (r.reported_at ? r.reported_at.slice(0, 16).replace('T', ' ') : '—') },
        {
            key: 'status', label: 'Status',
            render: (r) => <span className={`text-xs px-2 py-0.5 rounded font-medium ${STATUS_STYLES[r.status] ?? 'bg-gray-100 text-gray-700'}`}>{r.status}</span>,
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
        const list = [{ label: 'Actions', onClick: () => setActionsFor(r) }];
        const s = r.status;
        if (s === 'OPEN') {
            list.push({ label: 'Acknowledge', onClick: () => post(r.id, 'acknowledge') }, resolveAction(r));
        } else if (s === 'ACKNOWLEDGED') {
            list.push(resolveAction(r));
        } else if (s === 'RESOLVED') {
            list.push({ label: 'Close', onClick: () => post(r.id, 'close') });
        }
        return list;
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

// ── Corrective / preventive actions modal ───────────────────────────────────

function ActionsModal({ issue, base, csrf, users, onClose }) {
    const [actions, setActions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [form, setForm] = useState({ type: 'corrective', title: '', assigned_to_id: '', due_date: '' });

    const api = async (url, method = 'GET', body) => {
        const res = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            credentials: 'same-origin',
            body: body ? JSON.stringify(body) : undefined,
        });
        const json = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(json.message || 'Request failed');
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
        const notes = prompt('Completion notes (optional):') ?? undefined;
        run(() => api(`${base}/issues/actions/${a.id}/complete`, 'POST', { notes }));
    };
    const verify = (a) => run(() => api(`${base}/issues/actions/${a.id}/verify`, 'POST'));
    const remove = (a) => { if (confirm('Delete this action?')) run(() => api(`${base}/issues/actions/${a.id}`, 'DELETE')); };

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="fixed inset-0 bg-black/50" onClick={onClose} />
            <div className="flex min-h-full items-center justify-center p-4">
                <div className="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-2xl w-full p-6" onClick={(e) => e.stopPropagation()}>
                    <div className="flex justify-between items-start mb-4">
                        <div>
                            <h3 className="text-xl font-bold text-gray-800 dark:text-gray-100">Corrective / preventive actions</h3>
                            <p className="text-sm text-gray-500">{issue.title}</p>
                        </div>
                        <button onClick={onClose} className="text-gray-400 hover:text-gray-600 text-2xl leading-none">×</button>
                    </div>

                    {error && <div className="mb-3 p-2 rounded bg-red-50 border border-red-200 text-red-700 text-sm">{error}</div>}

                    {loading ? (
                        <p className="text-gray-400 text-sm py-4">Loading…</p>
                    ) : actions.length === 0 ? (
                        <p className="text-gray-400 text-sm py-3">No actions yet. The issue can only be closed once all actions are verified.</p>
                    ) : (
                        <ul className="space-y-2 mb-4 max-h-72 overflow-y-auto">
                            {actions.map((a) => (
                                <li key={a.id} className="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-800/50">
                                    <span className="text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded bg-purple-100 text-purple-700">{a.type}</span>
                                    <span className="flex-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                        {a.title}
                                        {a.assigned_to && <span className="ml-2 text-xs text-gray-400">→ {a.assigned_to}</span>}
                                        {a.due_date && <span className="ml-2 text-xs text-gray-400">due {a.due_date}</span>}
                                    </span>
                                    <span className={`text-xs px-2 py-0.5 rounded font-medium ${ACTION_STATUS_STYLES[a.status] ?? 'bg-gray-100'}`}>{a.status}</span>
                                    {a.status === 'open' && <button onClick={() => start(a)} className="text-xs px-2 py-1 rounded bg-blue-600 text-white">Start</button>}
                                    {(a.status === 'open' || a.status === 'in_progress') && <button onClick={() => complete(a)} className="text-xs px-2 py-1 rounded bg-amber-600 text-white">Complete</button>}
                                    {a.status === 'done' && <button onClick={() => verify(a)} className="text-xs px-2 py-1 rounded bg-green-600 text-white">Verify</button>}
                                    <button onClick={() => remove(a)} className="text-xs px-2 py-1 rounded bg-gray-200 text-gray-600">Delete</button>
                                </li>
                            ))}
                        </ul>
                    )}

                    <form onSubmit={add} className="flex flex-wrap items-end gap-2 border-t border-gray-200 dark:border-gray-700 pt-4">
                        <select value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })} className="form-input text-sm">
                            <option value="corrective">Corrective</option>
                            <option value="preventive">Preventive</option>
                        </select>
                        <input value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} placeholder="Action title" className="form-input text-sm flex-1 min-w-[12rem]" maxLength={255} required />
                        <select value={form.assigned_to_id} onChange={(e) => setForm({ ...form, assigned_to_id: e.target.value })} className="form-input text-sm">
                            <option value="">— Assignee —</option>
                            {Object.entries(users).map(([id, name]) => <option key={id} value={id}>{name}</option>)}
                        </select>
                        <input type="date" value={form.due_date} onChange={(e) => setForm({ ...form, due_date: e.target.value })} className="form-input text-sm" />
                        <button type="submit" className="btn-touch btn-primary text-sm">Add</button>
                    </form>
                </div>
            </div>
        </div>
    );
}
