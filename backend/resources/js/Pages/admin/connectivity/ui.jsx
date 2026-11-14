/**
 * Shared UI primitives for the connectivity admin pages (Modbus / OPC UA / the
 * "All" overview). Extracted to avoid copy-pasting the status dot, stat card,
 * and form section/field helpers across every page.
 */
import { Link, router, usePage } from '@inertiajs/react';
import { __, formatNumber } from '../../../lib/i18n';
import { nameControl } from '../../../lib/fieldName';

export const STATUS_DOT = {
    green:  'bg-om-running',
    yellow: 'bg-om-downtime',
    red:    'bg-om-blocked',
    slate:  'bg-slate-400',
};

/** Colored connection-status dot. `size` is a Tailwind w/h pair. */
export function StatusDot({ color, pulse = false, size = 'w-2.5 h-2.5' }) {
    const cls = STATUS_DOT[color] ?? 'bg-slate-400';
    return <span className={`${size} rounded-full shrink-0 ${cls} ${pulse ? 'animate-pulse' : ''}`} />;
}

/** A connection's status (`connected`, `error`, …) as a translated label. */
const STATUS_LABELS = {
    connected:    'Connected',
    connecting:   'Connecting',
    disconnected: 'Disconnected',
    error:        'Error',
};

export function statusLabel(status) {
    return STATUS_LABELS[status] ? __(STATUS_LABELS[status]) : status;
}

/** Centered metric card used on the connection Show pages. */
export function StatCard({ value, label, capitalize = false }) {
    return (
        <div className="bg-om-card rounded-om border border-om-line2 p-4 text-center">
            <p className={`text-2xl font-bold text-om-ink ${capitalize ? 'capitalize' : ''}`}>{value}</p>
            <p className="text-xs text-om-muted mt-1">{__(label)}</p>
        </div>
    );
}

/** Card-wrapped form section with an uppercase title. */
export function Section({ title, children }) {
    return (
        <div className="bg-om-card rounded-om border border-om-line2 p-5 space-y-4">
            <h2 className="text-sm font-semibold text-om-muted uppercase tracking-wider">{__(title)}</h2>
            {children}
        </div>
    );
}

