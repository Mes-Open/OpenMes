// Geist White restyle: light-only v1 — om-* tokens + @openmes/ui (balance/discrepancy data and close-shift post untouched).
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Badge, Button, InlineAlert } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import { __ } from '../../../lib/i18n';

// Backend discrepancy severities → InlineAlert severities.
const ALERT_SEVERITY = {
    danger: 'error',
    warning: 'warning',
    info: 'info',
};

function Metric({ label, value, sub, accent }) {
    return (
        <div className="bg-om-card border border-om-line rounded-om p-5 text-center">
            <p className={`font-mono text-[27px] font-medium leading-none tracking-[-0.02em] ${accent ?? 'text-om-ink'}`}>{value}</p>
            <p className="mt-2 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{label}</p>
            {sub && <p className="text-[11px] text-om-faint mt-0.5">{sub}</p>}
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
                    <h1 className="text-[26px] font-semibold tracking-[-0.02em] text-om-ink">{__('Shift Handover')}</h1>
                    <p className="text-sm text-om-muted mt-1">
                        {shift
                            ? `${shift.name} (${shift.start}–${shift.end})`
                            : __('No shift configured — using default window')}
                        {balance?.window?.business_date ? ` · ${balance.window.business_date}` : ''}
                    </p>
                </div>
                <div>
                    <label className="block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-1">
                        {__('Line')}
                    </label>
                    <select
                        value={selectedLineId ?? ''}
                        onChange={(e) => onLineChange(e.target.value)}
                        className="h-9 min-w-[180px] rounded-om-sm border border-om-line bg-om-card px-3 text-[13px] text-om-ink focus:border-om-accent focus:outline-none"
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
                <Metric label={__('Produced')} value={balance?.produced_qty ?? 0} accent="text-om-accent" />
                <Metric label={__('Scrap')} value={balance?.scrap_qty ?? 0} accent="text-om-blocked" />
                <Metric label={__('Good')} value={balance?.good_qty ?? 0} accent="text-om-running" />
                <Metric label={__('Packed')} value={balance?.packed_qty ?? 0} accent="text-om-ink" />
                <Metric
                    label={__('WIP')}
                    value={balance?.wip_total_qty ?? 0}
                    sub={`${balance?.wip_open_pallets_qty ?? 0} ${__('Open pallets')} + ${balance?.wip_unpacked_qty ?? 0} ${__('Unpacked')}`}
                    accent="text-om-downtime"
                />
                <Metric label={__('Shipped')} value={balance?.shipped_qty ?? 0} accent="text-om-muted" />
            </div>

            {/* Discrepancies */}
            {discrepancies.length > 0 && (
                <div className="space-y-2 mb-6">
                    {discrepancies.map((d, i) => (
                        <InlineAlert key={i} severity={ALERT_SEVERITY[d.severity] ?? 'info'} title={d.label}>
                            <span className="font-mono text-[13px] font-medium text-om-ink">{d.value}</span>
                        </InlineAlert>
                    ))}
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                {/* Open pallets (WIP detail) */}
                <div className="bg-om-card border border-om-line rounded-om overflow-hidden">
                    <div className="px-4 py-3 border-b border-om-line2 flex items-center justify-between">
                        <h2 className="text-[14px] font-semibold text-om-ink">{__('Open pallets')}</h2>
                        <Badge variant="neutral">{balance?.wip_open_pallets_count ?? 0}</Badge>
                    </div>
                    {(!balance?.open_pallets || balance.open_pallets.length === 0) ? (
                        <div className="px-4 py-6 text-center text-om-faint text-sm">{__('No open pallets')}</div>
                    ) : (
                        <div className="max-h-64 overflow-y-auto">
                            {balance.open_pallets.map((p) => (
                                <div key={p.id} className="px-4 py-2 flex items-center justify-between border-b border-om-line2 last:border-0 hover:bg-om-bg text-sm">
                                    <span className="font-mono text-[12px] font-medium text-om-accent">{p.pallet_no}</span>
                                    <span className="font-mono text-[12px] text-om-muted">{p.order_no}</span>
                                    <span className="font-mono text-[13px] font-medium text-om-ink">{p.qty} {__('pcs')}</span>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Close shift */}
                <form onSubmit={submit} className="bg-om-card border border-om-line rounded-om p-5">
                    <h2 className="text-[14px] font-semibold text-om-ink mb-3">{__('Close shift')}</h2>
                    <label className="block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-1.5">{__('Notes')}</label>
                    <textarea
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                        rows={4}
                        className="w-full rounded-om-sm border border-om-line bg-om-card px-3 py-2 text-sm text-om-ink placeholder:text-om-faint focus:outline-none focus:border-om-accent"
                        placeholder={__('Handover notes (optional)')}
                    />
                    <p className="text-xs text-om-faint mt-2">
                        {__('Confirming saves an immutable audit snapshot of the figures above.')}
                    </p>
                    <Button
                        type="submit"
                        variant="accent"
                        disabled={form.processing}
                        className="mt-3 w-full"
                    >
                        {form.processing ? __('Saving…') : __('Confirm & close shift')}
                    </Button>
                </form>
            </div>

            {/* Audit history */}
            <div className="bg-om-card border border-om-line rounded-om overflow-hidden">
                <div className="px-4 py-3 border-b border-om-line2">
                    <h2 className="text-[14px] font-semibold text-om-ink">{__('Recent handovers')}</h2>
                </div>
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead className="bg-om-panel">
                            <tr>
                                {['Shift', 'Line', 'Produced', 'Good', 'Packed', 'Shipped', 'Confirmed by', 'Confirmed at'].map((h) => (
                                    <th key={h} className="px-4 py-2 text-left font-mono text-[9px] font-normal uppercase tracking-[0.08em] text-om-faint">{__(h)}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-om-line2">
                            {recent.length === 0 ? (
                                <tr><td colSpan={8} className="px-4 py-6 text-center text-om-faint">{__('No handovers yet')}</td></tr>
                            ) : recent.map((h) => (
                                <tr key={h.id} className="hover:bg-om-bg">
                                    <td className="px-4 py-2.5 whitespace-nowrap text-om-muted">{h.shift_start}</td>
                                    <td className="px-4 py-2.5 text-om-ink">{h.line_name ?? '—'}</td>
                                    <td className="px-4 py-2.5 font-mono text-[13px] text-om-ink">{h.produced_qty}</td>
                                    <td className="px-4 py-2.5 font-mono text-[13px] text-om-ink">{h.good_qty}</td>
                                    <td className="px-4 py-2.5 font-mono text-[13px] text-om-ink">{h.packed_qty}</td>
                                    <td className="px-4 py-2.5 font-mono text-[13px] text-om-ink">{h.shipped_qty}</td>
                                    <td className="px-4 py-2.5 text-om-muted">{h.confirmed_by ?? '—'}</td>
                                    <td className="px-4 py-2.5 whitespace-nowrap text-om-faint">{h.confirmed_at}</td>
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
