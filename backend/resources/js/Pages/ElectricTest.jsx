import { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import { useShape } from '@electric-sql/react';

/**
 * Vertical-slice smoke test for the Electric live-sync loop via the gatekeeper.
 *
 *   PHP gatekeeper (sign)  →  Electric via Caddy  →  useShape()
 *
 * Fetches the signed shape config once, then streams from Electric through
 * Caddy. Insert/update a row in `work_orders` and the list updates within
 * ~100ms — no refresh, and no PHP worker held for the long-poll.
 */
export default function ElectricTest() {
    const [config, setConfig] = useState(null);

    useEffect(() => {
        fetch('/api/shapes/work_orders_active', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((r) => r.json())
            // Electric's client needs an absolute URL.
            .then((cfg) => setConfig({ ...cfg, url: window.location.origin + cfg.url }));
    }, []);

    if (!config) {
        return (
            <>
                <Head title="Electric live sync test" />
                <main style={pageStyle}><p>Connecting…</p></main>
            </>
        );
    }

    return <ElectricTestLive config={config} />;
}

function ElectricTestLive({ config }) {
    const { data, isLoading, error } = useShape(config);

    return (
        <>
            <Head title="Electric live sync test" />
            <main style={pageStyle}>
                <h1 style={{ fontSize: 28, marginBottom: 8 }}>Live work orders</h1>
                <p style={{ color: '#666', marginBottom: 24 }}>
                    Subscribed to <code>work_orders_active</code> shape via Electric.
                    Insert a row in <code>work_orders</code> and watch it appear here
                    without a refresh.
                </p>

                {isLoading && <p>Connecting…</p>}
                {error && (
                    <pre style={errorStyle}>{String(error)}</pre>
                )}

                {!isLoading && !error && (
                    <>
                        <p style={{ color: '#444', marginBottom: 12 }}>
                            <strong>{data.length}</strong> active work order{data.length === 1 ? '' : 's'}
                        </p>
                        <table style={tableStyle}>
                            <thead>
                                <tr>
                                    <th style={th}>Order</th>
                                    <th style={th}>Line</th>
                                    <th style={th}>Status</th>
                                    <th style={th}>Planned</th>
                                    <th style={th}>Produced</th>
                                </tr>
                            </thead>
                            <tbody>
                                {data.map((row) => (
                                    <tr key={row.id}>
                                        <td style={td}>{row.order_no}</td>
                                        <td style={td}>{row.line_id ?? '—'}</td>
                                        <td style={td}>
                                            <span style={statusBadge(row.status)}>{row.status}</span>
                                        </td>
                                        <td style={td}>{row.planned_qty}</td>
                                        <td style={td}>{row.produced_qty}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </>
                )}
            </main>
        </>
    );
}

const pageStyle = {
    maxWidth: 960,
    margin: '3rem auto',
    padding: '2rem',
    fontFamily: 'system-ui, sans-serif',
    color: '#111',
    background: '#fff',
    borderRadius: 12,
    boxShadow: '0 1px 3px rgba(0,0,0,0.08)',
};

const tableStyle = {
    width: '100%',
    borderCollapse: 'collapse',
    fontSize: 14,
};

const th = {
    textAlign: 'left',
    padding: '8px 12px',
    background: '#f6f6f8',
    borderBottom: '2px solid #e5e7eb',
    fontWeight: 600,
};

const td = {
    padding: '8px 12px',
    borderBottom: '1px solid #f0f0f3',
};

const errorStyle = {
    background: '#fef2f2',
    color: '#991b1b',
    padding: 12,
    borderRadius: 6,
    fontSize: 13,
    whiteSpace: 'pre-wrap',
};

function statusBadge(status) {
    const colors = {
        PENDING:     ['#fef3c7', '#92400e'],
        ACCEPTED:    ['#dbeafe', '#1e40af'],
        IN_PROGRESS: ['#d1fae5', '#065f46'],
        BLOCKED:     ['#fee2e2', '#991b1b'],
        PAUSED:      ['#e5e7eb', '#374151'],
    };
    const [bg, fg] = colors[status] || ['#e5e7eb', '#374151'];
    return {
        background: bg,
        color: fg,
        padding: '2px 8px',
        borderRadius: 4,
        fontSize: 12,
        fontWeight: 500,
    };
}
