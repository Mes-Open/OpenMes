import { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import CustomFieldsDisplay from '../../../components/CustomFieldsDisplay';

const WORK_ORDER_STATUS_LABELS = {
    PENDING: 'Pending',
    ACCEPTED: 'Accepted',
    IN_PROGRESS: 'In Progress',
    BLOCKED: 'Blocked',
    PAUSED: 'Paused',
    DONE: 'Done',
    REJECTED: 'Rejected',
    CANCELLED: 'Cancelled',
};

const WORK_ORDER_STATUS_CLASSES = {
    PENDING: 'bg-om-downtime-bg text-om-downtime',
    IN_PROGRESS: 'bg-om-chip text-om-accent',
    COMPLETED: 'bg-om-running-bg text-om-running',
    BLOCKED: 'bg-om-blocked-bg text-om-blocked',
};

function Icon({ d, className = 'w-5 h-5' }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={d} />
        </svg>
    );
}

// ── Line Statuses ─────────────────────────────────────────────────────────────

function LineStatusesCard({ line, lineStatuses }) {
    const form = useForm({ color: '#F59E0B', name: '', sort_order: 10 });

    const submit = (e) => {
        e.preventDefault();
        form.post(`/admin/lines/${line.id}/statuses`, { preserveScroll: true, onSuccess: () => form.reset() });
    };

    const deleteStatus = (statusId) => {
        if (confirm('Delete this status?')) {
            router.delete(`/admin/line-statuses/${statusId}`, { preserveScroll: true });
        }
    };

    return (
        <div className="bg-om-card rounded-om-sm shadow-sm p-6 mb-6">
            <div className="flex items-center justify-between mb-4">
                <div>
                    <h2 className="text-xl font-bold text-om-ink">Line Statuses</h2>
                    <p className="text-sm text-om-muted mt-0.5">
                        Kanban statuses available for work orders on this line. Global statuses are shown in gray.
                    </p>
                </div>
                <Link href="/admin/line-statuses" className="text-sm text-om-accent hover:underline">
                    Manage global statuses →
                </Link>
            </div>

            <div className="flex flex-wrap gap-2 mb-4">
                {lineStatuses.length === 0 ? (
                    <p className="text-sm text-om-muted">
                        No statuses yet. Add one below or{' '}
                        <Link href="/admin/line-statuses" className="text-om-accent hover:underline">
                            manage global statuses
                        </Link>
                        .
                    </p>
                ) : (
                    lineStatuses.map((status) => (
                        <div
                            key={status.id}
                            className="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium text-white"
                            style={{ backgroundColor: status.color }}
                        >
                            {status.name}
                            {status.is_default && <span className="text-xs opacity-75">(default)</span>}
                            {status.line_id === null ? (
                                <span className="text-xs opacity-60">global</span>
                            ) : (
                                <button
                                    onClick={() => deleteStatus(status.id)}
                                    className="ml-1 opacity-75 hover:opacity-100"
                                    title="Delete"
                                >
                                    <Icon d="M6 18L18 6M6 6l12 12" className="w-3.5 h-3.5" />
                                </button>
                            )}
                        </div>
                    ))
                )}
            </div>

            <form onSubmit={submit} className="border-t border-om-line2 pt-4 flex items-end gap-3 flex-wrap">
                <div className="flex flex-col gap-1">
                    <label className="text-xs text-om-muted">Color</label>
                    <input
                        type="color"
                        value={form.data.color}
                        onChange={(e) => form.setData('color', e.target.value)}
                        className="w-10 h-10 rounded cursor-pointer border border-om-line p-0.5"
                        required
                    />
                </div>
                <div className="flex flex-col gap-1 flex-1 min-w-[160px]">
                    <label className="text-xs text-om-muted">Status name (line-specific)</label>
                    <input
                        type="text"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="e.g. Waiting for parts"
                        className="form-input py-1.5 text-sm"
                        maxLength={100}
                        required
                    />
                </div>
                <div className="flex flex-col gap-1 w-20">
                    <label className="text-xs text-om-muted">Order</label>
                    <input
                        type="number"
                        value={form.data.sort_order}
                        onChange={(e) => form.setData('sort_order', e.target.value)}
                        min={0}
                        className="form-input py-1.5 text-sm"
                    />
                </div>
                <button
                    type="submit"
                    disabled={form.processing}
                    className="px-3 py-2 border border-om-line rounded-om-sm text-sm font-medium text-om-muted hover:bg-om-bg disabled:opacity-50"
                >
                    <Icon d="M12 4v16m8-8H4" className="w-4 h-4 inline-block mr-1" />
                    Add to this line
                </button>
            </form>
        </div>
    );
}

