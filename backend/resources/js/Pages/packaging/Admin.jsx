// Geist White restyle: light-only v1 — om-* tokens, @openmes/ui controls.
import { Head, Link, usePage } from '@inertiajs/react';
import { StatusPill } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../layouts/AppLayout';
import { formatNumber } from '../../lib/i18n';

function ProgressBar({ pct, done }) {
    const color = done ? 'bg-om-running' : pct >= 50 ? 'bg-om-downtime' : 'bg-om-accent';
    return (
        <div className="flex items-center gap-2">
            <div className="flex-1 bg-om-line rounded-full h-1.5">
                <div className={`h-1.5 rounded-full ${color}`} style={{ width: `${pct}%` }} />
            </div>
            <span className="font-mono text-[11px] text-om-faint w-8 text-right">{pct}%</span>
        </div>
    );
}

function StatusBadge({ item }) {
    if (item.done) {
        return <StatusPill status="done" label="Spakowane" />;
    }
    if (item.status === 'DONE') {
        return <StatusPill status="running" label="W trakcie" />;
    }
    return <StatusPill status="pending" label={item.status} />;
}

const itemColumns = [
    {
        id: 'order_no',
        accessorKey: 'order_no',
        header: 'Zlecenie',
        cell: ({ row }) => <span className="font-mono font-semibold text-om-ink">{row.original.order_no}</span>,
    },
    {
        id: 'product',
        accessorKey: 'product',
        header: 'Produkt',
        cell: ({ row }) => <span className="text-om-ink">{row.original.product}</span>,
    },
    {
        id: 'line',
        accessorFn: (r) => r.line ?? '—',
        header: 'Linia',
        cell: ({ row }) => <span className="text-om-muted">{row.original.line ?? '—'}</span>,
    },
    {
        id: 'eans',
        accessorFn: (r) => (r.eans ?? []).join(' '),
        header: 'EAN',
        enableSorting: false,
        cell: ({ row }) => (
            (row.original.eans ?? []).map((ean) => (
                <span key={ean} className="inline-block font-mono text-[11px] bg-om-chip text-om-muted px-2 py-0.5 rounded-[5px] mr-1">
                    {ean}
                </span>
            ))
        ),
    },
    {
        id: 'packed_qty',
        accessorKey: 'packed_qty',
        header: 'Spakowano',
        meta: { align: 'right' },
        cell: ({ row }) => <span className="font-mono font-semibold text-om-ink">{row.original.packed_qty}</span>,
    },
    {
        id: 'planned_qty',
        accessorKey: 'planned_qty',
        header: 'Plan',
        meta: { align: 'right' },
        cell: ({ row }) => <span className="font-mono text-om-muted">{row.original.planned_qty}</span>,
    },
    {
        id: 'progress',
        accessorKey: 'progress',
        header: 'Postęp',
        cell: ({ row }) => <ProgressBar pct={row.original.progress} done={row.original.done} />,
    },
    {
        id: 'status',
        accessorFn: (r) => (r.done ? 'Spakowane' : r.status === 'DONE' ? 'W trakcie' : r.status),
        header: 'Status',
        cell: ({ row }) => <StatusBadge item={row.original} />,
    },
];

export default function Admin() {
    const { items = [], stats = {} } = usePage().props;

    const totalPacked = stats.total_packed ?? 0;
    const plan = stats.plan ?? 0;
    const realizacja = plan > 0 ? Math.min(100, Math.round((totalPacked / plan) * 100)) : 0;

    const now = new Date();
    const hour = now.getHours();
    const shiftLabel = hour >= 6 && hour < 18 ? '06:00 – 18:00' : '18:00 – 06:00';

    const realizacjaColor =
        realizacja >= 100 ? 'text-om-running' : realizacja >= 50 ? 'text-om-downtime' : 'text-om-blocked';
    const realizacjaBar =
        realizacja >= 100 ? 'bg-om-running' : realizacja >= 50 ? 'bg-om-downtime' : 'bg-om-blocked';

    return (
        <>
            <Head title="Pakowanie — Przegląd" />
            <div className="max-w-7xl mx-auto">
                {/* Breadcrumbs */}
                <nav className="flex items-center gap-1 text-[13px] text-om-muted mb-4">
                    <Link href="/admin/dashboard" className="hover:text-om-ink hover:underline">Dashboard</Link>
                    <span className="mx-1">/</span>
                    <span className="text-om-ink">Pakowanie</span>
                </nav>

                {/* Header */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-[-0.02em] text-om-ink">Pakowanie &mdash; Przegląd</h1>
                        <p className="text-[12.5px] text-om-muted mt-1">Bieżąca zmiana: {shiftLabel}</p>
                    </div>
                    <div className="flex gap-2">
                        <Link
                            href="/packaging/station"
                            className="inline-flex items-center justify-center rounded-om-sm bg-om-ink px-4 py-2.5 text-[13px] font-semibold text-om-on-ink hover:bg-om-ink-hover transition-colors"
                        >
                            Otwórz stanowisko
                        </Link>
                        <Link
                            href="/packaging/eans"
                            className="inline-flex items-center justify-center rounded-om-sm bg-om-chip px-4 py-2.5 text-[13px] font-semibold text-om-ink hover:bg-om-line2 transition-colors"
                        >
                            Zarządzaj EAN
                        </Link>
                    </div>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                    <div className="bg-om-card border border-om-line rounded-om p-4 text-center">
                        <p className="font-mono text-[40px] leading-none font-semibold tracking-[-0.02em] text-om-ink">
                            {formatNumber((stats.today_packed ?? 0))}
                        </p>
                        <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mt-2">Spakowano (zmiana)</p>
                    </div>
                    <div className="bg-om-card border border-om-line rounded-om p-4 text-center">
                        <p className="font-mono text-[40px] leading-none font-semibold tracking-[-0.02em] text-om-muted">
                            {formatNumber(plan)}
                        </p>
                        <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mt-2">Plan łącznie</p>
                    </div>
                    <div className="bg-om-card border border-om-line rounded-om p-4 text-center">
                        <p className={`font-mono text-[40px] leading-none font-semibold tracking-[-0.02em] ${(stats.backlog ?? 0) > 0 ? 'text-om-blocked' : 'text-om-running'}`}>
                            {formatNumber((stats.backlog ?? 0))}
                        </p>
                        <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mt-2">Backlog</p>
                    </div>
                    <div className="bg-om-card border border-om-line rounded-om p-4 text-center">
                        <p className={`font-mono text-[40px] leading-none font-semibold tracking-[-0.02em] ${realizacjaColor}`}>{realizacja}%</p>
                        <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mt-2">Realizacja</p>
                        <div className="w-full bg-om-line rounded-full h-1.5 mt-2">
                            <div className={`h-1.5 rounded-full ${realizacjaBar}`} style={{ width: `${realizacja}%` }} />
                        </div>
                    </div>
                </div>

                {/* Items table */}
                <div className="bg-om-card border border-om-line rounded-om overflow-hidden">
                    <div className="px-4 py-3 border-b border-om-line">
                        <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-om-ink">
                            Zlecenia do spakowania ({items.length})
                        </h2>
                    </div>
                    <DataTable
                        data={items}
                        columns={itemColumns}
                        searchable
                        columnToggle
                        paginated
                        searchPlaceholder="Szukaj zleceń…"
                        emptyLabel="Brak zleceń z przypisanymi kodami EAN"
                    />
                </div>
            </div>
        </>
    );
}

Admin.layout = (page) => <AppLayout>{page}</AppLayout>;
