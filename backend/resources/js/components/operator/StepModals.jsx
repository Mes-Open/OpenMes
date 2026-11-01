import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { Button, Dropdown, Icon } from '@openmes/ui';
import Tooltip from '../Tooltip';
import QuantityField from '../QuantityField';
import { __, formatDate, formatNumber } from '../../lib/i18n';

// Step dialogs shared by the work-order page and the operator shell's
// execution panel, so starting or finishing a step asks the same questions
// (which lots to pick, the actual times) wherever the operator does it.

function fmtQty(n, decimals = 2) {
    if (n == null) return '—';
    return formatNumber(Number(n), { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

const errorCls = 'mt-[5px] text-[11.5px] text-om-blocked';
export const modalFooterCls = 'flex justify-end gap-[9px] border-t border-om-line2 bg-om-panel px-[18px] py-[14px]';

// ---------------------------------------------------------------------------
// Modal shell — §09 idiom (radius 12, deep shadow, header hairline + mono
// subtitle, panel footer supplied by callers inside their <form>).
// ---------------------------------------------------------------------------

export function ModalShell({ title, subtitle, onClose, children }) {
    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="fixed inset-0 bg-[rgba(10,9,8,0.4)]" onClick={onClose} />
            <div className="flex min-h-full items-center justify-center p-4">
                <div
                    className="relative w-full max-w-md overflow-hidden rounded-om border border-om-line bg-om-card shadow-[0_20px_50px_-20px_rgba(0,0,0,.35)]"
                    onClick={(e) => e.stopPropagation()}
                >
                    <div className="flex items-center justify-between border-b border-om-line2 px-[18px] py-4">
                        <div>
                            <div className="text-[15px] font-semibold text-om-ink">{title}</div>
                            {subtitle != null && (
                                <div className="mt-[3px] font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{subtitle}</div>
                            )}
                        </div>
                        <button
                            type="button"
                            onClick={onClose}
                            aria-label={__('Close')}
                            className="cursor-pointer text-om-faint hover:text-om-muted"
                        >
                            <Icon name="x" size={18} />
                        </button>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}

/**
 * Confirm-actual-times modal (#52), shown at step completion only for steps with
 * ISA-95 standard times configured. Prefills elapsed from started_at→now and the
 * setup/run split from the plan; the operator can correct them. Setup + run must
 * fit within elapsed. Posts to the same complete route with the actual times.
 */
export function ConfirmTimesModal({ step, onClose }) {
    const startedAt = step.started_at ? new Date(step.started_at).getTime() : null;
    const initialElapsed = startedAt ? Math.max(0, Math.round((Date.now() - startedAt) / 60000)) : 0;
    const initialSetup = step.setup_time_minutes != null ? Number(step.setup_time_minutes) : 0;

    const [elapsed, setElapsed] = useState(String(initialElapsed));
    const [setup, setSetup] = useState(step.setup_time_minutes != null ? String(initialSetup) : '');
    const [run, setRun] = useState(String(Math.max(0, initialElapsed - initialSetup)));
    const [saving, setSaving] = useState(false);

    // Backend rules are integer|min:0; mirror them so bad values never submit
    // (the number inputs' min= does not block this custom-button submission).
    const isNonNegInt = (v) => /^\d+$/.test(String(v).trim());
    const elapsedValid = isNonNegInt(elapsed);
    const setupValid = setup === '' || isNonNegInt(setup);
    const runValid = run === '' || isNonNegInt(run);
    const elapsedNum = elapsedValid ? Number(elapsed) : 0;
    const overflow = (Number(setup) || 0) + (Number(run) || 0) > elapsedNum;
    const invalid = ! elapsedValid || ! setupValid || ! runValid || overflow;

    const submit = () => {
        if (invalid) return;
        setSaving(true);
        router.post(`/operator/batch-step/${step.id}/complete`, {
            actual_elapsed_minutes: elapsedNum,
            actual_setup_minutes: setup === '' ? null : Number(setup),
            actual_run_minutes: run === '' ? null : Number(run),
        }, {
            preserveScroll: true,
            // Close only on success; keep the modal open (with values intact) so a
            // 422 or completion error stays visible instead of being dismissed.
            onSuccess: () => onClose(),
            onFinish: () => setSaving(false),
        });
    };

    const inputCls = 'w-full rounded-om-sm border border-om-line bg-om-card px-3 py-2 font-mono text-[15px]';
    const labelCls = 'block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-1';

    return (
        <ModalShell title={__('Confirm actual times')} subtitle={step.name} onClose={onClose}>
            <div className="px-[18px] py-4 space-y-3">
                <div>
                    <div className={labelCls}>{__('Actual elapsed (minutes)')}</div>
                    <input aria-label={__('Actual elapsed (minutes)')} type="number" min="0" value={elapsed} onChange={(e) => setElapsed(e.target.value)} className={inputCls} />
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <div className={labelCls}>{__('Actual setup (minutes)')}</div>
                        <input aria-label={__('Actual setup (minutes)')} type="number" min="0" value={setup} onChange={(e) => setSetup(e.target.value)} className={inputCls} placeholder={__('optional')} />
                    </div>
                    <div>
                        <div className={labelCls}>{__('Actual run (minutes)')}</div>
                        <input aria-label={__('Actual run (minutes)')} type="number" min="0" value={run} onChange={(e) => setRun(e.target.value)} className={inputCls} placeholder={__('optional')} />
                    </div>
                </div>
                {overflow && <p className="text-om-blocked text-xs">{__('Setup + run cannot exceed the elapsed time.')}</p>}
            </div>
            <div className={modalFooterCls}>
                <Button variant="secondary" onClick={onClose}>{__('Cancel')}</Button>
                <Button variant="primary" disabled={invalid || saving} onClick={submit}>
                    {saving ? '…' : __('Complete step')}
                </Button>
            </div>
        </ModalShell>
    );
}

// ---------------------------------------------------------------------------
// WO-time lot picking modal (ERP-aligned "suggest + override"): the system
// proposes lots (FEFO/FIFO/LIFO); the operator can split/reassign quantities
// across the candidate lots before starting the step. Lots only (phase 1).
// ---------------------------------------------------------------------------

const EPSILON = 0.0001;

export function LotPickModal({ step, materials, onClose }) {
    const [submitting, setSubmitting] = useState(false);
    const [startError, setStartError] = useState('');
    // picks: { [materialId]: [{ material_lot_id, picked_qty: string }] }
    const [picks, setPicks] = useState(() =>
        Object.fromEntries(
            materials.map((m) => [
                m.material_id,
                m.proposed.map((p) => ({ material_lot_id: p.material_lot_id, picked_qty: String(p.picked_qty) })),
            ])
        )
    );

    // material_id -> { lotId -> candidate } for quick lookups.
    const candById = useMemo(
        () =>
            Object.fromEntries(
                materials.map((m) => [m.material_id, Object.fromEntries(m.candidates.map((c) => [c.id, c]))])
            ),
        [materials]
    );

    const setLineQty = (matId, idx, val) =>
        setPicks((prev) => {
            const lines = [...(prev[matId] ?? [])];
            lines[idx] = { ...lines[idx], picked_qty: val };
            return { ...prev, [matId]: lines };
        });

    const removeLine = (matId, idx) =>
        setPicks((prev) => ({ ...prev, [matId]: (prev[matId] ?? []).filter((_, i) => i !== idx) }));

    const addLine = (matId, lotId, required) =>
        setPicks((prev) => {
            const lines = prev[matId] ?? [];
            if (lines.some((ln) => ln.material_lot_id === lotId)) return prev;
            const allocated = lines.reduce((s, ln) => s + (Number(ln.picked_qty) || 0), 0);
            const cand = candById[matId][lotId];
            const want = Math.max(required - allocated, 0);
            const qty = Math.min(want > 0 ? want : cand.quantity_available, cand.quantity_available);
            return { ...prev, [matId]: [...lines, { material_lot_id: lotId, picked_qty: String(round4(qty)) }] };
        });

    const materialValid = (m) => {
        const lines = picks[m.material_id] ?? [];
        if (lines.length === 0) return false;
        let sum = 0;
        for (const ln of lines) {
            const q = Number(ln.picked_qty);
            const cand = candById[m.material_id][ln.material_lot_id];
            if (!(q > 0) || !cand || q > cand.quantity_available + EPSILON) return false;
            sum += q;
        }
        return Math.abs(sum - m.required_qty) < EPSILON;
    };

    const allValid = materials.every(materialValid);

    const submit = (e) => {
        e.preventDefault();
        if (!allValid) return;
        setStartError('');
        setSubmitting(true);
        const payload = {
            picks: materials.map((m) => ({
                material_id: m.material_id,
                lots: (picks[m.material_id] ?? []).map((ln) => ({
                    material_lot_id: ln.material_lot_id,
                    picked_qty: Number(ln.picked_qty),
                })),
            })),
        };
        router.post(`/operator/batch-step/${step.id}/start`, payload, {
            preserveScroll: true,
            onError: errors => setStartError(Object.values(errors).join(' ')),
            onSuccess: page => {
                if (page.props.flash?.error) setStartError(page.props.flash.error);
                else onClose();
            },
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <ModalShell title={__("Pick material lots")} subtitle={step.name} onClose={onClose}>
            <form onSubmit={submit}>
                <div className="max-h-[60vh] space-y-5 overflow-y-auto px-[18px] py-4">
                    {startError && <p role="alert" className="text-sm text-om-blocked">{startError}</p>}
                    {materials.map((m) => {
                        const lines = picks[m.material_id] ?? [];
                        const allocated = lines.reduce((s, ln) => s + (Number(ln.picked_qty) || 0), 0);
                        const balanced = Math.abs(allocated - m.required_qty) < EPSILON;
                        const remaining = m.candidates.filter((c) => !lines.some((ln) => ln.material_lot_id === c.id));
                        return (
                            <div key={m.material_id} className="rounded-om-sm border border-om-line2 bg-om-panel p-3">
                                <div className="mb-2 flex items-start justify-between gap-2">
                                    <div>
                                        <span className="text-sm font-medium text-om-ink">{m.material_name}</span>
                                        <span className="ml-1 font-mono text-[11px] text-om-faint">{m.material_code}</span>
                                    </div>
                                    <span className="font-mono text-[10px] uppercase tracking-[0.08em] text-om-muted">
                                        {m.strategy}
                                    </span>
                                </div>

                                {lines.length === 0 && (
                                    <p className={errorCls}>
                                        {m.candidates.length === 0
                                            ? __('No lots available for this material')
                                            : __('Add a lot to allocate the required quantity.')}
                                    </p>
                                )}

                                <div className="space-y-3">
                                    {lines.map((ln, idx) => {
                                        const cand = candById[m.material_id][ln.material_lot_id];
                                        const over = Number(ln.picked_qty) > (cand?.quantity_available ?? 0) + EPSILON;
                                        return (
                                            <div key={ln.material_lot_id} className="flex items-center justify-between gap-3 border-b border-om-line2 pb-2.5 last:border-0 last:pb-0">
                                                <div className="min-w-0 flex-1">
                                                    <div className="font-mono text-[12px] font-semibold text-om-ink break-all leading-normal">
                                                        {cand?.lot_number ?? `#${ln.material_lot_id}`}
                                                    </div>
                                                    <div className="font-mono text-[11px] text-om-faint mt-0.5">
                                                        {__('avail')}: <span className="text-om-muted font-medium">{fmtQty(cand?.quantity_available, 4)}</span>
                                                        {cand?.expiry_date ? ` · ${__('exp')}: ${formatDate(cand.expiry_date)}` : ''}
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-1.5 flex-shrink-0">
                                                    <QuantityField
                                                        step="0.0001"
                                                        min="0"
                                                        inputMode="decimal"
                                                        value={ln.picked_qty}
                                                        onChange={(v) => setLineQty(m.material_id, idx, v)}
                                                        className="text-[12px] text-om-ink bg-om-bg border border-om-line rounded-om-sm px-2 py-1 outline-none w-20 text-right focus:border-om-accent transition-colors font-mono"
                                                    />
                                                    <Tooltip label="Remove lot">
                                                        <button
                                                            type="button"
                                                            onClick={() => removeLine(m.material_id, idx)}
                                                            className="cursor-pointer p-1 text-om-faint hover:text-om-blocked"
                                                            aria-label="Remove lot"
                                                        >
                                                            <Icon name="x" size={16} />
                                                        </button>
                                                    </Tooltip>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>

                                {remaining.length > 0 && (
                                    <Dropdown
                                        options={remaining.map((c) => ({
                                            value: String(c.id),
                                            label: `${c.lot_number} — ${__('avail')} ${fmtQty(c.quantity_available, 4)}`
                                                + (c.expiry_date ? ` (${__('exp')} ${formatDate(c.expiry_date)})` : ''),
                                        }))}
                                        value=""
                                        onChange={(val) => {
                                            if (val) addLine(m.material_id, Number(val), m.required_qty);
                                        }}
                                        placeholder={__('Add lot…')}
                                        aria-label={__('Add lot…')}
                                        className="mt-2 w-full"
                                    />
                                )}

                                <div className="mt-2 flex justify-between font-mono text-[11px]">
                                    <span className="text-om-faint">
                                        {__('Required')} {fmtQty(m.required_qty, 4)} {m.unit_of_measure}
                                    </span>
                                    <span className={balanced ? 'text-om-done' : 'text-om-blocked'}>
                                        {__('Allocated')} {fmtQty(allocated, 4)}
                                    </span>
                                </div>
                            </div>
                        );
                    })}
                </div>
                <div className={modalFooterCls}>
                    <Button variant="secondary" type="button" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button variant="accent" type="submit" disabled={!allValid || submitting}>
                        {submitting ? '…' : __('Confirm picks & start')}
                    </Button>
                </div>
            </form>
        </ModalShell>
    );
}

function round4(n) {
    return Math.round((Number(n) + Number.EPSILON) * 10000) / 10000;
}