// ── Product Types ─────────────────────────────────────────────────────────────

function ProductTypesCard({ line, allProductTypes, assignedTypeIds: initialAssigned }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ product_type_ids: initialAssigned });

    const toggleType = (id) => {
        const current = form.data.product_type_ids;
        const next = current.includes(id) ? current.filter((x) => x !== id) : [...current, id];
        form.setData('product_type_ids', next);
    };

    const submit = (e) => {
        e.preventDefault();
        form.post(`/admin/lines/${line.id}/product-types/sync`, { preserveScroll: true });
    };

    const assignedSet = new Set(initialAssigned);

    return (
        <div className="bg-om-card rounded-om-sm shadow-sm p-6 mb-6">
            <div className="flex items-center justify-between mb-4">
                <div>
                    <h2 className="text-xl font-bold text-om-ink">Assigned Product Types</h2>
                    <p className="text-sm text-om-muted mt-0.5">Product types that can be produced on this line.</p>
                </div>
            </div>

            <div className="flex flex-wrap gap-2 mb-4">
                {line.product_types.length === 0 ? (
                    <p className="text-sm text-om-faint">No product types assigned — all types are allowed.</p>
                ) : (
                    line.product_types.map((pt) => (
                        <span
                            key={pt.id}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-om-chip border border-om-line text-om-accent text-sm font-medium rounded-om-sm"
                        >
                            <span className="font-mono text-xs text-om-accent">{pt.code}</span>
                            {pt.name}
                        </span>
                    ))
                )}
            </div>

            <div className="border-t border-om-line2 pt-4">
                <button
                    type="button"
                    onClick={() => setOpen((o) => !o)}
                    className="text-sm text-om-accent hover:text-om-accent font-medium flex items-center gap-1"
                >
                    <Icon d="M12 4v16m8-8H4" className="w-4 h-4" />
                    <span>{open ? 'Hide selector' : 'Change assignment'}</span>
                </button>

                {open && (
                    <form onSubmit={submit} className="mt-3">
                        {allProductTypes.length === 0 ? (
                            <p className="text-sm text-om-muted">No active product types defined yet.</p>
                        ) : (
                            <>
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 mb-3">
                                    {allProductTypes.map((pt) => {
                                        const checked = form.data.product_type_ids.includes(pt.id);
                                        return (
                                            <label
                                                key={pt.id}
                                                className={`flex items-center gap-2 p-2.5 rounded-om-sm border cursor-pointer transition-colors ${
                                                    checked
                                                        ? 'border-blue-400 bg-om-chip'
                                                        : 'border-om-line2 hover:border-om-line'
                                                }`}
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={checked}
                                                    onChange={() => toggleType(pt.id)}
                                                    className="rounded border-om-line text-om-accent"
                                                />
                                                <div className="min-w-0">
                                                    <p className="text-sm font-medium text-om-ink truncate">{pt.name}</p>
                                                    <p className="text-xs text-om-faint font-mono">{pt.code}</p>
                                                </div>
                                            </label>
                                        );
                                    })}
                                </div>
                                <p className="text-xs text-om-faint mb-3">
                                    Leave all unchecked to allow all product types on this line.
                                </p>
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="bg-om-ink text-white px-4 py-2 rounded-om-sm text-sm font-medium hover:bg-black disabled:opacity-50"
                                >
                                    {form.processing ? 'Saving…' : 'Save Assignment'}
                                </button>
                            </>
                        )}
                    </form>
                )}
            </div>
        </div>
    );
}

// ── Workstations summary ──────────────────────────────────────────────────────

