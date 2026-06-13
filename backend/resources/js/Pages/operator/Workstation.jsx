import { useState, useEffect, useRef, useCallback } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import OperatorLayout from '../../layouts/OperatorLayout';
import LineSync from '../../components/LineSync';
import LabelPrintMenu from '../../components/LabelPrintMenu';
import { formatDate, formatNumber } from '../../lib/i18n';

// ─── helpers ────────────────────────────────────────────────────────────────

function fmt(n) {
    return formatNumber(Number(n ?? 0), { maximumFractionDigits: 0 });
}

function weekLabel(wk) {
    return 'W' + String(wk).padStart(2, '0');
}

function statusLabel(status) {
    if (status === 'PENDING') return 'Not Started';
    if (status === 'IN_PROGRESS') return 'In Progress';
    if (status === 'DONE') return 'Done';
    if (status === 'BLOCKED') return 'Blocked';
    return status.replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, (c) => c.toUpperCase());
}

function getCellValue(wo, col) {
    if (col.source === 'extra_data') {
        const val = wo.extra_data?.[col.key];
        return val !== undefined && val !== null ? String(val) : '—';
    }
    if (col.source === 'product_type') {
        return wo.product_type?.name ?? '—';
    }
    // field
    if (col.key === 'due_date') {
        if (!wo.due_date) return '—';
        const d = new Date(wo.due_date);
        return formatDate(d, { day: '2-digit', month: 'short' });
    }
    if (col.key === 'week_number') {
        return wo.week_number ? weekLabel(wo.week_number) : '—';
    }
    const v = wo[col.key];
    return v !== undefined && v !== null ? String(v) : '—';
}

// ─── column visibility hook ──────────────────────────────────────────────────

function useVisibleColumns(allColumns, lineId) {
    const storageKey = `ws_cols_${lineId}`;
    const defaultVisible = allColumns.filter((c) => c.default).map((c) => c.key);

    const [visibleKeys, setVisibleKeys] = useState(() => {
        try {
            const saved = localStorage.getItem(storageKey);
            return saved ? JSON.parse(saved) : defaultVisible;
        } catch {
            return defaultVisible;
        }
    });

    const toggleColumn = useCallback(
        (key) => {
            setVisibleKeys((prev) => {
                const next = prev.includes(key) ? prev.filter((k) => k !== key) : [...prev, key];
                try {
                    localStorage.setItem(storageKey, JSON.stringify(next));
                } catch {}
                return next;
            });
        },
        [storageKey],
    );

    const resetColumns = useCallback(() => {
        setVisibleKeys(defaultVisible);
        try {
            localStorage.removeItem(storageKey);
        } catch {}
    }, [storageKey]); // eslint-disable-line react-hooks/exhaustive-deps

    return { visibleKeys, toggleColumn, resetColumns };
}

// ─── timed-correction link ───────────────────────────────────────────────────

function TimedCorrectLink({ entry, qtyEditPolicy, qtyEditWindowMinutes }) {
    const [visible, setVisible] = useState(true);

    useEffect(() => {
        if (qtyEditPolicy !== 'timed') return;
        const deadline = new Date(entry.updated_at).getTime() + qtyEditWindowMinutes * 60 * 1000;
        const tick = setInterval(() => {
            if (Date.now() > deadline) {
                setVisible(false);
                clearInterval(tick);
            }
        }, 5000);
        return () => clearInterval(tick);
    }, [entry.updated_at, qtyEditPolicy, qtyEditWindowMinutes]);

    if (!visible) return null;

    return (
        <Link
            href={`/operator/shift-entry/${entry.id}/correct`}
            className="w-6 h-6 flex items-center justify-center rounded text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
            title="Correct quantity"
            onClick={(e) => e.stopPropagation()}
        >
            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                    d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
            </svg>
        </Link>
    );
}

// ─── shift cell ─────────────────────────────────────────────────────────────

