import { useMemo } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../layouts/AppLayout';
import { ShipmentStatusPill } from '../../components/shipments/shipmentUi';
import { useSyncedShape } from '../../lib/useSyncedShape';
import { __, formatDateTime } from '../../lib/i18n';

/**
 * Pick the load to scan (#400): every shipment still on the dock, with how far
 * loading has got. Live — a load planned in the office appears here at once.
 */
export default function LoadingPicker() {
    const { statusLabels = {} } = usePage().props;
    const { data: shipments, isLoading } = useSyncedShape('shipments');
    const { data: items } = useSyncedShape('shipment_items');

    const open = useMemo(() => shipments
        .filter((s) => s.status === 'planned' || s.status === 'loading')
        .sort((a, b) => (a.status === b.status ? String(a.planned_at ?? '').localeCompare(String(b.planned_at ?? '')) : a.status === 'loading' ? -1 : 1)), [shipments]);

    const counts = useMemo(() => {
        const out = {};
        for (const item of items) {
            const c = (out[item.shipment_id] ??= { expected: 0, loaded: 0 });
            c.expected += 1;
            if (item.loaded_at) c.loaded += 1;
        }
        return out;
    }, [items]);

    return (
        <div className="max-w-5xl mx-auto">
            <Head title={__('Loading terminal')} />
            <h1 className="text-[26px] font-semibold tracking-[-0.02em] text-om-ink mb-1">{__('Loading terminal')}</h1>
            <p className="text-om-muted text-[13px] mb-6">{__('Pick the shipment you are loading.')}</p>

            {!isLoading && open.length === 0 && (
                <p className="bg-om-card border border-om-line rounded-om p-6 text-[13px] text-om-muted">{__('No shipments waiting to be loaded.')}</p>
            )}

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {open.map((s) => {
                    const c = counts[s.id] ?? { expected: 0, loaded: 0 };
                    const pct = c.expected ? Math.round((c.loaded / c.expected) * 100) : 0;
                    return (
                        <Link
                            key={s.id}
                            href={`/logistics/loading/${s.id}`}
                            data-testid={`pick-${s.shipment_no}`}
                            className="block bg-om-card border border-om-line rounded-om p-5 hover:border-om-accent transition-colors"
                        >
                            <div className="flex items-center justify-between gap-3">
                                <span className="font-mono text-[18px] font-semibold text-om-ink">{s.shipment_no}</span>
                                <ShipmentStatusPill status={s.status} labels={statusLabels} />
                            </div>
                            <div className="text-[13px] text-om-muted mt-1">
                                {[s.carrier, s.vehicle_no, s.destination].filter(Boolean).join(' · ') || '—'}
                            </div>
                            {s.planned_at && <div className="text-[12px] text-om-faint mt-1">{__('Planned')}: {formatDateTime(s.planned_at)}</div>}
                            <div className="mt-4 flex items-center gap-3">
                                <div className="flex-1 h-2 rounded-full bg-om-chip overflow-hidden">
                                    <div className="h-full bg-om-running" style={{ width: `${pct}%` }} />
                                </div>
                                <span className="text-[13px] font-semibold text-om-ink">{c.loaded} / {c.expected}</span>
                            </div>
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}

LoadingPicker.layout = (page) => <AppLayout>{page}</AppLayout>;