function WorkstationsCard({ line, effectiveWorkstations }) {
    return (
        <div className="bg-om-card rounded-om-sm shadow-sm p-6 mb-6">
            <div className="flex items-center justify-between mb-4">
                <div>
                    <h2 className="text-xl font-bold text-om-ink">Workstations</h2>
                    {effectiveWorkstations.length === 0 || (effectiveWorkstations.length === 1 && effectiveWorkstations[0].is_line_itself) ? (
                        <p className="text-sm text-om-downtime mt-0.5 font-medium">
                            No workstations configured — line itself acts as a single workstation.
                        </p>
                    ) : (
                        <p className="text-sm text-om-muted mt-0.5">
                            {line.workstations_count} workstation(s) on this line.
                        </p>
                    )}
                </div>
                <Link
                    href={`/admin/lines/${line.id}/workstations`}
                    className="px-3 py-2 border border-om-line rounded-om-sm text-sm font-medium text-om-muted hover:bg-om-bg"
                >
                    Manage
                </Link>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                {effectiveWorkstations.map((ws) => (
                    <div
                        key={ws.id}
                        className={`flex items-center gap-3 p-3 rounded-om-sm border ${
                            ws.is_line_itself
                                ? 'border-om-line bg-om-downtime-bg'
                                : 'border-om-line2 bg-om-panel'
                        }`}
                    >
                        <div className={`${ws.is_line_itself ? 'bg-om-downtime-bg' : 'bg-om-running-bg'} rounded-full p-2 flex-shrink-0`}>
                            <Icon
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                                className={`w-5 h-5 ${ws.is_line_itself ? 'text-om-downtime' : 'text-om-running'}`}
                            />
                        </div>
                        <div className="min-w-0">
                            <p className="text-sm font-semibold text-om-ink truncate">{ws.name}</p>
                            <p className="text-xs text-om-faint font-mono">{ws.code}</p>
                            {ws.is_line_itself && (
                                <p className="text-xs text-om-downtime">virtual (line = workstation)</p>
                            )}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

// ── Operators ─────────────────────────────────────────────────────────────────

function OperatorsCard({ line, availableOperators }) {
    const form = useForm({ user_id: '' });

    const submit = (e) => {
        e.preventDefault();
        form.post(`/admin/lines/${line.id}/assign-operator`, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const unassign = (userId, userName) => {
        if (confirm(`Remove ${userName} from this line?`)) {
            router.delete(`/admin/lines/${line.id}/operators/${userId}`, { preserveScroll: true });
        }
    };

    return (
        <div className="bg-om-card rounded-om-sm shadow-sm p-6">
            <div className="flex items-center justify-between mb-4">
                <h2 className="text-xl font-bold text-om-ink">Assigned Operators</h2>
            </div>

            {line.users.length > 0 ? (
                <div className="space-y-2 mb-4">
                    {line.users.map((user) => (
                        <div key={user.id} className="flex items-center justify-between p-3 bg-om-panel rounded-om-sm">
                            <div className="flex items-center gap-3">
                                <div className="w-10 h-10 rounded-full bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center text-white font-bold">
                                    {user.name.charAt(0).toUpperCase()}
                                </div>
                                <div>
                                    <p className="font-medium text-om-ink">{user.name}</p>
                                    <p className="text-sm text-om-muted">{user.username}</p>
                                </div>
                            </div>
                            <button
                                onClick={() => unassign(user.id, user.name)}
                                className="text-om-blocked hover:text-om-blocked p-2"
                                title="Remove operator"
                            >
                                <Icon d="M6 18L18 6M6 6l12 12" />
                            </button>
                        </div>
                    ))}
                </div>
            ) : (
                <div className="text-center py-8 bg-om-panel rounded-om-sm mb-4">
                    <Icon
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                        className="mx-auto h-12 w-12 text-om-faint mb-2"
                    />
                    <p className="text-om-muted">No operators assigned yet</p>
                </div>
            )}

            {availableOperators.length > 0 ? (
                <form onSubmit={submit} className="border-t border-om-line2 pt-4">
                    <label className="block text-sm font-medium text-om-muted mb-1">Assign New Operator</label>
                    <div className="flex gap-2">
                        <select
                            value={form.data.user_id}
                            onChange={(e) => form.setData('user_id', e.target.value)}
                            className="form-input flex-1"
                            required
                        >
                            <option value="">Select an operator...</option>
                            {availableOperators.map((op) => (
                                <option key={op.id} value={op.id}>
                                    {op.name} ({op.username})
                                </option>
                            ))}
                        </select>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="bg-om-ink text-white px-4 py-2 rounded-om-sm text-sm font-medium hover:bg-black disabled:opacity-50 flex items-center gap-2"
                        >
                            <Icon d="M12 4v16m8-8H4" />
                            Assign
                        </button>
                    </div>
                    {form.errors.user_id && <p className="mt-1 text-xs text-om-blocked">{form.errors.user_id}</p>}
                </form>
            ) : (
                <div className="border-t border-om-line2 pt-4">
                    <p className="text-sm text-om-muted text-center">
                        All available operators are already assigned to this line.
                    </p>
                </div>
            )}
        </div>
    );
}

// ── Recent Work Orders ────────────────────────────────────────────────────────

function WorkOrdersCard({ line, workOrders }) {
    return (
        <div className="bg-om-card rounded-om-sm shadow-sm p-6">
            <h2 className="text-xl font-bold text-om-ink mb-4">Recent Work Orders</h2>

            {workOrders.length > 0 ? (
                <>
                    <div className="space-y-2">
                        {workOrders.map((wo) => (
                            <div key={wo.id} className="p-3 bg-om-panel rounded-om-sm">
                                <div className="flex items-start justify-between">
                                    <div className="flex-1">
                                        <p className="font-medium text-om-ink">{wo.work_order_number}</p>
                                        <p className="text-sm text-om-muted">{wo.product_name}</p>
                                        <p className="text-xs text-om-muted mt-1">
                                            Quantity: {wo.planned_qty} | Created: {wo.created_at?.slice(0, 16).replace('T', ' ')}
                                        </p>
                                    </div>
                                    <span
                                        className={`px-2 py-1 text-xs font-medium rounded-full ${
                                            WORK_ORDER_STATUS_CLASSES[wo.status] ?? 'bg-om-chip text-om-ink'
                                        }`}
                                    >
                                        {WORK_ORDER_STATUS_LABELS[wo.status] ?? wo.status}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>
                    {line.work_orders_count > 10 && (
                        <p className="text-sm text-om-muted text-center mt-4">
                            Showing 10 most recent of {line.work_orders_count} total work orders
                        </p>
                    )}
                </>
            ) : (
                <div className="text-center py-8 bg-om-panel rounded-om-sm">
                    <Icon
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"
                        className="mx-auto h-12 w-12 text-om-faint mb-2"
                    />
                    <p className="text-om-muted">No work orders yet</p>
                </div>
            )}
        </div>
    );
}

// ── Default Operator View ─────────────────────────────────────────────────────

function DefaultViewCard({ line }) {
    const handleChange = (value) => {
        router.post(
            `/admin/lines/${line.id}/default-view`,
            { default_operator_view: value },
            { preserveScroll: true },
        );
    };

    const current = line.default_operator_view ?? 'queue';

    return (
        <div className="bg-om-card rounded-om-sm shadow-sm p-6 mt-6">
            <h2 className="text-xl font-bold text-om-ink mb-1">Default Operator View</h2>
            <p className="text-sm text-om-muted mb-4">
                Choose which view operators see by default when they select this line.
            </p>
            <div className="flex gap-3">
                {[
                    {
                        value: 'queue',
                        label: 'Queue',
                        desc: 'Standard work order list with status, batches, priority and actions.',
                    },
                    {
                        value: 'workstation',
                        label: 'Workstation',
                        desc: 'Flat production table with quantities, Z1/Z2 shift inputs and inline entry.',
                    },
                ].map((opt) => (
                    <label
                        key={opt.value}
                        className={`flex-1 flex items-center gap-3 p-4 rounded-om-sm border-2 cursor-pointer transition-colors ${
                            current === opt.value
                                ? 'border-om-accent bg-om-chip'
                                : 'border-om-line2 hover:border-om-line'
                        }`}
                    >
                        <input
                            type="radio"
                            name="default_operator_view"
                            value={opt.value}
                            checked={current === opt.value}
                            onChange={() => handleChange(opt.value)}
                            className="text-om-accent focus:ring-om-accent"
                        />
                        <div>
                            <span className="text-sm font-semibold text-om-ink">{opt.label}</span>
                            <p className="text-xs text-om-muted mt-0.5">{opt.desc}</p>
                        </div>
                    </label>
                ))}
            </div>
        </div>
    );
}

// ── View Template ─────────────────────────────────────────────────────────────

function ViewTemplateCard({ line, allViewTemplates }) {
    const form = useForm({ view_template_id: line.view_template_id != null ? String(line.view_template_id) : '' });

    const submit = (e) => {
        e.preventDefault();
        form.post(`/admin/lines/${line.id}/view-template`, { preserveScroll: true });
    };

    return (
        <div className="bg-om-card rounded-om-sm shadow-sm p-6 mt-6">
            <h2 className="text-xl font-bold text-om-ink mb-1">Workstation View</h2>
            <p className="text-sm text-om-muted mb-4">
                Select a view template that defines which columns operators see in the Workstation view for this line.
            </p>
            <form onSubmit={submit} className="flex items-end gap-3">
                <div className="flex-1">
                    <label className="block text-sm font-medium text-om-muted mb-1">View Template</label>
                    <select
                        value={form.data.view_template_id}
                        onChange={(e) => form.setData('view_template_id', e.target.value)}
                        className="form-input w-full"
                    >
                        <option value="">— Default (no custom columns) —</option>
                        {allViewTemplates.map((tpl) => (
                            <option key={tpl.id} value={String(tpl.id)}>
                                {tpl.name} ({tpl.columns_count} columns)
                            </option>
                        ))}
                    </select>
                </div>
                <button
                    type="submit"
                    disabled={form.processing}
                    className="bg-om-ink text-white px-4 py-2 rounded-om-sm text-sm font-medium hover:bg-black disabled:opacity-50"
                >
                    {form.processing ? 'Saving…' : 'Save'}
                </button>
            </form>
            {allViewTemplates.length === 0 && (
                <p className="text-xs text-om-faint mt-3">
                    No templates created yet.{' '}
                    <Link href="/admin/view-templates/create" className="text-om-accent hover:underline">
                        Create one
                    </Link>
                    .
                </p>
            )}
        </div>
    );
}

// ── View Columns ──────────────────────────────────────────────────────────────

function ViewColumnsCard({ line, viewColumns: initialColumns }) {
    const [columns, setColumns] = useState(
        initialColumns.map((c) => ({ label: c.label, key: c.key, source: c.source })),
    );
    const [newLabel, setNewLabel] = useState('');
    const [newKey, setNewKey] = useState('');
    const [newSource, setNewSource] = useState('extra_data');
    const [processing, setProcessing] = useState(false);

    const add = () => {
        if (!newLabel || !newKey) return;
        setColumns((cols) => [...cols, { label: newLabel, key: newKey, source: newSource }]);
        setNewLabel('');
        setNewKey('');
        setNewSource('extra_data');
    };

    const remove = (i) => setColumns((cols) => cols.filter((_, idx) => idx !== i));

    const moveUp = (i) => {
        if (i === 0) return;
        setColumns((cols) => {
            const next = [...cols];
            [next[i - 1], next[i]] = [next[i], next[i - 1]];
            return next;
        });
    };

    const moveDown = (i) => {
        setColumns((cols) => {
            if (i >= cols.length - 1) return cols;
            const next = [...cols];
            [next[i], next[i + 1]] = [next[i + 1], next[i]];
            return next;
        });
    };

    const submit = (e) => {
        e.preventDefault();
        setProcessing(true);
        router.post(
            `/admin/lines/${line.id}/view-columns`,
            { columns },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <div className="bg-om-card rounded-om-sm shadow-sm p-6 mt-4">
            <h2 className="text-xl font-bold text-om-ink mb-1">Workstation View Columns</h2>
            <p className="text-sm text-om-muted mb-4">
                Configure which columns operators see in the Workstation view for this line.{' '}
                Columns with source <strong>extra_data</strong> pull values from the work order&apos;s imported data.{' '}
                Columns with source <strong>field</strong> pull from work order fields (order_no, description, due_date, priority).
            </p>

            <form onSubmit={submit}>
                {/* Existing columns */}
                <div className="space-y-2 mb-4">
                    {columns.length === 0 ? (
                        <div className="text-sm text-om-faint text-center py-4">
                            No custom columns configured. Default view will be shown.
                        </div>
                    ) : (
                        columns.map((col, i) => (
                            <div key={i} className="flex items-center gap-2 bg-om-panel rounded-om-sm px-3 py-2">
                                <span className="text-om-faint text-sm font-mono w-6 text-center">{i + 1}</span>
                                <span className="flex-1 text-sm font-medium text-om-ink">{col.label}</span>
                                <code className="text-xs text-om-muted bg-om-line2 px-1.5 py-0.5 rounded">
                                    {col.source}.{col.key}
                                </code>
                                <button
                                    type="button"
                                    onClick={() => moveUp(i)}
                                    className="p-1 text-om-faint hover:text-om-ink"
                                    title="Move up"
                                >
                                    <Icon d="M5 15l7-7 7 7" className="w-4 h-4" />
                                </button>
                                <button
                                    type="button"
                                    onClick={() => moveDown(i)}
                                    className="p-1 text-om-faint hover:text-om-ink"
                                    title="Move down"
                                >
                                    <Icon d="M19 9l-7 7-7-7" className="w-4 h-4" />
                                </button>
                                <button
                                    type="button"
                                    onClick={() => remove(i)}
                                    className="p-1 text-red-400 hover:text-om-blocked"
                                    title="Remove"
                                >
                                    <Icon d="M6 18L18 6M6 6l12 12" className="w-4 h-4" />
                                </button>
                            </div>
                        ))
                    )}
                </div>

                {/* Add new column */}
                <div className="flex flex-wrap gap-2 items-end border-t border-om-line2 pt-4 mb-4">
                    <div className="flex-1 min-w-[140px]">
                        <label className="text-xs text-om-muted block mb-1">Column Label</label>
                        <input
                            type="text"
                            value={newLabel}
                            onChange={(e) => setNewLabel(e.target.value)}
                            placeholder="e.g. Material"
                            className="form-input w-full text-sm"
                        />
                    </div>
                    <div className="flex-1 min-w-[140px]">
                        <label className="text-xs text-om-muted block mb-1">Data Key</label>
                        <input
                            type="text"
                            value={newKey}
                            onChange={(e) => setNewKey(e.target.value)}
                            placeholder="e.g. material"
                            className="form-input w-full text-sm"
                        />
                    </div>
                    <div className="w-36">
                        <label className="text-xs text-om-muted block mb-1">Source</label>
                        <select
                            value={newSource}
                            onChange={(e) => setNewSource(e.target.value)}
                            className="form-input w-full text-sm"
                        >
                            <option value="extra_data">extra_data</option>
                            <option value="field">field</option>
                        </select>
                    </div>
                    <button
                        type="button"
                        onClick={add}
                        disabled={!newLabel || !newKey}
                        className="px-3 py-2 border border-om-line rounded-om-sm text-sm font-medium text-om-muted hover:bg-om-bg disabled:opacity-40"
                    >
                        + Add
                    </button>
                </div>

                <div className="flex justify-end">
                    <button
                        type="submit"
                        disabled={processing}
                        className="bg-om-ink text-white px-4 py-2 rounded-om-sm text-sm font-medium hover:bg-black disabled:opacity-50"
                    >
                        {processing ? 'Saving…' : 'Save View Columns'}
                    </button>
                </div>
            </form>
        </div>
    );
}

// ── Main Show page ────────────────────────────────────────────────────────────

export default function LineShow() {
    const {
        line,
        workOrders = [],
        availableOperators = [],
        lineStatuses = [],
        allProductTypes = [],
        assignedTypeIds = [],
        viewColumns = [],
        allViewTemplates = [],
        effectiveWorkstations = [],
        customFields = [],
    } = usePage().props;

    const handleToggleActive = () => {
        router.post(`/admin/lines/${line.id}/toggle-active`, {}, { preserveScroll: true });
    };

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`${line.name} — Configure`} />

            {/* Header */}
            <div className="mb-6">
                <Link
                    href="/admin/lines"
                    className="text-om-accent hover:text-om-accent flex items-center gap-2 mb-4 text-sm"
                >
                    <Icon d="M15 19l-7-7 7-7" />
                    Back to Production Lines
                </Link>
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <h1 className="text-3xl font-bold text-om-ink">{line.name}</h1>
                        {line.is_active ? (
                            <span className="px-3 py-1 bg-om-running-bg text-om-running rounded-full text-sm font-medium">
                                Active
                            </span>
                        ) : (
                            <span className="px-3 py-1 bg-om-chip text-om-muted rounded-full text-sm font-medium">
                                Inactive
                            </span>
                        )}
                    </div>
                    <div className="flex gap-2">
                        <Link
                            href={`/admin/lines/${line.id}/edit`}
                            className="px-4 py-2 border border-om-line rounded-om-sm text-sm font-medium text-om-muted hover:bg-om-bg flex items-center gap-2"
                        >
                            <Icon d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            Edit Line
                        </Link>
                        <button
                            onClick={handleToggleActive}
                            className={`px-4 py-2 rounded-om-sm text-sm font-medium flex items-center gap-2 ${
                                line.is_active
                                    ? 'border border-om-line text-om-muted hover:bg-om-bg'
                                    : 'bg-om-ink text-white hover:bg-black'
                            }`}
                        >
                            {line.is_active ? (
                                <>
                                    <Icon d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                    Deactivate
                                </>
                            ) : (
                                <>
                                    <Icon d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    Activate
                                </>
                            )}
                        </button>
                    </div>
                </div>
                <p className="text-sm text-om-muted font-mono mt-1">{line.code}</p>
                {line.description && <p className="text-om-muted mt-2">{line.description}</p>}
            </div>

            {/* Stats Cards */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div className="bg-om-card rounded-om-sm shadow-sm p-5">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm text-om-muted">Work Orders</p>
                            <p className="text-3xl font-bold text-om-accent">{line.work_orders_count}</p>
                        </div>
                        <div className="bg-om-chip rounded-full p-3">
                            <Icon
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                                className="w-8 h-8 text-om-accent"
                            />
                        </div>
                    </div>
                </div>

                <Link
                    href={`/admin/lines/${line.id}/workstations`}
                    className="bg-om-card rounded-om-sm shadow-sm p-5 hover:shadow-md transition-shadow block"
                >
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm text-om-muted">Workstations</p>
                            <p className="text-3xl font-bold text-om-running">{line.workstations_count}</p>
                        </div>
                        <div className="bg-om-running-bg rounded-full p-3">
                            <Icon
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                                className="w-8 h-8 text-om-running"
                            />
                        </div>
                    </div>
                </Link>

                <div className="bg-om-card rounded-om-sm shadow-sm p-5">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm text-om-muted">Assigned Operators</p>
                            <p className="text-3xl font-bold text-om-ink">{line.users_count}</p>
                        </div>
                        <div className="bg-om-chip rounded-full p-3">
                            <Icon
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
                                className="w-8 h-8 text-om-ink"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div className="mb-6">
                <CustomFieldsDisplay definitions={customFields} values={line.custom_fields ?? {}} />
            </div>

            <LineStatusesCard line={line} lineStatuses={lineStatuses} />
            <ProductTypesCard line={line} allProductTypes={allProductTypes} assignedTypeIds={assignedTypeIds} />
            <WorkstationsCard line={line} effectiveWorkstations={effectiveWorkstations} />

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <OperatorsCard line={line} availableOperators={availableOperators} />
                <WorkOrdersCard line={line} workOrders={workOrders} />
            </div>

            <DefaultViewCard line={line} />
            <ViewTemplateCard line={line} allViewTemplates={allViewTemplates} />
            <ViewColumnsCard line={line} viewColumns={viewColumns} />
        </div>
    );
}

LineShow.layout = (page) => <AppLayout>{page}</AppLayout>;
