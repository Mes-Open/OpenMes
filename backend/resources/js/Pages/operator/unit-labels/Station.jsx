import { useState, useEffect, useCallback, useMemo } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { Button, Dropdown } from '@openmes/ui';
import AppDataTable from '../../../components/AppDataTable';
import AppLayout from '../../../layouts/AppLayout';
import { __ } from '../../../lib/i18n';

function csrf() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

function statusTone(status) {
    if (status === 'scrapped') return 'text-red-600';
    if (status === 'done') return 'text-emerald-600';
    return 'text-om-accent';
}

export default function Station() {
    const { workOrders = [] } = usePage().props;

    const [woId, setWoId] = useState('');
    const [psn, setPsn] = useState('');
    const [serialNo, setSerialNo] = useState('');
    const [busy, setBusy] = useState(false);
    const [result, setResult] = useState(null); // { success, created?, unit?, error? }
    const [flash, setFlash] = useState(null); // 'success' | 'error' | null
    const [units, setUnits] = useState([]);

    const fetchUnits = useCallback(async () => {
        try {
            const qs = woId ? `?work_order_id=${encodeURIComponent(woId)}` : '';
            const res = await fetch(`/operator/unit-labels/units${qs}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (res.ok) {
                const data = await res.json();
                setUnits(data.units ?? []);
            }
        } catch {
            // Non-critical: table keeps its last state.
        }
    }, [woId]);

    useEffect(() => {
        fetchUnits();
    }, [fetchUnits]);

    const flashIt = (kind) => {
        setFlash(kind);
        setTimeout(() => setFlash(null), 2000);
    };

    const applyLabel = useCallback(async () => {
        const sn = serialNo.trim();
        if (!sn) {
            setResult({ success: false, error: __('Scan or type the serial number first.') });
            return;
        }
        setBusy(true);
        try {
            const res = await fetch('/operator/unit-labels/apply', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    serial_no: sn,
                    psn: psn.trim() || null,
                    work_order_id: woId ? Number(woId) : null,
                }),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok) {
                setResult({ success: true, created: !!data.created, unit: data.unit, message: data.message });
                setPsn('');
                setSerialNo('');
                flashIt('success');
            } else {
                const msg = data.errors
                    ? Object.values(data.errors).flat().join(' ')
                    : (data.message || __('Could not apply label.'));
                setResult({ success: false, error: msg });
                flashIt('error');
            }
            fetchUnits();
        } catch {
            setResult({ success: false, error: __('Network error, try again.') });
        } finally {
            setBusy(false);
        }
    }, [psn, serialNo, woId, fetchUnits]);

    const unitColumns = useMemo(() => [
        {
            id: 'serial_no',
            accessorKey: 'serial_no',
            header: __('Serial No'),
            cell: ({ row }) => <span className="font-mono font-semibold text-om-ink">{row.original.serial_no}</span>,
        },
        {
            id: 'psn',
            accessorKey: 'psn',
            header: 'PSN',
            cell: ({ row }) => <span className="font-mono text-om-muted">{row.original.psn || '-'}</span>,
        },
        {
            id: 'work_order',
            accessorKey: 'work_order',
            header: __('Work Order'),
            cell: ({ row }) => <span className="font-mono text-om-muted">{row.original.work_order || '-'}</span>,
        },
        {
            id: 'status',
            accessorKey: 'status',
            header: __('Status'),
            cell: ({ row }) => (
                <span className={`text-[11px] font-semibold uppercase tracking-wide ${statusTone(row.original.status)}`}>
                    {row.original.status}
                </span>
            ),
        },
        {
            id: 'produced_at',
            accessorKey: 'produced_at',
            header: __('Applied At'),
            cell: ({ row }) => (
                <span className="font-mono text-om-muted text-[11px] whitespace-nowrap">
                    {row.original.produced_at ? new Date(row.original.produced_at).toLocaleString() : '-'}
                </span>
            ),
        },
    ], []);

    return (
        <>
            <Head title={__('SN Label Station')} />
            <div className="p-6 space-y-5">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold text-om-ink">SN Label Station</h1>
                        <p className="text-sm text-om-muted">
                            {__('Apply the pre-printed serial number label and bind it to the process serial number (PSN).')}
                        </p>
                    </div>
                    <div className="w-72">
                        <Dropdown
                            aria-label={__('Work order')}
                            value={woId}
                            onChange={(v) => setWoId(v)}
                            placeholder={__('All work orders')}
                            options={[
                                { value: '', label: __('All work orders') },
                                ...workOrders.map((wo) => ({
                                    value: String(wo.id),
                                    label: `${wo.order_no} - ${wo.product || wo.status}`,
                                })),
                            ]}
                        />
                    </div>
                </div>

                <div className="bg-white border border-om-line rounded-lg p-5">
                    <div className="grid grid-cols-1 md:grid-cols-[1fr_1fr_auto] gap-4 items-end">
                        <div>
                            <label className="block text-[12px] font-semibold text-om-muted mb-1 uppercase tracking-wide">
                                {__('Process Serial Number (PSN)')}
                            </label>
                            <input
                                value={psn}
                                onChange={(e) => setPsn(e.target.value)}
                                placeholder="260-26-1"
                                className="w-full font-mono text-lg px-3 py-2.5 border border-om-line rounded-md focus:outline-none focus:ring-2 focus:ring-om-accent"
                            />
                        </div>
                        <div>
                            <label className="block text-[12px] font-semibold text-om-muted mb-1 uppercase tracking-wide">
                                {__('Serial Number (label)')}
                            </label>
                            <input
                                value={serialNo}
                                onChange={(e) => setSerialNo(e.target.value)}
                                onKeyDown={(e) => { if (e.key === 'Enter' && !busy) applyLabel(); }}
                                placeholder="e.g. 0001"
                                className="w-full font-mono text-lg px-3 py-2.5 border border-om-line rounded-md focus:outline-none focus:ring-2 focus:ring-om-accent"
                            />
                        </div>
                        <Button variant="accent" onClick={applyLabel} disabled={busy || !serialNo.trim()} className="px-6 py-2.5">
                            {__('Apply label')}
                        </Button>
                    </div>

                    {result && (
                        <div className={`mt-4 rounded-md px-4 py-3 text-sm border ${result.success ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-red-50 border-red-200 text-red-700'}`}>
                            {result.success ? (
                                <div className="flex flex-wrap items-center gap-x-4 gap-y-1">
                                    <span className="font-semibold">
                                        {result.created ? __('Unit registered') : __('Already registered')}
                                    </span>
                                    <span className="font-mono">{result.unit?.serial_no}</span>
                                    <span className="font-mono">PSN: {result.unit?.psn || '-'}</span>
                                    {result.unit?.work_order && <span className="font-mono">{result.unit.work_order}</span>}
                                </div>
                            ) : (
                                <span>{result.error}</span>
                            )}
                        </div>
                    )}
                </div>

                <div className="bg-white border border-om-line rounded-lg overflow-hidden">
                    <div className="px-4 py-3 border-b border-om-line">
                        <h2 className="text-sm font-semibold text-om-ink">{__('Recent units (last 50)')}</h2>
                    </div>
                    <AppDataTable
                        data={units}
                        columns={unitColumns}
                        searchable
                    />
                </div>
            </div>

            {flash && (
                <div className={`fixed bottom-5 right-5 z-50 rounded-md px-4 py-3 text-sm font-semibold text-white shadow-lg ${flash === 'success' ? 'bg-emerald-600' : 'bg-red-600'}`}>
                    {flash === 'success' ? __('Saved') : __('Error')}
                </div>
            )}
        </>
    );
}

Station.layout = (page) => <AppLayout>{page}</AppLayout>;