/** Labeled form field with optional required marker and error text. */
export function Field({ label, required, error, children }) {
    return (
        <div>
            <div className="block text-sm font-medium text-om-muted mb-1">
                {__(label)} {required && <span className="text-om-blocked">*</span>}
            </div>
            {nameControl(children, __(label))}
            {error && <p className="mt-1 text-xs text-om-blocked">{error}</p>}
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* Connection lists: one header, one tile, used by every list tab.     */
/* ------------------------------------------------------------------ */

export const PROTOCOL_LABELS = {
    mqtt:   'MQTT',
    modbus: 'Modbus',
    opcua:  'OPC UA',
    rest:   'REST',
};

const TABS = [
    { key: 'all',    label: 'All',     href: '/admin/connectivity' },
    { key: 'mqtt',   label: 'MQTT',    href: '/admin/connectivity/mqtt' },
    { key: 'modbus', label: 'Modbus',  href: '/admin/connectivity/modbus' },
    { key: 'opcua',  label: 'OPC UA',  href: '/admin/connectivity/opcua' },
];

const primaryLink = 'inline-flex items-center gap-2 px-4 py-2 bg-om-ink text-om-on-ink text-sm font-medium rounded-om-sm hover:bg-om-ink-hover transition-colors';
const outlineLink = 'inline-flex items-center gap-2 px-4 py-2 border border-om-line text-om-ink text-sm font-medium rounded-om-sm hover:bg-om-chip transition-colors';

/**
 * Title, description, the protocol tabs (with counts) and the page actions —
 * the same on the overview and on each protocol's list.
 */
export function ConnectivityHeader({ active, counts = {}, description, createHref, createLabel }) {
    const tabs = usePage().props.auth?.user?.accessibleTabs;
    const canOpenCounters = ! Array.isArray(tabs) || tabs.includes('machine_counters');
    const total = Object.values(counts).reduce((sum, n) => sum + n, 0);

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold text-om-ink">{__('Machine Connectivity')}</h1>
                    <p className="text-sm text-om-muted mt-1">{description}</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {canOpenCounters && (
                        <Link href="/admin/connectivity/counters" className={outlineLink}>
                            {__('Machine counters')}
                        </Link>
                    )}
                    {createHref && (
                        <Link href={createHref} className={primaryLink}>
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            {createLabel}
                        </Link>
                    )}
                </div>
            </div>
            <nav className="flex gap-1 border-b border-om-line2 overflow-x-auto">
                {TABS.map((tab) => {
                    const count = tab.key === 'all' ? total : (counts[tab.key] ?? 0);
                    const isActive = tab.key === active;
                    return (
                        <Link
                            key={tab.key}
                            href={tab.href}
                            aria-current={isActive ? 'page' : undefined}
                            className={`px-4 py-2 -mb-px text-sm font-medium whitespace-nowrap border-b-2 transition-colors ${
                                isActive ? 'border-om-accent text-om-accent' : 'border-transparent text-om-muted hover:text-om-ink'
                            }`}
                        >
                            {__(tab.label)} <span className="text-om-faint">({count})</span>
                        </Link>
                    );
                })}
            </nav>
        </div>
    );
}

/** Responsive grid the connection tiles sit in. */
export function ConnectionGrid({ children }) {
    return <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">{children}</div>;
}

/** Shown in place of the grid when a list has no connections. */
export function ConnectionsEmpty({ message, createHref, createLabel }) {
    return (
        <div className="bg-om-card rounded-om border border-dashed border-om-line p-10 text-center text-om-faint">
            <svg className="w-10 h-10 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
            </svg>
            <p className="text-sm">{message}</p>
            {createHref && (
                <Link href={createHref} className="mt-2 inline-block text-om-accent hover:underline text-sm">
                    {createLabel}
                </Link>
            )}
        </div>
    );
}

/**
 * One machine connection as a tile, identical on every list.
 *
 * `conn` carries id, name, description, is_active, status, status_color,
 * last_connected_at and, depending on the protocol, topics_count /
 * messages_received or tags_count. `endpoint` is the address to show;
 * `showProtocol` adds the protocol badge (the "All" tab mixes them).
 */
/** Whether an address points at the local machine (wrong from inside a container). */
function isLoopback(endpoint) {
    const host = String(endpoint ?? '').replace(/^[a-z.]+:\/\//i, '').split(/[:/\s]/)[0].replace(/^\[|\]$/g, '').toLowerCase();
    return host === 'localhost' || host.startsWith('127.') || host === '::1';
}

export function ConnectionCard({ conn, protocol, endpoint, showProtocol = false }) {
    const base = PROTOCOL_LABELS[protocol] && protocol !== 'rest' ? `/admin/connectivity/${protocol}` : null;
    const isMqtt = protocol === 'mqtt';
    const count = isMqtt ? (conn.topics_count ?? 0) : (conn.tags_count ?? 0);
    const countLabel = isMqtt
        ? (count === 1 ? __('topic') : __('topics'))
        : (count === 1 ? __('tag') : __('tags'));

    const toggle = () => router.post(`/admin/connectivity/connections/${conn.id}/toggle-active`, {}, { preserveScroll: true });

    return (
        <div className={`bg-om-card rounded-om border border-om-line2 shadow-sm hover:shadow-md transition-shadow p-5 flex flex-col gap-3 ${conn.is_active ? '' : 'opacity-75'}`}>
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <h3 className="font-semibold text-om-ink truncate">
                        {base ? <Link href={`${base}/${conn.id}`} className="hover:text-om-accent">{conn.name}</Link> : conn.name}
                    </h3>
                    <div className="mt-1 flex items-center gap-1.5 text-xs text-om-muted">
                        <StatusDot color={conn.status_color} pulse={conn.status === 'connected'} size="w-2 h-2" />
                        <span>{conn.is_active ? statusLabel(conn.status) : __('Inactive')}</span>
                    </div>
                </div>
                {showProtocol && (
                    <span className="shrink-0 text-xs px-2 py-0.5 rounded-full font-medium uppercase tracking-wide bg-om-chip text-om-muted">
                        {PROTOCOL_LABELS[protocol] ?? protocol}
                    </span>
                )}
            </div>

            {endpoint && (
                <p className="text-xs text-om-faint font-mono truncate" title={endpoint}>{endpoint}</p>
            )}
            {conn.status_message && (
                <p className="text-xs text-om-blocked bg-om-blocked-bg rounded-md px-2 py-1.5 line-clamp-3" title={conn.status_message}>
                    {conn.status_message}
                </p>
            )}
            {isLoopback(endpoint) && (
                <p className="text-xs text-om-downtime">
                    {__('127.0.0.1 / localhost inside Docker is the container itself — use host.docker.internal or the machine\'s IP address.')}
                </p>
            )}
            {conn.description && (
                <p className="text-xs text-om-muted line-clamp-2">{conn.description}</p>
            )}

            <div className="mt-auto flex items-center justify-between gap-2 text-xs text-om-muted">
                <span>
                    {count} {countLabel}
                    {isMqtt && <> · {formatNumber(Number(conn.messages_received ?? 0))} {__('msg')}</>}
                </span>
                <span className="text-om-faint truncate" title={__('Last connected:')}>
                    {conn.last_connected_at ?? __('Never')}
                </span>
            </div>

            <div className="flex gap-2 pt-3 border-t border-om-line2">
                {base && (
                    <>
                        <Link href={`${base}/${conn.id}`} className="flex-1 text-center text-xs px-3 py-1.5 bg-om-chip text-om-accent rounded-md hover:bg-om-line2 transition-colors font-medium">
                            {__('View')}
                        </Link>
                        <Link href={`${base}/${conn.id}/edit`} className="flex-1 text-center text-xs px-3 py-1.5 bg-om-panel text-om-muted rounded-md hover:bg-om-chip transition-colors font-medium">
                            {__('Edit')}
                        </Link>
                    </>
                )}
                {! base && (conn.counter_ids ?? []).map((id) => (
                    <Link key={id} href={`/admin/connectivity/counters?counter=${id}`} className="flex-1 text-center text-xs px-3 py-1.5 bg-om-chip text-om-accent rounded-md hover:bg-om-line2 font-medium">
                        {__('Open counter')} #{id}
                    </Link>
                ))}
                {base && (
                    <button
                        type="button"
                        onClick={toggle}
                        className={`text-xs px-3 py-1.5 rounded-md font-medium transition-colors cursor-pointer hover:brightness-95 ${
                            conn.is_active ? 'bg-om-blocked-bg text-om-blocked' : 'bg-om-running-bg text-om-running'
                        }`}
                    >
                        {conn.is_active ? __('Disable') : __('Enable')}
                    </button>
                )}
            </div>
        </div>
    );
}