function ShiftCell({ wo, shift, shiftEntries, qtyEditPolicy, qtyEditWindowMinutes }) {
    const isDone = wo.status === 'DONE';
    const entryKey = `${wo.id}_${shift.id}`;
    const entriesForCell = shiftEntries[entryKey] ?? [];
    const firstEntry = entriesForCell[0] ?? null;
    const entryQty = firstEntry ? parseFloat(firstEntry.quantity) : 0;

    const defaultVal = entryQty > 0 ? String(Math.round(entryQty)) : '';
    const [inputVal, setInputVal] = useState(defaultVal);
    const prevEntryQty = useRef(entryQty);

    // Sync when server data changes (after reload)
    useEffect(() => {
        if (prevEntryQty.current !== entryQty) {
            prevEntryQty.current = entryQty;
            setInputVal(entryQty > 0 ? String(Math.round(entryQty)) : '');
        }
    }, [entryQty]);

    const submit = useCallback(() => {
        const qty = parseInt(inputVal, 10);
        if (!qty || qty <= 0) return;
        router.post(`/operator/workstation/${wo.id}/shift-entry`, { shift_id: shift.id, quantity: qty });
    }, [inputVal, wo.id, shift.id]);

    if (isDone) {
        return (
            <td className="px-2 py-1 text-center" onClick={(e) => e.stopPropagation()}>
                <span className="text-gray-400">{entryQty > 0 ? Math.round(entryQty) : 0}</span>
            </td>
        );
    }

    const canCorrect =
        firstEntry &&
        entryQty > 0 &&
        qtyEditPolicy !== 'none' &&
        (qtyEditPolicy === 'full' ||
            (qtyEditPolicy === 'timed' &&
                new Date(firstEntry.updated_at).getTime() + qtyEditWindowMinutes * 60 * 1000 > Date.now()));

    return (
        <td className="px-2 py-1 text-center" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-center gap-1">
                <input
                    type="number"
                    value={inputVal}
                    onChange={(e) => setInputVal(e.target.value)}
                    onFocus={(e) => e.target.select()}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            submit();
                        }
                    }}
                    onBlur={() => {
                        const qty = parseInt(inputVal, 10);
                        if (qty > 0 && inputVal !== defaultVal) submit();
                    }}
                    className={`w-16 text-center text-sm font-semibold border border-gray-300 dark:border-gray-600 rounded px-1 py-1.5 tabular-nums focus:ring-2 focus:ring-blue-400 ${
                        entryQty > 0
                            ? 'text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/20'
                            : 'bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200'
                    }`}
                    placeholder="—"
                    min="1"
                    step="1"
                    inputMode="numeric"
                />
                {canCorrect && (
                    <TimedCorrectLink
                        entry={firstEntry}
                        qtyEditPolicy={qtyEditPolicy}
                        qtyEditWindowMinutes={qtyEditWindowMinutes}
                    />
                )}
            </div>
        </td>
    );
}

// ─── modals ──────────────────────────────────────────────────────────────────

function StartModal({ modal, onClose }) {
    if (!modal.open) return null;
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="fixed inset-0 bg-black/50" onClick={onClose} />
            <div className="relative bg-white dark:bg-gray-800 w-full max-w-sm rounded-xl shadow-2xl p-6" onClick={(e) => e.stopPropagation()}>
                <h3 className="text-lg font-bold text-green-700 dark:text-green-400 mb-3">Start Production</h3>
                <p className="text-gray-700 dark:text-gray-300 mb-2 text-lg">
                    <strong>{modal.product}</strong>
                </p>
                <p className="text-sm text-gray-500 dark:text-gray-400 mb-1">
                    Order: <span className="font-mono">{modal.orderNo}</span>
                </p>
                <p className="text-sm text-gray-500 dark:text-gray-400 mb-5">
                    Planned: <strong>{fmt(modal.qty)}</strong> units
                </p>
                <div className="flex gap-3">
                    <button
                        type="button"
                        onClick={onClose}
                        className="flex-1 py-3 text-base rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 font-medium transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={() => {
                            onClose();
                            router.post(`/operator/workstation/${modal.id}/start`);
                        }}
                        className="flex-1 py-3 text-base font-bold rounded-lg bg-green-600 hover:bg-green-700 text-white transition-colors"
                    >
                        Start
                    </button>
                </div>
            </div>
        </div>
    );
}

