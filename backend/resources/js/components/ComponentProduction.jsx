import { Link } from '@inertiajs/react';
import { __, formatNumber, formatDateTime } from '../lib/i18n';

/** Frozen part requirements shared by planner and operator order details. */
export default function ComponentProduction({ workOrder, basePath = '/admin/work-orders' }) {
    const rows = workOrder.component_production ?? [];
    const spec = workOrder.extra_data?.component_specification;
    if (!rows.length && !spec) return null;
    const reservationLabel = (status) => ({ held: __('Reserved'), issued: __('Issued to production'), released: __('Reservation released') }[status] ?? status);
    const qty = (value) => formatNumber(Number(value ?? 0), { maximumFractionDigits: 4 });
    const attributes = (part) => {
        const data = part?.extra_data ?? {};
        return [data.foam_grade, ...['length_mm', 'width_mm', 'thickness_mm'].map((key) => data[key] ? `${qty(data[key])} mm` : null)].filter(Boolean).join(' · ');
    };
    return (
        <section className="rounded-xl border border-om-line bg-om-card p-5 space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-[15px] font-semibold text-om-ink">{__('Component production')}</h2>
                {workOrder.parent_work_order_id && basePath && <Link className="text-sm text-om-accent hover:underline" href={`${basePath}/${workOrder.parent_work_order_id}`}>{__('Parent work order')}</Link>}
            </div>
            {spec && <p className="text-sm text-om-muted">{spec.material_code} · {spec.material_name} {attributes(spec)}</p>}
            {rows.length > 0 && <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                    <thead className="text-xs text-om-muted"><tr>
                        {['Component', 'Required', 'From stock', 'Good quantity', 'Scrap', 'Remaining', 'Work order'].map((label) => <th key={label} className="p-2 font-medium">{__(label)}</th>)}
                    </tr></thead>
                    <tbody>{rows.map((row) => <tr key={row.id} className="border-t border-om-line">
                        <td className="p-2" style={{ paddingLeft: `${8 + (row.path.split('/').length - 1) * 16}px` }}>
                            <div className="font-medium text-om-ink">{row.specification.material_code} · {row.specification.material_name}</div>
                            <div className="text-xs text-om-muted">{attributes(row.specification)}</div>
                        </td>
                        <td className="p-2 tabular-nums">{qty(row.required_qty)} {row.specification.unit_of_measure}</td>
                        <td className="p-2 tabular-nums">{qty(row.covered_stock_qty ?? 0)} / {qty(row.stock_qty ?? 0)}
                            {row.covered_stock_qty < row.stock_qty && <div className="text-xs text-om-blocked">{__('Reserved component stock is no longer available.')}</div>}
                            {(row.stock_reservations ?? []).map((reservation) => <div key={reservation.id} className="text-xs text-om-muted">
                                {reservation.warehouse_code} · {qty(reservation.quantity)} · {reservationLabel(reservation.status)}
                                {reservation.stock_document_id && basePath === '/admin/work-orders' && <Link className="block text-om-accent hover:underline" href={`/admin/stock-documents/${reservation.stock_document_id}`}>{__('Stock document')} #{reservation.stock_document_id}</Link>}
                            </div>)}
                        </td>
                        <td className="p-2 tabular-nums">{row.child_work_order_id ? qty(row.good_qty) : '—'}</td>
                        <td className="p-2 tabular-nums">{row.child_work_order_id ? qty(row.scrap_qty) : '—'}</td>
                        <td className="p-2 tabular-nums">{row.child_work_order_id || row.stock_qty > 0 ? qty(row.remaining_qty) : '—'}</td>
                        <td className="p-2">
                            {row.needed_at && <div className="text-xs text-om-muted">{__('Needed at')}: {formatDateTime(row.needed_at)}</div>}
                            {row.schedule_status === 'late' && <div className="text-xs text-om-blocked">{__('Component production is scheduled too late.')}</div>}
                            {row.schedule_status === 'unscheduled' && row.remaining_qty > 0 && <div className="text-xs text-om-muted">{__('Production start is not scheduled.')}</div>}
                            {row.child_work_order_id ? <>
                                {basePath ? <Link className="text-om-accent hover:underline" href={`${basePath}/${row.child_work_order_id}`}>{row.order_no}</Link> : row.order_no}
                                <div className="text-xs text-om-muted">{row.operation || (row.ready ? __('Ready for assembly') : __('Component production incomplete'))}</div>
                            </> : <span className="text-om-muted">{row.stock_qty > 0 ? __('From stock') : __('Material input')}</span>}
                        </td>
                    </tr>)}</tbody>
                </table>
            </div>}
        </section>
    );
}
