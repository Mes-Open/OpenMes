// Geist White restyle: light-only v1 — om-* tokens, @openmes/ui controls.
import { useMemo, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Button, ConfirmDialog, Dropdown, StatusPill, TextField } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../layouts/AppLayout';
import { formatDateTime, formatNumber, __ } from '../../lib/i18n';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

// Inspection status → StatusPill status token.
function statusPill(status) {
    const map = {
        pass: 'running',
        conditional_pass: 'downtime',
        fail: 'blocked',
        pending: 'pending',
    };
    return map[status] ?? 'pending';
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

// Disposition → StatusPill status token.
function dispositionPill(disposition) {
    const map = {
        accept: 'running',
        accept_with_deviation: 'running',
        rework: 'downtime',
        quarantine: 'pending',
        scrap: 'blocked',
        reject: 'blocked',
        return_to_supplier: 'downtime',
    };
    return map[disposition] ?? 'pending';
}

const CARD_CLASS = 'bg-om-card border border-om-line rounded-om p-5';
const SECTION_HEADING_CLASS = 'text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-3';
const TH_CLASS = 'text-left p-2 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint';
const INPUT_CLASS =
    'bg-om-bg border border-om-line rounded-om-sm px-3 py-2 text-[13px] text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent focus:ring-[3px] focus:ring-[rgba(234,90,43,.12)]';

function fmtDateTime(str) {
    if (!str) return '—';
    return formatDateTime(new Date(str), {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

function fmtNum(n, decimals = 2) {
    if (n == null) return '—';
    return formatNumber(Number(n), { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
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
        <div className={`${CARD_CLASS} mb-4`}>
            <h3 className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-3">{__('Disposition')}</h3>

            {hasDecision ? (
                <div>
                    <div className="flex items-center gap-3">
                        <StatusPill
                            status={dispositionPill(inspection.disposition)}
                            pulse={false}
                            label={(inspection.disposition ?? '').replace(/_/g, ' ')}
                        />
                        <span className="text-[12.5px] text-om-muted">
                            by {inspection.disposition_by?.name ?? '—'}
                            {inspection.disposition_at ? ` · ${fmtDateTime(inspection.disposition_at)}` : ''}
                        </span>
                    </div>
                    {inspection.disposition_notes && (
                        <p className="mt-2 text-[12.5px] text-om-muted">{inspection.disposition_notes}</p>
                    )}
                </div>
            ) : (
                <div>
                    <p className="text-[12.5px] text-om-muted">{__('No disposition recorded yet.')}</p>
                    {canDispose && (
                        <div className="mt-3">
                            <Button variant="primary" onClick={() => setModalOpen(true)}>
                                {__('Record Disposition')}
                            </Button>
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
        <div className="fixed inset-0 bg-[rgba(10,9,8,0.4)] z-50 flex items-center justify-center p-4">
            <div
                className="bg-om-card border border-om-line rounded-om shadow-[0_20px_50px_-20px_rgba(0,0,0,.35)] max-w-lg w-full p-6"
                onClick={(e) => e.stopPropagation()}
            >
                <h3 className="text-[17px] font-semibold tracking-[-0.01em] text-om-ink mb-3">{__('Record Disposition')}</h3>
                <form onSubmit={submit}>
                    <div className="space-y-2 mb-4">
                        {DISPOSITION_OPTIONS.map(({ value, label, desc }) => (
                            <label
                                key={value}
                                className="flex items-start gap-2 p-2 rounded-om-sm border border-om-line hover:bg-om-chip cursor-pointer transition-colors"
                            >
                                <input
                                    type="radio"
                                    name="disposition"
                                    value={value}
                                    required
                                    checked={form.data.disposition === value}
                                    onChange={() => form.setData('disposition', value)}
                                    className="sr-only"
                                />
                                <span
                                    aria-hidden
                                    className={`mt-0.5 flex size-[18px] shrink-0 items-center justify-center rounded-full border-2 ${form.data.disposition === value ? 'border-om-accent' : 'border-om-faintest'}`}
                                >
                                    {form.data.disposition === value && <span className="size-2 rounded-full bg-om-accent" />}
                                </span>
                                <div>
                                    <div className="font-medium text-[13px] text-om-ink capitalize">{label}</div>
                                    <div className="text-[11.5px] text-om-muted">{desc}</div>
                                </div>
                            </label>
                        ))}
                    </div>
                    {form.errors.disposition && (
                        <p className="text-[11.5px] text-om-blocked mb-2">{form.errors.disposition}</p>
                    )}
                    <TextField
                        multiline
                        value={form.data.notes}
                        onChange={(v) => form.setData('notes', v)}
                        placeholder={__('Notes (optional)')}
                        error={form.errors.notes}
                    />
                    <div className="flex justify-end gap-2 mt-3">
                        <Button variant="secondary" onClick={onClose}>
                            {__('Cancel')}
                        </Button>
                        <Button type="submit" variant="primary" loading={form.processing}>
                            {__('Save')}
                        </Button>
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
        <form onSubmit={saveProgress} className={`${CARD_CLASS} mb-4`}>
            <h2 className={SECTION_HEADING_CLASS}>{__('Record measurements')}</h2>
            <div className="overflow-x-auto">
                <table className="w-full text-[13px]">
                    <thead>
                        <tr className="border-b border-om-line">
                            <th className={TH_CLASS}>{__('Criterion')}</th>
                            <th className={TH_CLASS}>{__('Type')}</th>
                            <th className={TH_CLASS}>{__('Spec')}</th>
                            <th className={TH_CLASS}>{__('Value')}</th>
                            <th className={TH_CLASS}>{__('Notes')}</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-om-line">
                        {rows.map((row, idx) => (
                            <tr key={row.id}>
                                <td className="p-2 font-medium text-om-ink">
                                    {row.criterion_name}
                                    {row.required && (
                                        <span className="text-om-accent ml-0.5" title={__('Required')}>*</span>
                                    )}
                                </td>
                                <td className="p-2 text-om-muted">{row.criterion_type}</td>
                                <td className="p-2 text-om-muted font-mono text-[12px]">
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
                                            className={`${INPUT_CLASS} w-32 font-mono`}
                                        />
                                    ) : (
                                        <Dropdown
                                            value={row.value_boolean == null ? '' : String(row.value_boolean)}
                                            onChange={(v) => updateRow(idx, 'value_boolean', v)}
                                            options={[
                                                { value: '', label: '—' },
                                                { value: '1', label: __('Pass') },
                                                { value: '0', label: __('Fail') },
                                            ]}
                                            className="w-28"
                                        />
                                    )}
                                </td>
                                <td className="p-2">
                                    <input
                                        type="text"
                                        value={row.notes}
                                        onChange={(e) => updateRow(idx, 'notes', e.target.value)}
                                        maxLength={1000}
                                        className={`${INPUT_CLASS} w-full`}
                                    />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="flex gap-2 justify-end mt-3">
                <Button type="submit" variant="secondary" loading={saving}>
                    {__('Save progress')}
                </Button>
            </div>
        </form>
    );
}

// ---------------------------------------------------------------------------
// Complete inspection form
// ---------------------------------------------------------------------------

function CompleteForm({ inspection }) {
    const form = useForm({ notes: inspection.notes ?? '' });
    const [confirmOpen, setConfirmOpen] = useState(false);

    const submit = (e) => {
        e.preventDefault();
        setConfirmOpen(true);
    };

    const confirmComplete = () => {
        setConfirmOpen(false);
        form.post(`/inspections/${inspection.id}/complete`);
    };

    return (
        <form onSubmit={submit} className={CARD_CLASS}>
            <h2 className={SECTION_HEADING_CLASS}>{__('Complete inspection')}</h2>
            <p className="text-[12.5px] text-om-muted mb-2">
                Pass/fail is computed from the recorded results above. If any required criterion fails,
                a non-conformance issue is created automatically.
            </p>
            <TextField
                multiline
                rows={2}
                value={form.data.notes}
                onChange={(v) => form.setData('notes', v)}
                placeholder={__('Optional notes…')}
                error={form.errors.notes}
                className="mb-3"
            />
            <div className="text-right">
                <Button type="submit" variant="accent" loading={form.processing}>
                    {__('Complete')}
                </Button>
            </div>
            <ConfirmDialog
                open={confirmOpen}
                onClose={() => setConfirmOpen(false)}
                onConfirm={confirmComplete}
                destructive={false}
                title="Complete this inspection?"
                confirmLabel={__('Complete')}
                cancelLabel={__('Cancel')}
            >
                It cannot be edited afterwards.
            </ConfirmDialog>
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
        if (isPassed === true) return <StatusPill status="running" pulse={false} label="✓ Pass" />;
        if (isPassed === false) return <StatusPill status="blocked" label="✗ Fail" />;
        return <StatusPill status="pending" label="—" />;
    }

    const columns = useMemo(() => [
        {
            id: 'criterion_name',
            accessorKey: 'criterion_name',
            header: __('Criterion'),
            cell: ({ row }) => (
                <span className="font-medium text-om-ink">{row.original.criterion_name}</span>
            ),
        },
        {
            id: 'criterion_type',
            accessorKey: 'criterion_type',
            header: __('Type'),
            cell: ({ row }) => (
                <span className="text-om-muted">{row.original.criterion_type}</span>
            ),
        },
        {
            id: 'spec',
            accessorFn: (r) =>
                r.criterion_type === 'measurement'
                    ? `${r.spec_min ?? '−∞'} … ${r.spec_max ?? '+∞'} ${r.unit ?? ''}`
                    : '—',
            header: __('Spec'),
            cell: ({ row }) => (
                <span className="text-om-muted font-mono text-[12px]">
                    {row.original.criterion_type === 'measurement'
                        ? `${row.original.spec_min ?? '−∞'} … ${row.original.spec_max ?? '+∞'} ${row.original.unit ?? ''}`
                        : '—'}
                </span>
            ),
        },
        {
            id: 'value',
            accessorFn: (r) => resultValue(r),
            header: __('Value'),
            cell: ({ row }) => (
                <span className="font-mono text-om-ink">{resultValue(row.original)}</span>
            ),
        },
        {
            id: 'result',
            accessorKey: 'is_passed',
            header: __('Result'),
            cell: ({ row }) => passBadge(row.original.is_passed),
        },
    ], []);

    return (
        <div className={CARD_CLASS}>
            <h2 className={SECTION_HEADING_CLASS}>{__('Results')}</h2>
            {results.length === 0 ? (
                <p className="text-[13px] text-om-muted">{__('No results recorded.')}</p>
            ) : (
                <DataTable
                    data={results}
                    columns={columns}
                    searchable={false}
                    columnToggle={false}
                    paginated={false}
                    emptyLabel={__('No results recorded.')}
                />
            )}
            {notes && (
                <div className="mt-3 text-[12.5px] text-om-muted">
                    <strong className="text-om-ink">{__('Notes:')}</strong> {notes}
                </div>
            )}
            <div className="text-right mt-3">
                <Link
                    href="/inspections"
                    className="inline-flex items-center justify-center rounded-om-sm border border-om-line px-4 py-[9px] text-[13px] font-semibold text-om-ink hover:bg-om-chip transition-colors"
                >
                    {__('Back')}
                </Link>
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
            <Head title={__('Inspection #:id', { id: inspection.id })} />

            <div className="max-w-4xl mx-auto">
                {/* Header */}
                <div className="flex justify-between items-start mb-4">
                    <div>
                        <h1 className="text-[22px] font-semibold tracking-[-0.02em] text-om-ink">
                            Inspection #{inspection.id} — {inspection.material?.name ?? '—'}
                        </h1>
                        <p className="text-[13px] text-om-muted mt-1">
                            Lot: <span className="font-mono text-om-ink">{inspection.lot_number}</span>
                            {inspection.quantity_received != null && (
                                <> · {__('Qty')}: {fmtNum(inspection.quantity_received)}</>
                            )}
                            {' · '}{__('Inspector')}: {inspection.inspector?.name ?? '—'}
                            {' · '}{__('Started')}: {fmtDateTime(inspection.started_at)}
                        </p>
                    </div>
                    <StatusPill
                        status={statusPill(inspection.status)}
                        pulse={false}
                        label={(inspection.status ?? '').replace(/_/g, ' ')}
                    />
                </div>

                {/* Disposition */}
                <DispositionSection inspection={inspection} />

                {/* Non-conformance alert */}
                {inspection.issue_id && (
                    <div className="mb-4 rounded-om border border-om-line border-l-4 border-l-om-blocked bg-om-blocked-bg p-5">
                        <strong className="text-[13px] text-om-blocked">Non-conformance created: Issue #{inspection.issue_id}</strong>
                        <p className="text-[12.5px] text-om-blocked mt-1">
                            {__('A non-conformance issue was auto-generated because this inspection failed.')}
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
