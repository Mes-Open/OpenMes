import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';

export default function Demo({ message, serverTime }) {
    const [count, setCount] = useState(0);

    return (
        <>
            <Head title="React demo" />
            <main style={{
                maxWidth: 720,
                margin: '4rem auto',
                padding: '2rem',
                fontFamily: 'system-ui, sans-serif',
                color: '#111',
                background: '#fff',
                borderRadius: 12,
                boxShadow: '0 1px 3px rgba(0,0,0,0.08)',
            }}>
                <h1 style={{ fontSize: 28, marginBottom: 12 }}>React + Inertia is wired up</h1>
                <p style={{ color: '#444', marginBottom: 8 }}>{message}</p>
                <p style={{ color: '#666', fontSize: 14, marginBottom: 24 }}>
                    Server time at render: <code>{serverTime}</code>
                </p>

                <button
                    onClick={() => setCount((c) => c + 1)}
                    style={{
                        background: '#1e40af',
                        color: '#fff',
                        padding: '8px 16px',
                        borderRadius: 6,
                        border: 'none',
                        cursor: 'pointer',
                        fontSize: 15,
                    }}
                >
                    Clicked {count} {count === 1 ? 'time' : 'times'}
                </button>

                <hr style={{ margin: '2rem 0', border: 0, borderTop: '1px solid #eee' }} />

                <p style={{ fontSize: 14, color: '#666' }}>
                    Edit <code>resources/js/Pages/Demo.jsx</code> and save — the watcher
                    rebuilds and a browser refresh shows your change. No local Node
                    install required: a <code>frontend</code> container runs{' '}
                    <code>vite build --watch</code> inside docker-compose.
                </p>

                <p style={{ marginTop: 16 }}>
                    <Link href="/" style={{ color: '#1e40af' }}>← back to /</Link>
                </p>
            </main>
        </>
    );
}
