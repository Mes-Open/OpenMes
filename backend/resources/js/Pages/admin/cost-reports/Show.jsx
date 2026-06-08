import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import { __, formatNumber, formatDateTime } from '../../../lib/i18n';

const PAY_TYPE_LABELS = {
    hourly: 'Hourly',
    weekly: 'Weekly',
    piece_rate: 'Piece rate',
};

function money(value, currency) {
    if (value == null) return '—';
    return `${formatNumber(value)} ${currency}`;
}

export default function CostReportShow() {
    const { breakdown, meta = {} } = usePage().props;
    const b = breakdown;
    const currency = b.currency;

    return (
        <>
            <Head title={`${__('Production Cost')} · ${b.order_no}`} />
            <div className="max-w-5xl mx-auto space-y-6">
                <div>
                    <Link href="/admin/cost-reports" className="text-sm text-blue-600 hover:underline">
                        ← {__('Production Cost Report')}
                    </Link>
                    <h1 className="text-3xl font-bold text-gray-800 dark:text-gray-100 mt-1">{b.order_no}</h1>
                    <p className="text-gray-600 dark:text-gray-400">
                        {meta.product_name ?? '—'} · {meta.line_name ?? '—'}
                        {meta.completed_at && <> · {formatDateTime(meta.completed_at)}</>}
                    </p>
                </div>

                {b.mixed_currency && (
                    <div className="rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-2">
                        {__('Mixed currencies - totals are summed without conversion.')}
                    </div>
                )}

                {/* Headline */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <Card label={__('Total cost')} value={money(b.total_cost, currency)} strong />
                    <Card label={__('Cost per unit')} value={b.cost_per_unit == null ? '—' : money(b.cost_per_unit, currency)} />
                    <Card label={__('Produced')} value={formatNumber(b.produced_qty)} />
                    <Card
                        label={`${__('Materials')} / ${__('Labor')}`}
                        value={`${money(b.materials.total, currency)} / ${money(b.labor.total, currency)}`}
                    />
                </div>

                {/* Materials */}
                <Section title={__('Materials')} total={money(b.materials.total, currency)}>
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="text-left text-xs text-gray-500 uppercase border-b border-gray-200 dark:border-slate-700">
                                <th className="px-4 py-2">{__('Material')}</th>
                                <th className="px-4 py-2">{__('Source')}</th>
                                <th className="px-4 py-2 text-right">{__('Qty')}</th>
                                <th className="px-4 py-2 text-right">{__('Unit price')}</th>
                                <th className="px-4 py-2 text-right">{__('Line total')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-slate-700">
                            {b.materials.items.map((it, i) => (
                                <tr key={i}>
                                    <td className="px-4 py-2">
                                        {it.material_name ?? '—'}
                                        {it.material_code && <span className="text-xs text-gray-400 font-mono ml-1">{it.material_code}</span>}
                                    </td>
                                    <td className="px-4 py-2">
                                        <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${
                                            it.source === 'actual' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-700'
                                        }`}>
                                            {it.source === 'actual' ? __('Actual consumption') : __('BOM estimate')}
                                        </span>
                                    </td>
                                    <td className="px-4 py-2 text-right font-mono">{formatNumber(it.qty)}</td>
                                    <td className="px-4 py-2 text-right font-mono">{money(it.unit_price, it.currency)}</td>
                                    <td className="px-4 py-2 text-right font-mono">{money(it.line_total, it.currency)}</td>
                                </tr>
                            ))}
                            <EmptyRow items={b.materials.items} colSpan={5} />
                        </tbody>
                    </table>
                </Section>

                {/* Labor */}
                <Section title={__('Labor')} total={money(b.labor.total, currency)}>
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="text-left text-xs text-gray-500 uppercase border-b border-gray-200 dark:border-slate-700">
                                <th className="px-4 py-2">{__('Worker')}</th>
                                <th className="px-4 py-2">{__('Pay type')}</th>
                                <th className="px-4 py-2 text-right">{__('Basis')}</th>
                                <th className="px-4 py-2 text-right">{__('Rate')}</th>
                                <th className="px-4 py-2 text-right">{__('Line total')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-slate-700">
                            {b.labor.items.map((it, i) => (
                                <tr key={i}>
                                    <td className="px-4 py-2">
                                        {it.worker_name ?? '—'}
                                        {it.worker_code && <span className="text-xs text-gray-400 font-mono ml-1">{it.worker_code}</span>}
                                    </td>
                                    <td className="px-4 py-2">{__(PAY_TYPE_LABELS[it.pay_type] ?? it.pay_type)}</td>
                                    <td className="px-4 py-2 text-right font-mono">
                                        {formatNumber(it.basis)} {it.basis_unit === 'pcs' ? __('Pieces') : __('Hours')}
                                    </td>
                                    <td className="px-4 py-2 text-right font-mono">{money(it.rate, it.currency)}</td>
                                    <td className="px-4 py-2 text-right font-mono">{money(it.line_total, it.currency)}</td>
                                </tr>
                            ))}
                            <EmptyRow items={b.labor.items} colSpan={5} />
                        </tbody>
                    </table>
                </Section>

                {/* Additional costs */}
                <Section title={__('Additional costs')} total={money(b.additional.total, currency)}>
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="text-left text-xs text-gray-500 uppercase border-b border-gray-200 dark:border-slate-700">
                                <th className="px-4 py-2">{__('Description')}</th>
                                <th className="px-4 py-2 text-right">{__('Line total')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-slate-700">
                            {b.additional.items.map((it, i) => (
                                <tr key={i}>
                                    <td className="px-4 py-2">{it.description ?? '—'}</td>
                                    <td className="px-4 py-2 text-right font-mono">{money(it.line_total, it.currency)}</td>
                                </tr>
                            ))}
                            <EmptyRow items={b.additional.items} colSpan={2} />
                        </tbody>
                    </table>
                </Section>

                {/* Grand total */}
                <div className="bg-gray-800 dark:bg-slate-900 text-white rounded-lg p-4 flex items-center justify-between">
                    <span className="text-lg font-medium">{__('Total cost')}</span>
                    <span className="text-2xl font-bold font-mono">{money(b.total_cost, currency)}</span>
                </div>
            </div>
        </>
    );
}

function Section({ title, total, children }) {
    return (
        <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm overflow-x-auto">
            <div className="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-slate-700">
                <h2 className="font-semibold text-gray-800 dark:text-gray-100">{title}</h2>
                <span className="font-mono text-gray-700 dark:text-gray-300">{total}</span>
            </div>
            {children}
        </div>
    );
}

function Card({ label, value, strong }) {
    return (
        <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-4">
            <div className="text-xs text-gray-500 uppercase">{label}</div>
            <div className={`mt-1 ${strong ? 'text-2xl font-bold' : 'text-lg font-semibold'} text-gray-800 dark:text-gray-100`}>
                {value}
            </div>
        </div>
    );
}

function EmptyRow({ items, colSpan }) {
    if (items.length > 0) return null;
    return (
        <tr>
            <td colSpan={colSpan} className="px-4 py-6 text-center text-gray-400 text-sm">
                {__('No cost data for this work order.')}
            </td>
        </tr>
    );
}

CostReportShow.layout = (page) => <AppLayout>{page}</AppLayout>;
