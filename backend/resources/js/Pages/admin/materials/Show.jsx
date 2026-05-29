import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';

const MOVEMENT_TYPE_COLORS = {
    receipt:    'text-green-700',
    return:     'text-blue-700',
    allocation: 'text-amber-700',
    consume:    'text-gray-600',
    scrap:      'text-red-700',
    adjustment: 'text-purple-700',
};

const LOT_STATUS_COLORS = {
    released:   'bg-green-100 text-green-800',
    quarantine: 'bg-red-100 text-red-800',
    expired:    'bg-amber-100 text-amber-800',
};

function fmt(val, decimals = 3) {
    return Number(val ?? 0).toFixed(decimals);
}

export default function MaterialShow({ material, lots = [], recentMovements = [] }) {
    const available = material.available_quantity ?? 0;
    const minStock = material.min_stock_level ?? 0;
    const stockCardBorder = available < minStock ? 'border-red-400' : 'border-blue-400';

    return (
        <>
            <Head title={`Material — ${material.name}`} />

            {/* Breadcrumbs */}
            <nav className="text-sm text-gray-500 mb-4 flex items-center gap-1">
                <a href="/admin/dashboard" className="hover:underline">Dashboard</a>
                <span>/</span>
                <a href="/admin/materials" className="hover:underline">Materials</a>
                <span>/</span>
                <span className="text-gray-800">{material.name}</span>
            </nav>

            <div className="max-w-5xl mx-auto">
                {/* Header */}
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-3xl font-bold text-gray-800">{material.name}</h1>
                            {material.is_active ? (
                                <span className="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">Active</span>
                            ) : (
                                <span className="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-sm font-medium">Inactive</span>
                            )}
                        </div>
                        <p className="text-sm text-gray-600 mt-1 font-mono">{material.code}</p>
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
                                <dt className="text-gray-500">On hand</dt>
                                <dd className="font-mono">{fmt(material.stock_quantity)} {material.unit_of_measure}</dd>
                            </div>
                            <div className="flex justify-between text-sm">
                                <dt className="text-gray-500">Reserved by active batches</dt>
                                <dd className="font-mono text-amber-700">{fmt(material.reserved_quantity)} {material.unit_of_measure}</dd>
                            </div>
                            <div className="flex justify-between text-sm pt-2 border-t border-gray-200">
                                <dt className="font-medium text-gray-700">Available</dt>
                                <dd className={`font-mono font-bold ${available <= 0 ? 'text-red-600' : 'text-green-700'}`}>
                                    {fmt(available)} {material.unit_of_measure}
                                </dd>
                            </div>
                            {material.min_stock_level != null && (
                                <div className="flex justify-between text-xs text-gray-400">
                                    <dt>Min stock level</dt>
                                    <dd className="font-mono">{fmt(material.min_stock_level)} {material.unit_of_measure}</dd>
                                </div>
                            )}
                            {material.unit_price != null && (
                                <div className="flex justify-between text-xs text-gray-400">
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
                            <p className="text-sm text-gray-500">No external system linked.</p>
                        )}

                        {material.sources && material.sources.length > 0 && (
                            <>
                                <h4 className="text-sm font-semibold mt-4 mb-2">Additional Sources</h4>
                                {material.sources.map((src) => (
                                    <div key={src.id} className="p-2 bg-gray-50 rounded mb-2 text-sm">
                                        <span className="font-medium">{src.integration_config?.system_name ?? 'Unknown'}</span>:{' '}
                                        <span className="font-mono">{src.external_code}</span>
                                    </div>
                                ))}
                            </>
                        )}
                    </div>
                </div>

                {/* Lots */}
                {lots.length > 0 && (
                    <div className="card mb-6">
                        <h3 className="text-lg font-semibold mb-4">
                            Lots <span className="text-sm font-normal text-gray-400">({lots.length})</span>
                        </h3>
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <Th>Lot</Th>
                                    <Th>Supplier ref</Th>
                                    <Th right>Received</Th>
                                    <Th right>Available</Th>
                                    <Th>Expiry</Th>
                                    <Th center>Status</Th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {lots.map((lot) => {
                                    const badge = LOT_STATUS_COLORS[lot.status] ?? 'bg-gray-100 text-gray-600';
                                    const expiringSoon = lot.expiry_date && isExpiringSoon(lot.expiry_date);
                                    const expired = lot.is_expired;
                                    return (
                                        <tr key={lot.id} className={expired ? 'bg-red-50' : ''}>
                                            <td className="px-3 py-2 font-mono">{lot.lot_number}</td>
                                            <td className="px-3 py-2 text-gray-500 font-mono text-xs">{lot.supplier_lot_no ?? '—'}</td>
                                            <td className="px-3 py-2 text-right font-mono">{fmt(lot.quantity_received)}</td>
                                            <td className={`px-3 py-2 text-right font-mono ${lot.quantity_available <= 0 ? 'text-gray-400' : 'font-bold'}`}>
                                                {fmt(lot.quantity_available)}
                                            </td>
                                            <td className={`px-3 py-2 text-xs ${expiringSoon ? 'text-amber-700 font-semibold' : 'text-gray-500'}`}>
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
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50">
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
                            <tbody className="divide-y divide-gray-200">
                                {recentMovements.map((mv, i) => {
                                    const qty = Number(mv.quantity ?? 0);
                                    const qtyColor = qty > 0 ? 'text-green-700' : qty < 0 ? 'text-red-700' : 'text-gray-500';
                                    const typeColor = MOVEMENT_TYPE_COLORS[mv.movement_type] ?? 'text-gray-700';
                                    return (
                                        <tr key={mv.id ?? i}>
                                            <td className="px-3 py-2 text-xs font-mono text-gray-500">
                                                {mv.performed_at ? mv.performed_at.substring(0, 16).replace('T', ' ') : '—'}
                                            </td>
                                            <td className="px-3 py-2">
                                                <span className={`font-medium ${typeColor}`}>{mv.movement_type}</span>
                                            </td>
                                            <td className={`px-3 py-2 text-right font-mono ${qtyColor}`}>
                                                {qty > 0 ? '+' : ''}{fmt(qty)}
                                            </td>
                                            <td className="px-3 py-2 text-right font-mono">{fmt(mv.balance_after)}</td>
                                            <td className="px-3 py-2 text-xs text-gray-500">
                                                {mv.source_type ? `${mv.source_type} #${mv.source_id}` : '—'}
                                            </td>
                                            <td className="px-3 py-2 text-xs text-gray-600 truncate max-w-xs" title={mv.reason ?? ''}>
                                                {(mv.reason ?? '').substring(0, 60)}
                                            </td>
                                            <td className="px-3 py-2 text-xs text-gray-500">{mv.performed_by?.name ?? '—'}</td>
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
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <Th>Template</Th>
                                    <Th>Product</Th>
                                    <Th right>Qty/Unit</Th>
                                    <Th right>Scrap %</Th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
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
            <dt className="text-sm text-gray-500">{label}</dt>
            <dd className="text-sm font-medium">{value}</dd>
        </div>
    );
}

function Th({ children, right, center }) {
    const align = right ? 'text-right' : center ? 'text-center' : 'text-left';
    return (
        <th className={`px-3 py-2 ${align} text-xs font-medium text-gray-500 uppercase`}>
            {children}
        </th>
    );
}
