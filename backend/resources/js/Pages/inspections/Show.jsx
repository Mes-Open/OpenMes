import { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../layouts/AppLayout';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function statusBadge(status) {
    const map = {
        pass: 'bg-green-100 text-green-700',
        conditional_pass: 'bg-yellow-100 text-yellow-700',
        fail: 'bg-red-100 text-red-700',
        pending: 'bg-gray-100 text-gray-600',
    };
    return map[status] ?? 'bg-gray-100 text-gray-600';
}

const DISPOSITION_OPTIONS = [
    { value: 'accept', label: 'Accept', desc: 'Accept — pass to production' },
    { value: 'accept_with_deviation', label: 'Accept with deviation', desc: 'Accept with deviation — minor issue, documented' },
    { value: 'rework', label: 'Rework', desc: 'Rework — fix and re-inspect' },
    { value: 'quarantine', label: 'Quarantine', desc: 'Quarantine — hold pending decision' },
    { value: 'scrap', label: 'Scrap', desc: 'Scrap — discard' },
    { value: 'return_to_supplier', label: 'Return to supplier', desc: 'Return to supplier' },
    { value: 'reject', label: 'Reject', desc: 'Reject (no further action)' },
];

function dispositionColorClass(disposition) {
    const map = {
        accept: 'bg-green-100 text-green-700',
        accept_with_deviation: 'bg-green-100 text-green-800',
        rework: 'bg-amber-100 text-amber-700',
        quarantine: 'bg-blue-100 text-blue-700',
        scrap: 'bg-red-100 text-red-700',
        reject: 'bg-red-100 text-red-800',
        return_to_supplier: 'bg-purple-100 text-purple-700',
    };
    return map[disposition] ?? 'bg-gray-100 text-gray-700';
}