function CompleteModal({ modal, onClose }) {
    const [qty, setQty] = useState('');

    useEffect(() => {
        if (modal.open) setQty('');
    }, [modal.open, modal.id]);

    if (!modal.open) return null;

    const handleSubmit = (e) => {
        e.preventDefault();
        const n = parseInt(qty, 10);
        if (isNaN(n) || n < 0) return;
        onClose();
        router.post(`/operator/workstation/${modal.id}/complete`, { produced_qty: n });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="fixed inset-0 bg-black/50" onClick={onClose} />
            <div className="relative bg-white dark:bg-gray-800 w-full max-w-sm rounded-xl shadow-2xl p-6" onClick={(e) => e.stopPropagation()}>
                <h3 className="text-lg font-bold text-blue-700 dark:text-blue-400 mb-3">Add Produced Quantity</h3>
                <p className="text-gray-700 dark:text-gray-300 mb-1">
                    <strong>{modal.product}</strong>
                </p>
                <p className="text-sm text-gray-500 dark:text-gray-400 mb-1">
                    Order: <span className="font-mono">{modal.orderNo}</span>
                </p>
                <p className="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    Planned: <strong>{fmt(modal.planned)}</strong> | Already produced: <strong>{fmt(modal.produced)}</strong>
                </p>
                <form onSubmit={handleSubmit}>
                    <div className="mb-5">
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Quantity <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="number"
                            value={qty}
                            onChange={(e) => setQty(e.target.value)}
                            className="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-4 text-3xl font-bold text-center tabular-nums bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-400 outline-none"
                            placeholder="0"
                            min="0"
                            step="1"
                            required
                            autoFocus
                            inputMode="numeric"
                        />
                    </div>
                    <div className="flex gap-3">
                        <button
                            type="button"
                            onClick={onClose}
                            className="flex-1 py-3 text-base rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 font-medium transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={qty === '' || parseInt(qty, 10) < 0}
                            className="flex-1 py-3 text-base font-bold rounded-lg bg-blue-600 hover:bg-blue-700 text-white disabled:opacity-40 transition-colors"
                        >
                            Confirm
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function InfoModal({ info, onClose }) {
    if (!info) return null;
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="fixed inset-0 bg-black/50" onClick={onClose} />
            <div className="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-md w-full p-6" onClick={(e) => e.stopPropagation()}>
                <div className="flex justify-between items-center mb-4">
                    <h3 className="text-lg font-bold text-gray-800 dark:text-white">Order Details</h3>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div className="space-y-3">
                    <InfoRow label="Order #"><span className="text-sm font-bold font-mono">{info.orderNo}</span></InfoRow>
                    <InfoRow label="Product"><span className="text-sm font-medium">{info.product}</span></InfoRow>
                    <InfoRow label="Line"><span className="text-sm font-medium">{info.line}</span></InfoRow>
                    <InfoRow label="Status"><span className="text-sm font-bold">{info.status}</span></InfoRow>
                    <div className="grid grid-cols-3 gap-3 py-2">
                        <div className="text-center">
                            <p className="text-xs text-gray-500">Planned</p>
                            <p className="text-lg font-bold text-gray-800 dark:text-white">{info.planned}</p>
                        </div>
                        <div className="text-center">
                            <p className="text-xs text-gray-500">Produced</p>
                            <p className="text-lg font-bold text-blue-600">{info.produced}</p>
                        </div>
                        <div className="text-center">
                            <p className="text-xs text-gray-500">Remaining</p>
                            <p className="text-lg font-bold text-orange-600">{info.remaining}</p>
                        </div>
                    </div>
                    <InfoRow label="Priority"><span className="text-sm font-medium">{info.priority}</span></InfoRow>
                    <InfoRow label="Due Date"><span className="text-sm font-medium">{info.dueDate}</span></InfoRow>
                    {info.description && info.description !== '-' && (
                        <div>
                            <p className="text-xs text-gray-500 mb-1">Description</p>
                            <p className="text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700 rounded p-2">{info.description}</p>
                        </div>
                    )}
                </div>
                <button
                    type="button"
                    onClick={onClose}
                    className="w-full mt-4 py-3 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold transition-colors"
                >
                    Close
                </button>
            </div>
        </div>
    );
}

function InfoRow({ label, children }) {
    return (
        <div className="flex justify-between border-b border-gray-200 dark:border-gray-700 pb-2">
            <span className="text-sm text-gray-500">{label}</span>
            {children}
        </div>
    );
}

function ReportModal({ report, issueTypes, onClose }) {
    const [typeId, setTypeId] = useState('');
    const [title, setTitle] = useState('');
    const [desc, setDesc] = useState('');

    useEffect(() => {
        if (report) {
            setTypeId('');
            setTitle('');
            setDesc('');
        }
    }, [report?.woId]);

    if (!report) return null;

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!typeId || !title) return;
        onClose();
        router.post('/operator/issue', {
            work_order_id: report.woId,
            issue_type_id: typeId,
            title,
            description: desc,
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="fixed inset-0 bg-black/50" onClick={onClose} />
            <div className="relative bg-white dark:bg-gray-800 w-full max-w-lg rounded-xl shadow-2xl p-6" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-center justify-between mb-4">
                    <div>
                        <h3 className="text-lg font-bold text-gray-800 dark:text-white">Report Issue</h3>
                        <p className="text-sm text-gray-500 font-mono">{report.woNo}</p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Type <span className="text-red-500">*</span>
                        </label>
                        <div className="grid grid-cols-2 gap-2">
                            {issueTypes.map((t) => (
                                <label
                                    key={t.id}
                                    className={`flex items-center gap-2 p-2.5 rounded-lg border-2 cursor-pointer transition-colors ${
                                        String(typeId) === String(t.id)
                                            ? 'border-orange-400 bg-orange-50 dark:bg-orange-900/20'
                                            : 'border-gray-200 dark:border-gray-600 hover:border-gray-300'
                                    }`}
                                >
                                    <input
                                        type="radio"
                                        name="issue_type_id"
                                        value={t.id}
                                        checked={String(typeId) === String(t.id)}
                                        onChange={() => {
                                            setTypeId(String(t.id));
                                            if (!title) setTitle(t.name);
                                        }}
                                        className="sr-only"
                                        required
                                    />
                                    <span className="text-sm font-medium text-gray-700 dark:text-gray-300">{t.name}</span>
                                </label>
                            ))}
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Title <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            value={title}
                            onChange={(e) => setTitle(e.target.value)}
                            className="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-400 outline-none"
                            required
                            maxLength={255}
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Details <span className="text-gray-400 text-xs">(optional)</span>
                        </label>
                        <textarea
                            value={desc}
                            onChange={(e) => setDesc(e.target.value)}
                            rows={3}
                            className="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-400 outline-none resize-none"
                            maxLength={2000}
                        />
                    </div>

                    <div className="flex gap-3">
                        <button
                            type="button"
                            onClick={onClose}
                            className="flex-1 py-3 rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 font-medium transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={!typeId || !title}
                            className="flex-1 py-3 rounded-lg bg-orange-500 hover:bg-orange-600 text-white font-semibold disabled:opacity-40 transition-colors"
                        >
                            Submit Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ─── column picker dropdown ───────────────────────────────────────────────────

function ColumnPicker({ allColumns, visibleKeys, toggleColumn, resetColumns }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

    useEffect(() => {
        const handler = (e) => {
            if (ref.current && !ref.current.contains(e.target)) setOpen(false);
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    const systemCols = allColumns.filter((c) => c.source !== 'extra_data');
    const extraCols = allColumns.filter((c) => c.source === 'extra_data');

    return (
        <div className="relative" ref={ref}>
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="p-2 rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                title="Configure columns"
            >
                <svg className="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </button>

            {open && (
                <div className="absolute right-0 mt-2 w-64 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 z-50 p-3">
                    <div className="flex items-center justify-between mb-2">
                        <span className="text-sm font-bold text-gray-700 dark:text-gray-200">Columns</span>
                        <button
                            type="button"
                            onClick={resetColumns}
                            className="text-xs text-blue-500 hover:underline"
                        >
                            Reset
                        </button>
                    </div>

                    <div className="text-xs text-gray-400 uppercase tracking-wider mb-1 mt-2">System fields</div>
                    {systemCols.map((col) => (
                        <label key={col.key} className="flex items-center gap-2 py-1 px-1 rounded hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={visibleKeys.includes(col.key)}
                                onChange={() => toggleColumn(col.key)}
                                className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span className="text-sm text-gray-700 dark:text-gray-300">{col.label}</span>
                        </label>
                    ))}

                    {extraCols.length > 0 && (
                        <>
                            <div className="text-xs text-gray-400 uppercase tracking-wider mb-1 mt-3">Import data</div>
                            {extraCols.map((col) => (
                                <label key={col.key} className="flex items-center gap-2 py-1 px-1 rounded hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={visibleKeys.includes(col.key)}
                                        onChange={() => toggleColumn(col.key)}
                                        className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span className="text-sm text-gray-700 dark:text-gray-300">{col.label}</span>
                                    <span className="text-xs text-gray-400 ml-auto">{col.key}</span>
                                </label>
                            ))}
                        </>
                    )}
                </div>
            )}
        </div>
    );
}

// ─── status badge ─────────────────────────────────────────────────────────────

function StatusBadge({ status }) {
    if (status === 'DONE') {
        return (
            <span className="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-bold bg-green-200 text-green-800 dark:bg-green-800 dark:text-green-200">
                Done
            </span>
        );
    }
    if (status === 'IN_PROGRESS') {
        return (
            <span className="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-bold bg-blue-200 text-blue-800 dark:bg-blue-800 dark:text-blue-200 animate-pulse">
                In Progress
            </span>
        );
    }
    if (status === 'BLOCKED') {
        return (
            <span className="px-3 py-1 rounded-full text-sm font-bold bg-red-200 text-red-800">
                Blocked
            </span>
        );
    }
    return (
        <span className="px-3 py-1 rounded-full text-sm font-bold bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-300">
            {statusLabel(status)}
        </span>
    );
}

// ─── row ─────────────────────────────────────────────────────────────────────

function WorkOrderRow({ wo, allColumns, visibleKeys, lineShifts, shiftEntries, qtyEditPolicy, qtyEditWindowMinutes, onStart, onComplete, onInfo, onReport, labelTemplates = [] }) {
    const isDone = wo.status === 'DONE';
    const isActive = wo.status === 'IN_PROGRESS';
    const planned = parseFloat(wo.planned_qty ?? 0);
    const produced = parseFloat(wo.produced_qty ?? 0);
    const remaining = Math.max(0, planned - produced);

    const handleRowClick = () => {
        if (isDone) return;
        if (!isActive) {
            onStart({
                open: true,
                id: wo.id,
                orderNo: wo.order_no,
                product: wo.product_type?.name ?? wo.order_no,
                qty: planned,
            });
        } else {
            onComplete({
                open: true,
                id: wo.id,
                orderNo: wo.order_no,
                product: wo.product_type?.name ?? wo.order_no,
                planned,
                produced,
            });
        }
    };

    const rowClass = [
        'border-b-2 border-gray-400 dark:border-gray-500 transition-colors border-l-4',
        isDone
            ? 'bg-green-100 dark:bg-green-900/30 border-l-green-500'
            : isActive
            ? 'bg-blue-100 dark:bg-blue-900/30 border-l-blue-500'
            : wo.status === 'BLOCKED'
            ? 'bg-red-50 dark:bg-red-900/20 border-l-red-500'
            : 'hover:bg-gray-50 dark:hover:bg-gray-800/50 border-l-transparent',
        !isDone ? 'cursor-pointer active:bg-gray-100 dark:active:bg-gray-700' : '',
    ]
        .join(' ')
        .trim();

    return (
        <tr className={rowClass} onClick={handleRowClick}>
            {allColumns.map((col) =>
                visibleKeys.includes(col.key) ? (
                    <td key={col.key} className="px-3 py-3 text-sm text-gray-800 dark:text-gray-200">
                        {col.key === 'status' ? (
                            <StatusBadge status={wo.status} />
                        ) : (
                            getCellValue(wo, col)
                        )}
                    </td>
                ) : null,
            )}

            {/* To Produce */}
            <td className="px-3 py-3 text-center font-bold text-gray-800 dark:text-gray-200 border-l-2 border-gray-200 dark:border-gray-600 tabular-nums">
                {fmt(planned)}
            </td>

            {/* Produced */}
            <td className="px-3 py-3 text-center font-semibold text-gray-700 dark:text-gray-300 tabular-nums">
                {fmt(produced)}
            </td>

            {/* Remaining */}
            <td className={`px-3 py-3 text-center font-bold tabular-nums ${
                remaining <= 0
                    ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'
                    : 'bg-blue-600 text-white'
            }`}>
                {fmt(remaining)}
            </td>

            {/* Shift cells */}
            {lineShifts.map((shift) => (
                <ShiftCell
                    key={shift.id}
                    wo={wo}
                    shift={shift}
                    shiftEntries={shiftEntries}
                    qtyEditPolicy={qtyEditPolicy}
                    qtyEditWindowMinutes={qtyEditWindowMinutes}
                />
            ))}

            {/* Actions */}
            <td className="px-2 py-3 text-center" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-center justify-center gap-3">
                    {!isDone && (
                        <button
                            type="button"
                            onClick={() =>
                                onComplete({
                                    open: true,
                                    id: wo.id,
                                    orderNo: wo.order_no,
                                    product: wo.product_type?.name ?? wo.order_no,
                                    planned,
                                    produced,
                                })
                            }
                            className="w-10 h-10 flex items-center justify-center rounded-full bg-blue-600 hover:bg-blue-700 text-white text-lg font-bold shadow transition-colors"
                            title="Add produced quantity"
                        >
                            +
                        </button>
                    )}
                    <button
                        type="button"
                        onClick={() => onReport({ woId: wo.id, woNo: wo.order_no })}
                        className="w-10 h-10 flex items-center justify-center rounded-full bg-red-500 hover:bg-red-600 text-white text-lg font-bold shadow transition-colors"
                        title="Report problem"
                    >
                        !
                    </button>
                    <button
                        type="button"
                        onClick={() =>
                            onInfo({
                                orderNo: wo.order_no,
                                product: wo.product_type?.name ?? '-',
                                line: wo.line?.name ?? '-',
                                status: statusLabel(wo.status),
                                planned: fmt(planned),
                                produced: fmt(produced),
                                remaining: fmt(remaining),
                                priority: wo.priority ?? '-',
                                dueDate: wo.due_date ? wo.due_date.substring(0, 10) : '-',
                                description: wo.description ?? '-',
                            })
                        }
                        className="w-10 h-10 flex items-center justify-center rounded-full bg-gray-400 hover:bg-gray-500 text-white text-sm font-bold shadow transition-colors"
                        title="Details"
                    >
                        ?
                    </button>
                    {labelTemplates.some((t) => t.type === 'work_order') && (
                        <LabelPrintMenu kind="work-order" id={wo.id} templates={labelTemplates} label="Label" iconOnly />
                    )}
                </div>
            </td>
        </tr>
    );
}

// ─── main page ───────────────────────────────────────────────────────────────

export default function Workstation() {
    const {
        workOrders = [],
        line,
        availableWeeks = [],
        weekFilter,
        search: searchProp,
        issueTypes = [],
        allColumns = [],
        shifts = [],
        shiftEntries = {},
        qtyEditPolicy = 'none',
        qtyEditWindowMinutes = 60,
        labelTemplates = [],
    } = usePage().props;

    const { visibleKeys, toggleColumn, resetColumns } = useVisibleColumns(allColumns, line?.id ?? 0);

    const [searchVal, setSearchVal] = useState(searchProp ?? '');

    // Modals
    const [startModal, setStartModal] = useState({ open: false });
    const [completeModal, setCompleteModal] = useState({ open: false });
    const [infoModal, setInfoModal] = useState(null);   // null = closed, obj = open
    const [reportModal, setReportModal] = useState(null); // null = closed, obj = open

    // Only show shift columns for shifts belonging to this line
    const lineShifts = shifts.filter((s) => s.line_id === line?.id || String(s.line_id) === String(line?.id));
    const hasShifts = lineShifts.length > 0;

    const handleSearch = (e) => {
        e.preventDefault();
        const params = {};
        if (searchVal) params.search = searchVal;
        if (weekFilter && weekFilter !== 'all') params.week = weekFilter;
        router.get('/operator/workstation', params);
    };

    const weekUrl = (wk) => {
        const params = new URLSearchParams();
        if (wk && wk !== 'all') params.set('week', wk);
        if (searchProp) params.set('search', searchProp);
        const qs = params.toString();
        return '/operator/workstation' + (qs ? '?' + qs : '');
    };

    return (
        <>
            <Head title={`Workstation — ${line?.name ?? ''}`} />

            <LineSync lineId={line?.id} reloadOnly={['workOrders', 'shiftEntries']} />

            <div className="max-w-full mx-auto px-2 sm:px-4">
                {/* Header */}
                <div className="mb-4">
                    <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-3">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-800 dark:text-white">{line?.name}</h1>
                        </div>
                        <div className="flex items-center gap-2">
                            {/* Mode toggle */}
                            <div className="flex items-center bg-gray-100 dark:bg-gray-800 rounded-lg p-1 gap-1">
                                <Link
                                    href="/operator/queue"
                                    className="px-3 py-1.5 rounded-md text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 transition-all"
                                >
                                    Queue
                                </Link>
                                <span className="px-3 py-1.5 rounded-md text-sm font-medium bg-white dark:bg-gray-700 shadow text-blue-600 dark:text-blue-400">
                                    Workstation
                                </span>
                            </div>

                            <ColumnPicker
                                allColumns={allColumns}
                                visibleKeys={visibleKeys}
                                toggleColumn={toggleColumn}
                                resetColumns={resetColumns}
                            />

                            <Link
                                href="/operator/select-line"
                                className="px-4 py-2 rounded-lg text-sm font-medium bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 transition-colors"
                            >
                                Change Line
                            </Link>
                        </div>
                    </div>

                    {/* Week filter */}
                    {availableWeeks.length > 0 && (
                        <div className="flex flex-wrap items-center gap-2 mb-3">
                            <svg className="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span className="text-sm text-gray-500 dark:text-gray-400">Select week:</span>

                            <a
                                href={weekUrl('all')}
                                className={`px-4 py-2 rounded-full text-sm font-medium border-2 transition-colors ${
                                    !weekFilter || weekFilter === 'all'
                                        ? 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-500 text-gray-700 dark:text-gray-200 shadow-sm'
                                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'
                                }`}
                            >
                                All weeks
                            </a>

                            {availableWeeks.map((wk) => (
                                <a
                                    key={wk}
                                    href={weekUrl(wk)}
                                    className={`px-4 py-2 rounded-full text-sm font-medium border-2 transition-colors ${
                                        String(weekFilter) === String(wk)
                                            ? 'bg-blue-50 dark:bg-blue-900/30 border-blue-500 text-blue-700 dark:text-blue-300 shadow-sm'
                                            : 'border-transparent text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'
                                    }`}
                                >
                                    {weekLabel(wk)}
                                </a>
                            ))}
                        </div>
                    )}

                    {/* Action buttons (disabled stubs) */}
                    <div className="flex gap-2 mb-3">
                        <button
                            type="button"
                            disabled
                            className="px-5 py-2.5 rounded-lg text-sm font-bold bg-yellow-400 text-yellow-900 opacity-60 cursor-not-allowed"
                        >
                            Cleaning
                        </button>
                        <button
                            type="button"
                            disabled
                            className="px-5 py-2.5 rounded-lg text-sm font-bold bg-red-500 text-white opacity-60 cursor-not-allowed"
                        >
                            Failure
                        </button>
                    </div>

                    <p className="text-xs text-gray-400 dark:text-gray-500 mb-2">
                        Click a row to change production status. Use &quot;Z1&quot; or &quot;Z2&quot; columns to enter produced quantities per shift.
                    </p>
                </div>

                {/* Search */}
                <form onSubmit={handleSearch} className="mb-4">
                    <input
                        type="text"
                        value={searchVal}
                        onChange={(e) => setSearchVal(e.target.value)}
                        placeholder="Search by order number, product or data..."
                        className="w-full sm:w-96 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-400 outline-none"
                        autoComplete="off"
                    />
                </form>

                {/* Table */}
                {workOrders.length === 0 ? (
                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow text-center py-16">
                        <p className="text-gray-500 text-lg">No work orders found</p>
                    </div>
                ) : (
                    <div className="bg-white dark:bg-gray-900 rounded-xl shadow overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm border-collapse border-2 border-gray-400 dark:border-gray-500">
                                <thead>
                                    <tr className="bg-gray-100 dark:bg-gray-800 border-b-2 border-gray-300 dark:border-gray-600">
                                        {allColumns.map((col) =>
                                            visibleKeys.includes(col.key) ? (
                                                <th
                                                    key={col.key}
                                                    className="px-3 py-3 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider whitespace-nowrap"
                                                >
                                                    {col.label}
                                                </th>
                                            ) : null,
                                        )}
                                        <th className="px-3 py-3 text-center text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider border-l-2 border-gray-300 dark:border-gray-600">
                                            To Produce
                                        </th>
                                        <th className="px-3 py-3 text-center text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                                            Produced
                                        </th>
                                        <th className="px-3 py-3 text-center text-xs font-bold uppercase tracking-wider bg-blue-600 text-white">
                                            Remaining
                                        </th>
                                        {hasShifts &&
                                            lineShifts.map((shift) => (
                                                <th
                                                    key={shift.id}
                                                    className="px-3 py-3 text-center text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider"
                                                    title={`${shift.name} (${(shift.start_time ?? '').substring(0, 5)}–${(shift.end_time ?? '').substring(0, 5)})`}
                                                >
                                                    {shift.code}
                                                </th>
                                            ))}
                                        <th className="px-3 py-3 w-10" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {workOrders.map((wo) => (
                                        <WorkOrderRow
                                            key={wo.id}
                                            wo={wo}
                                            allColumns={allColumns}
                                            visibleKeys={visibleKeys}
                                            lineShifts={hasShifts ? lineShifts : []}
                                            shiftEntries={shiftEntries}
                                            qtyEditPolicy={qtyEditPolicy}
                                            qtyEditWindowMinutes={qtyEditWindowMinutes}
                                            onStart={setStartModal}
                                            onComplete={setCompleteModal}
                                            onInfo={setInfoModal}
                                            onReport={setReportModal}
                                            labelTemplates={labelTemplates}
                                        />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>

            {/* Modals */}
            <StartModal modal={startModal} onClose={() => setStartModal({ open: false })} />
            <CompleteModal modal={completeModal} onClose={() => setCompleteModal({ open: false })} />
            <InfoModal info={infoModal} onClose={() => setInfoModal(null)} />
            {issueTypes.length > 0 && (
                <ReportModal
                    report={reportModal}
                    issueTypes={issueTypes}
                    onClose={() => setReportModal(null)}
                />
            )}
        </>
    );
}

Workstation.layout = (page) => <OperatorLayout>{page}</OperatorLayout>;
