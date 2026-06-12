import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import CustomFieldsDisplay from '../../../components/CustomFieldsDisplay';

const MOVEMENT_TYPE_COLORS = {
    receipt:    'text-om-running',
    return:     'text-om-accent',
    allocation: 'text-om-downtime',
    consume:    'text-om-muted',
    scrap:      'text-om-blocked',
    adjustment: 'text-purple-700',
};

const LOT_STATUS_COLORS = {
    released:   'bg-om-running-bg text-om-running',
    quarantine: 'bg-om-blocked-bg text-om-blocked',
    expired:    'bg-om-downtime-bg text-om-downtime',
};

function fmt(val, decimals = 3) {
    return Number(val ?? 0).toFixed(decimals);
}

export default function MaterialShow({ material, lots = [], recentMovements = [], customFields = [] }) {
    const available = material.available_quantity ?? 0;
    const minStock = material.min_stock_level ?? 0;
    const stockCardBorder = available < minStock ? 'border-red-400' : 'border-blue-400';

    return (
        <>
            <Head title={`Material — ${material.name}`} />

            {/* Breadcrumbs */}
            <nav className="text-sm text-om-muted mb-4 flex items-center gap-1">
                <Link href="/admin/dashboard" className="hover:underline">Dashboard</Link>
                <span>/</span>
                <Link href="/admin/materials" className="hover:underline">Materials</Link>
                <span>/</span>
                <span className="text-om-ink">{material.name}</span>
            </nav>

            <div className="max-w-5xl mx-auto">
                {/* Header */}
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-3xl font-bold text-om-ink">{material.name}</h1>
                            {material.is_active ? (
                                <span className="px-3 py-1 bg-om-running-bg text-om-running rounded-full text-sm font-medium">Active</span>
                            ) : (
                                <span className="px-3 py-1 bg-om-chip text-om-muted rounded-full text-sm font-medium">Inactive</span>
                            )}
                        </div>
                        <p className="text-sm text-om-muted mt-1 font-mono">{material.code}</p>
                    </div>
                    <Link href={`/admin/materials/${material.id}/edit`} className="btn-touch btn-secondary">Edit</Link>
                </div>

                {/* Details + Stock grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    {/* Details */}
                    <div className="card">
                        <h3 className="text-lg font-semibold mb-4">Details</h3>
                        <dl className="space-y-3">
                            <Row label="Type"     value={material.material_type?.name ?? '—'} />
                            <Row label="Unit"     value={material.unit_of_measure ?? '—'} />
                            <Row label="Tracking" value={ucFirst(material.tracking_type)} />
                            <Row label="Default Scrap %" value={`${material.default_scrap_percentage}%`} />
                        </dl>
                    </div>

                    {/* Stock */}
                    <div className={`card border-l-4 ${stockCardBorder}`}>
                        <h3 className="text-lg font-semibold mb-4">Stock breakdown</h3>
                        <dl className="space-y-2">
                            <div className="flex justify-between text-sm">
                                <dt className="text-om-muted">On hand</dt>
                                <dd className="font-mono">{fmt(material.stock_quantity)} {material.unit_of_measure}</dd>
                            </div>
                            <div className="flex justify-between text-sm">
                                <dt className="text-om-muted">Reserved by active batches</dt>
                                <dd className="font-mono text-om-downtime">{fmt(material.reserved_quantity)} {material.unit_of_measure}</dd>
                            </div>
                            <div className="flex justify-between text-sm pt-2 border-t border-om-line2">
                                <dt className="font-medium text-om-muted">Available</dt>
                                <dd className={`font-mono font-bold ${available <= 0 ? 'text-om-blocked' : 'text-om-running'}`}>
                                    {fmt(available)} {material.unit_of_measure}
                                </dd>
                            </div>
                            {material.min_stock_level != null && (
                                <div className="flex justify-between text-xs text-om-faint">
                                    <dt>Min stock level</dt>
                                    <dd className="font-mono">{fmt(material.min_stock_level)} {material.unit_of_measure}</dd>
                                </div>
                            )}
                            {material.unit_price != null && (
                                <div className="flex justify-between text-xs text-om-faint">
                                    <dt>Stock value</dt>
                                    <dd className="font-mono">
                                        {Number(material.stock_quantity * material.unit_price).toFixed(2)} {material.price_currency}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </div>

                    {/* External System */}
                    <div className="card">
                        <h3 className="text-lg font-semibold mb-4">External System</h3>
                        {material.external_code ? (
                            <dl className="space-y-3">
                                <Row label="System"        value={material.external_system} />
                                <Row label="External Code" value={<span className="font-mono">{material.external_code}</span>} />
                            </dl>
                        ) : (
                            <p className="text-sm text-om-muted">No external system linked.</p>
                        )}

                        {material.sources && material.sources.length > 0 && (
                            <>
                                <h4 className="text-sm font-semibold mt-4 mb-2">Additional Sources</h4>
                                {material.sources.map((src) => (
                                    <div key={src.id} className="p-2 bg-om-panel rounded mb-2 text-sm">
                                        <span className="font-medium">{src.integration_config?.system_name ?? 'Unknown'}</span>:{' '}
                                        <span className="font-mono">{src.external_code}</span>
                                    </div>
                                ))}
                            </>
                        )}
                    </div>
                </div>

                {/* Custom fields */}
                <div className="mb-6">
                    <CustomFieldsDisplay definitions={customFields} values={material.custom_fields ?? {}} />
                </div>

                {/* Lots */}
                {lots.length > 0 && (
                    <div className="card mb-6">
                        <h3 className="text-lg font-semibold mb-4">
                            Lots <span className="text-sm font-normal text-om-faint">({lots.length})</span>
                        </h3>
                        <table className="min-w-full divide-y divide-om-line2 text-sm">
                            <thead className="bg-om-panel">
                                <tr>
                                    <Th>Lot</Th>
                                    <Th>Supplier ref</Th>
                                    <Th right>Received</Th>
                                    <Th right>Available</Th>
                                    <Th>Expiry</Th>
                                    <Th center>Status</Th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-om-line2">
                                {lots.map((lot) => {
                                    const badge = LOT_STATUS_COLORS[lot.status] ?? 'bg-om-chip text-om-muted';
                                    const expiringSoon = lot.expiry_date && isExpiringSoon(lot.expiry_date);
                                    const expired = lot.is_expired;
                                    return (
                                        <tr key={lot.id} className={expired ? 'bg-om-blocked-bg' : ''}>
                                            <td className="px-3 py-2 font-mono">{lot.lot_number}</td>
                                            <td className="px-3 py-2 text-om-muted font-mono text-xs">{lot.supplier_lot_no ?? '—'}</td>
                                            <td className="px-3 py-2 text-right font-mono">{fmt(lot.quantity_received)}</td>
                                            <td className={`px-3 py-2 text-right font-mono ${lot.quantity_available <= 0 ? 'text-om-faint' : 'font-bold'}`}>
                                                {fmt(lot.quantity_available)}
                                            </td>
                                            <td className={`px-3 py-2 text-xs ${expiringSoon ? 'text-om-downtime font-semibold' : 'text-om-muted'}`}>
                                                {lot.expiry_date ? lot.expiry_date.substring(0, 10) : '—'}
                                                {expiringSoon && <span className="ml-1">&#x23F0;</span>}
                                            </td>
                                            <td className="px-3 py-2 text-center">
                                                <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${badge}`}>
                                                    {ucFirst(lot.status)}
                                                </span>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Recent stock movements */}
                {recentMovements.length > 0 && (
                    <div className="card mb-6">
                        <h3 className="text-lg font-semibold mb-4">Recent stock movements</h3>
                        <table className="min-w-full divide-y divide-om-line2 text-sm">
                            <thead className="bg-om-panel">
                                <tr>
                                    <Th>When</Th>
                                    <Th>Type</Th>
                                    <Th right>Delta</Th>
                                    <Th right>Balance</Th>
                                    <Th>Source</Th>
                                    <Th>Reason</Th>
                                    <Th>By</Th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-om-line2">
                                {recentMovements.map((mv, i) => {
                                    const qty = Number(mv.quantity ?? 0);
                                    const qtyColor = qty > 0 ? 'text-om-running' : qty < 0 ? 'text-om-blocked' : 'text-om-muted';
                                    const typeColor = MOVEMENT_TYPE_COLORS[mv.movement_type] ?? 'text-om-muted';
                                    return (
                                        <tr key={mv.id ?? i}>
                                            <td className="px-3 py-2 text-xs font-mono text-om-muted">
                                                {mv.performed_at ? mv.performed_at.substring(0, 16).replace('T', ' ') : '—'}
                                            </td>
                                            <td className="px-3 py-2">
                                                <span className={`font-medium ${typeColor}`}>{mv.movement_type}</span>
                                            </td>
                                            <td className={`px-3 py-2 text-right font-mono ${qtyColor}`}>
                                                {qty > 0 ? '+' : ''}{fmt(qty)}
                                            </td>
                                            <td className="px-3 py-2 text-right font-mono">{fmt(mv.balance_after)}</td>
                                            <td className="px-3 py-2 text-xs text-om-muted">
                                                {mv.source_type ? `${mv.source_type} #${mv.source_id}` : '—'}
                                            </td>
                                            <td className="px-3 py-2 text-xs text-om-muted truncate max-w-xs" title={mv.reason ?? ''}>
                                                {(mv.reason ?? '').substring(0, 60)}
                                            </td>
                                            <td className="px-3 py-2 text-xs text-om-muted">{mv.performed_by?.name ?? '—'}</td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* BOM usage */}
                {material.bom_items && material.bom_items.length > 0 && (
                    <div className="card">
                        <h3 className="text-lg font-semibold mb-4">
                            Used in BOM ({material.bom_items.length} templates)
                        </h3>
                        <table className="min-w-full divide-y divide-om-line2">
                            <thead className="bg-om-panel">
                                <tr>
                                    <Th>Template</Th>
                                    <Th>Product</Th>
                                    <Th right>Qty/Unit</Th>
                                    <Th right>Scrap %</Th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-om-line2">
                                {material.bom_items.map((item) => (
                                    <tr key={item.id}>
                                        <td className="px-4 py-2 text-sm">{item.process_template?.name ?? '—'}</td>
                                        <td className="px-4 py-2 text-sm">{item.process_template?.product_type?.name ?? '-'}</td>
                                        <td className="px-4 py-2 text-sm text-right">{item.quantity_per_unit}</td>
                                        <td className="px-4 py-2 text-sm text-right">{item.scrap_percentage}%</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

MaterialShow.layout = (page) => <AppLayout>{page}</AppLayout>;

/* ── Helpers ─────────────────────────────────────────────────────────────── */

function ucFirst(str) {
    if (!str) return '—';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function isExpiringSoon(dateStr) {
    const d = new Date(dateStr);
    const now = new Date();
    const diff = (d - now) / (1000 * 60 * 60 * 24);
    return diff >= 0 && diff <= 30;
}

function Row({ label, value }) {
    return (
        <div className="flex justify-between">
            <dt className="text-sm text-om-muted">{label}</dt>
            <dd className="text-sm font-medium">{value}</dd>
        </div>
    );
}

function Th({ children, right, center }) {
    const align = right ? 'text-right' : center ? 'text-center' : 'text-left';
    return (
        <th className={`px-3 py-2 ${align} text-xs font-medium text-om-muted uppercase`}>
            {children}
        </th>
    );
}