function fmtDateTime(str) {
    if (!str) return '—';
    return new Date(str).toLocaleString(undefined, {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

function fmtNum(n, decimals = 2) {
    if (n == null) return '—';
    return Number(n).toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

// ---------------------------------------------------------------------------
// Disposition section
// ---------------------------------------------------------------------------

function DispositionSection({ inspection }) {
    const [modalOpen, setModalOpen] = useState(false);
    const { auth } = usePage().props;
    const canDispose = auth?.user?.roles?.some((r) => ['Admin', 'Supervisor'].includes(r));

    const hasDecision = inspection.disposition && inspection.disposition !== 'pending';

    return (
        <div className="card mb-4">
            <h3 className="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Disposition</h3>

            {hasDecision ? (
                <div>
                    <div className="flex items-center gap-3">
                        <span className={`px-3 py-1 rounded text-sm font-medium capitalize ${dispositionColorClass(inspection.disposition)}`}>
                            {(inspection.disposition ?? '').replace(/_/g, ' ')}
                        </span>
                        <span className="text-sm text-gray-500">
                            by {inspection.disposition_by?.name ?? '—'}
                            {inspection.disposition_at ? ` · ${fmtDateTime(inspection.disposition_at)}` : ''}
                        </span>
                    </div>
                    {inspection.disposition_notes && (
                        <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">{inspection.disposition_notes}</p>
                    )}
                </div>
            ) : (
                <div>
                    <p className="text-sm text-gray-500">No disposition recorded yet.</p>
                    {canDispose && (
                        <div className="mt-3">
                            <button
                                type="button"
                                onClick={() => setModalOpen(true)}
                                className="btn-touch btn-primary text-sm"
                            >
                                Record Disposition
                            </button>
                        </div>
                    )}
                </div>
            )}

            {modalOpen && (
                <DispositionModal
                    inspection={inspection}
                    onClose={() => setModalOpen(false)}
                />
            )}
        </div>
    );
}

// ---------------------------------------------------------------------------
// Disposition modal
// ---------------------------------------------------------------------------

function DispositionModal({ inspection, onClose }) {
    const form = useForm({ disposition: '', notes: '' });

    const submit = (e) => {
        e.preventDefault();
        form.post(`/inspections/${inspection.id}/disposition`, {
            onSuccess: onClose,
        });
    };

    return (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div
                className="bg-white dark:bg-slate-800 rounded-lg shadow-xl max-w-lg w-full p-6"
                onClick={(e) => e.stopPropagation()}
            >
                <h3 className="text-lg font-semibold mb-3">Record Disposition</h3>
                <form onSubmit={submit}>
                    <div className="space-y-2 mb-4">
                        {DISPOSITION_OPTIONS.map(({ value, label, desc }) => (
                            <label
                                key={value}
                                className="flex items-start gap-2 p-2 rounded border hover:bg-gray-50 dark:hover:bg-slate-700 cursor-pointer"
                            >
                                <input
                                    type="radio"
                                    name="disposition"
                                    value={value}
                                    required
                                    checked={form.data.disposition === value}
                                    onChange={() => form.setData('disposition', value)}
                                    className="mt-1"
                                />
                                <div>
                                    <div className="font-medium text-sm capitalize">{label}</div>
                                    <div className="text-xs text-gray-500">{desc}</div>
                                </div>
                            </label>
                        ))}
                    </div>
                    {form.errors.disposition && (
                        <p className="text-red-600 text-xs mb-2">{form.errors.disposition}</p>
                    )}
                    <textarea
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                        rows={3}
                        className="form-input w-full text-sm"
                        placeholder="Notes (optional)"
                    />
                    {form.errors.notes && (
                        <p className="text-red-600 text-xs mt-1">{form.errors.notes}</p>
                    )}
                    <div className="flex justify-end gap-2 mt-3">
                        <button
                            type="button"
                            onClick={onClose}
                            className="btn-touch btn-secondary text-sm"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="btn-touch btn-primary text-sm disabled:opacity-50"
                        >
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ---------------------------------------------------------------------------
// Results entry form (pending inspection)
// ---------------------------------------------------------------------------

function ResultsEntryForm({ inspection }) {
    // initialise local state from the loaded results
    const [rows, setRows] = useState(() =>
        (inspection.results ?? []).map((r) => ({
            id: r.id,
            criterion_name: r.criterion_name,
            criterion_type: r.criterion_type,
            spec_min: r.spec_min,
            spec_max: r.spec_max,
            unit: r.unit,
            required: r.required,
            value_numeric: r.value_numeric ?? '',
            value_boolean: r.value_boolean === true ? '1' : r.value_boolean === false ? '0' : '',
            notes: r.notes ?? '',
        }))
    );
    const [saving, setSaving] = useState(false);

    const updateRow = (idx, key, val) => {
        setRows((prev) => prev.map((row, i) => (i === idx ? { ...row, [key]: val } : row)));
    };

    const saveProgress = (e) => {
        e.preventDefault();
        setSaving(true);
        const payload = rows.map((row) => {
            const entry = { id: row.id };
            if (row.criterion_type === 'measurement') {
                entry.value_numeric = row.value_numeric !== '' ? row.value_numeric : null;
            } else {
                entry.value_boolean = row.value_boolean !== '' ? row.value_boolean : null;
            }
            entry.notes = row.notes || null;
            return entry;
        });
        router.post(
            `/inspections/${inspection.id}/results`,
            { results: payload },
            { onFinish: () => setSaving(false) }
        );
    };

    if (rows.length === 0) return null;

    return (
        <form onSubmit={saveProgress} className="card mb-4">
            <h2 className="text-lg font-bold mb-3">Record measurements</h2>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead className="bg-gray-50 dark:bg-slate-700">
                        <tr>
                            <th className="text-left p-2">Criterion</th>
                            <th className="text-left p-2">Type</th>
                            <th className="text-left p-2">Spec</th>
                            <th className="text-left p-2">Value</th>
                            <th className="text-left p-2">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row, idx) => (
                            <tr key={row.id} className="border-b border-gray-100 dark:border-gray-700">
                                <td className="p-2 font-medium">
                                    {row.criterion_name}
                                    {row.required && (
                                        <span className="text-red-500 ml-0.5" title="Required">*</span>
                                    )}
                                </td>
                                <td className="p-2 text-gray-500">{row.criterion_type}</td>
                                <td className="p-2 text-gray-500 font-mono text-xs">
                                    {row.criterion_type === 'measurement'
                                        ? `${row.spec_min ?? '−∞'} … ${row.spec_max ?? '+∞'} ${row.unit ?? ''}`
                                        : '—'}
                                </td>
                                <td className="p-2">
                                    {row.criterion_type === 'measurement' ? (
                                        <input
                                            type="number"
                                            step="0.0001"
                                            value={row.value_numeric}
                                            onChange={(e) => updateRow(idx, 'value_numeric', e.target.value)}
                                            className="form-input w-32"
                                        />
                                    ) : (
                                        <select
                                            value={row.value_boolean}
                                            onChange={(e) => updateRow(idx, 'value_boolean', e.target.value)}
                                            className="form-input w-28"
                                        >
                                            <option value="">—</option>
                                            <option value="1">Pass</option>
                                            <option value="0">Fail</option>
                                        </select>
                                    )}
                                </td>
                                <td className="p-2">
                                    <input
                                        type="text"
                                        value={row.notes}
                                        onChange={(e) => updateRow(idx, 'notes', e.target.value)}
                                        maxLength={1000}
                                        className="form-input w-full"
                                    />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="flex gap-2 justify-end mt-3">
                <button type="submit" disabled={saving} className="btn-touch btn-secondary disabled:opacity-50">
                    Save progress
                </button>
            </div>
        </form>
    );
}

// ---------------------------------------------------------------------------
// Complete inspection form
// ---------------------------------------------------------------------------

function CompleteForm({ inspection }) {
    const form = useForm({ notes: inspection.notes ?? '' });

    const submit = (e) => {
        e.preventDefault();
        if (!confirm('Complete this inspection? It cannot be edited afterwards.')) return;
        form.post(`/inspections/${inspection.id}/complete`);
    };

    return (
        <form onSubmit={submit} className="card">
            <h2 className="text-lg font-bold mb-3">Complete inspection</h2>
            <p className="text-sm text-gray-600 mb-2">
                Pass/fail is computed from the recorded results above. If any required criterion fails,
                a non-conformance issue is created automatically.
            </p>
            <textarea
                value={form.data.notes}
                onChange={(e) => form.setData('notes', e.target.value)}
                rows={2}
                placeholder="Optional notes…"
                className="form-input w-full mb-3"
            />
            {form.errors.notes && (
                <p className="text-red-600 text-xs mb-2">{form.errors.notes}</p>
            )}
            <div className="text-right">
                <button
                    type="submit"
                    disabled={form.processing}
                    className="btn-touch btn-primary disabled:opacity-50"
                >
                    Complete
                </button>
            </div>
        </form>
    );
}

// ---------------------------------------------------------------------------
// Read-only results table (completed inspection)
// ---------------------------------------------------------------------------

function ResultsTable({ results, notes }) {
    function resultValue(r) {
        if (r.value_numeric != null) return r.value_numeric;
        if (r.value_boolean === true) return 'pass';
        if (r.value_boolean === false) return 'fail';
        return r.value_text ?? '—';
    }

    function passBadge(isPassed) {
        if (isPassed === true) return <span className="inline-block px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-700">✓ Pass</span>;
        if (isPassed === false) return <span className="inline-block px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-700">✗ Fail</span>;
        return <span className="inline-block px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-500">—</span>;
    }

    return (
        <div className="card">
            <h2 className="text-lg font-bold mb-3">Results</h2>
            {results.length === 0 ? (
                <p className="text-gray-500">No results recorded.</p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 dark:bg-slate-700">
                            <tr>
                                <th className="text-left p-2">Criterion</th>
                                <th className="text-left p-2">Type</th>
                                <th className="text-left p-2">Spec</th>
                                <th className="text-left p-2">Value</th>
                                <th className="text-left p-2">Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            {results.map((r) => (
                                <tr key={r.id} className="border-b border-gray-100 dark:border-gray-700">
                                    <td className="p-2 font-medium">{r.criterion_name}</td>
                                    <td className="p-2 text-gray-500">{r.criterion_type}</td>
                                    <td className="p-2 text-gray-500 font-mono text-xs">
                                        {r.criterion_type === 'measurement'
                                            ? `${r.spec_min ?? '−∞'} … ${r.spec_max ?? '+∞'} ${r.unit ?? ''}`
                                            : '—'}
                                    </td>
                                    <td className="p-2 font-mono">{resultValue(r)}</td>
                                    <td className="p-2">{passBadge(r.is_passed)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            {notes && (
                <div className="mt-3 text-sm text-gray-700 dark:text-gray-300">
                    <strong>Notes:</strong> {notes}
                </div>
            )}
            <div className="text-right mt-3">
                <Link href="/inspections" className="btn-touch btn-secondary">Back</Link>
            </div>
        </div>
    );
}

// ---------------------------------------------------------------------------
// Main page
// ---------------------------------------------------------------------------

export default function InspectionsShow() {
    const { inspection } = usePage().props;
    const isPending = inspection.status === 'pending';

    return (
        <>
            <Head title={`Inspection #${inspection.id}`} />

            <div className="max-w-4xl mx-auto">
                {/* Header */}
                <div className="flex justify-between items-start mb-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-800 dark:text-gray-100">
                            Inspection #{inspection.id} — {inspection.material?.name ?? '—'}
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400 mt-1">
                            Lot: <span className="font-mono">{inspection.lot_number}</span>
                            {inspection.quantity_received != null && (
                                <> · Qty: {fmtNum(inspection.quantity_received)}</>
                            )}
                            {' · '}Inspector: {inspection.inspector?.name ?? '—'}
                            {' · '}Started: {fmtDateTime(inspection.started_at)}
                        </p>
                    </div>
                    <span className={`inline-block px-3 py-1 rounded text-sm font-semibold capitalize ${statusBadge(inspection.status)}`}>
                        {(inspection.status ?? '').replace(/_/g, ' ')}
                    </span>
                </div>

                {/* Disposition */}
                <DispositionSection inspection={inspection} />

                {/* Non-conformance alert */}
                {inspection.issue_id && (
                    <div className="card mb-4 border-l-4 border-red-500 bg-red-50 dark:bg-red-900/20">
                        <strong>Non-conformance created: Issue #{inspection.issue_id}</strong>
                        <p className="text-sm text-gray-700 dark:text-gray-300 mt-1">
                            A non-conformance issue was auto-generated because this inspection failed.
                        </p>
                    </div>
                )}

                {/* Pending: editable results + complete form */}
                {isPending && (inspection.results ?? []).length > 0 && (
                    <ResultsEntryForm inspection={inspection} />
                )}

                {isPending && (
                    <CompleteForm inspection={inspection} />
                )}

                {/* Completed: read-only table */}
                {!isPending && (
                    <ResultsTable
                        results={inspection.results ?? []}
                        notes={inspection.notes}
                    />
                )}
            </div>
        </>
    );
}

InspectionsShow.layout = (page) => <AppLayout>{page}</AppLayout>;
