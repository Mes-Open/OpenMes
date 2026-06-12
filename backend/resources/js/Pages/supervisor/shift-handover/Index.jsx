import { Head, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import { __ } from '../../../lib/i18n';

const SEVERITY = {
    danger: 'bg-red-50 border-red-300 text-red-800 dark:bg-red-900/20 dark:text-red-300',
    warning: 'bg-amber-50 border-amber-300 text-amber-800 dark:bg-amber-900/20 dark:text-amber-300',
    info: 'bg-blue-50 border-blue-300 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300',
};

function Metric({ label, value, sub, accent }) {
    return (
        <div className="card text-center">
            <p className={`text-3xl font-extrabold ${accent ?? 'text-gray-800 dark:text-gray-100'}`}>{value}</p>
            <p className="text-xs text-gray-500 mt-1">{label}</p>
            {sub && <p className="text-[11px] text-gray-400 mt-0.5">{sub}</p>}
        </div>
    );
}

export default function ShiftHandoverIndex() {
    const { lines = [], selectedLineId = null, balance, recent = [] } = usePage().props;
    const form = useForm({ line_id: selectedLineId ?? '', notes: '' });

    const onLineChange = (value) => {
        router.get('/supervisor/shift-handover', value ? { line_id: value } : {}, {
            preserveScroll: true,
            preserveState: false,
        });
    };

    const submit = (e) => {
        e.preventDefault();
        if (!confirm(__('Confirm & close shift') + '?')) return;
        form.transform((data) => ({ ...data, line_id: selectedLineId ?? '' }));
        form.post('/supervisor/shift-handover', { preserveScroll: true });
    };

    const shift = balance?.shift;
    const discrepancies = balance?.discrepancies ? Object.values(balance.discrepancies) : [];

    return (
        <div className="max-w-7xl mx-auto">
            <Head title="Shift Handover" />

            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
                <div>
                    <h1 className="text-3xl font-bold text-gray-800 dark:text-white">{__('Shift Handover')}</h1>
                    <p className="text-sm text-gray-500 mt-1">
                        {shift
                            ? `${shift.name} (${shift.start}–${shift.end})`
                            : __('No shift configured — using default window')}
                        {balance?.window?.business_date ? ` · ${balance.window.business_date}` : ''}
                    </p>
                </div>
                <div>
                    <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
                        {__('Line')}
                    </label>
                    <select
                        value={selectedLineId ?? ''}
                        onChange={(e) => onLineChange(e.target.value)}
                        className="form-input"
                    >
                        <option value="">{__('All lines')}</option>
                        {lines.map((l) => (
                            <option key={l.id} value={String(l.id)}>{l.name}</option>
                        ))}
                    </select>
                </div>
            </div>

            {/* Balance */}
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                <Metric label={__('Produced')} value={balance?.produced_qty ?? 0} accent="text-indigo-600 dark:text-indigo-400" />
                <Metric label={__('Scrap')} value={balance?.scrap_qty ?? 0} accent="text-red-500" />
                <Metric label={__('Good')} value={balance?.good_qty ?? 0} accent="text-emerald-600 dark:text-emerald-400" />
                <Metric label={__('Packed')} value={balance?.packed_qty ?? 0} accent="text-blue-600 dark:text-blue-400" />
                <Metric
                    label={__('WIP')}
                    value={balance?.wip_total_qty ?? 0}
                    sub={`${balance?.wip_open_pallets_qty ?? 0} ${__('Open pallets')} + ${balance?.wip_unpacked_qty ?? 0} ${__('Unpacked')}`}
                    accent="text-amber-600 dark:text-amber-400"
                />
                <Metric label={__('Shipped')} value={balance?.shipped_qty ?? 0} accent="text-gray-700 dark:text-gray-200" />
            </div>

            {/* Discrepancies */}
            {discrepancies.length > 0 && (
                <div className="space-y-2 mb-6">
                    {discrepancies.map((d, i) => (
                        <div key={i} className={`flex items-center justify-between px-4 py-2.5 rounded-lg border text-sm ${SEVERITY[d.severity] ?? SEVERITY.info}`}>
                            <span className="font-medium">{d.label}</span>
                            <span className="font-bold">{d.value}</span>
                        </div>
                    ))}
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                {/* Open pallets (WIP detail) */}
                <div className="card overflow-hidden p-0">
                    <div className="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <h2 className="font-semibold text-gray-700 dark:text-gray-200 text-sm uppercase tracking-wide">{__('Open pallets')}</h2>
                        <span className="text-xs text-gray-400">{balance?.wip_open_pallets_count ?? 0}</span>
                    </div>
                    {(!balance?.open_pallets || balance.open_pallets.length === 0) ? (
                        <div className="px-4 py-6 text-center text-gray-400 text-sm">{__('No open pallets')}</div>
                    ) : (
                        <div className="max-h-64 overflow-y-auto">
                            {balance.open_pallets.map((p) => (
                                <div key={p.id} className="px-4 py-2 flex items-center justify-between border-b border-gray-100 dark:border-gray-700/50 text-sm">
                                    <span className="font-mono font-semibold text-indigo-600 dark:text-indigo-400">{p.pallet_no}</span>
                                    <span className="text-gray-500">{p.order_no}</span>
                                    <span className="font-semibold">{p.qty} {__('pcs')}</span>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Close shift */}
                <form onSubmit={submit} className="card">
                    <h2 className="font-semibold text-gray-700 dark:text-gray-200 text-sm uppercase tracking-wide mb-3">{__('Close shift')}</h2>
                    <label className="block text-sm text-gray-600 dark:text-gray-300 mb-1">{__('Notes')}</label>
                    <textarea
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                        rows={4}
                        className="form-input w-full"
                        placeholder={__('Handover notes (optional)')}
                    />
                    <p className="text-xs text-gray-400 mt-2">
                        {__('Confirming saves an immutable audit snapshot of the figures above.')}
                    </p>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="mt-3 w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-lg text-sm font-semibold bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50"
                    >
                        {form.processing ? __('Saving…') : __('Confirm & close shift')}
                    </button>
                </form>
            </div>

            {/* Audit history */}
            <div className="card overflow-hidden p-0">
                <div className="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h2 className="font-semibold text-gray-700 dark:text-gray-200 text-sm uppercase tracking-wide">{__('Recent handovers')}</h2>
                </div>
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead className="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                {['Shift', 'Line', 'Produced', 'Good', 'Packed', 'Shipped', 'Confirmed by', 'Confirmed at'].map((h) => (
                                    <th key={h} className="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">{__(h)}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-gray-700/50">
                            {recent.length === 0 ? (
                                <tr><td colSpan={8} className="px-4 py-6 text-center text-gray-400">{__('No handovers yet')}</td></tr>
                            ) : recent.map((h) => (
                                <tr key={h.id}>
                                    <td className="px-4 py-2.5 whitespace-nowrap text-gray-600">{h.shift_start}</td>
                                    <td className="px-4 py-2.5">{h.line_name ?? '—'}</td>
                                    <td className="px-4 py-2.5">{h.produced_qty}</td>
                                    <td className="px-4 py-2.5">{h.good_qty}</td>
                                    <td className="px-4 py-2.5">{h.packed_qty}</td>
                                    <td className="px-4 py-2.5">{h.shipped_qty}</td>
                                    <td className="px-4 py-2.5">{h.confirmed_by ?? '—'}</td>
                                    <td className="px-4 py-2.5 whitespace-nowrap text-gray-500">{h.confirmed_at}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}

ShiftHandoverIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
