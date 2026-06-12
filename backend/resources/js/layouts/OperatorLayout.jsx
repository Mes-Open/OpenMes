import { Link, router, usePage } from '@inertiajs/react';
import { OnlineDot } from '@openmes/ui';

/**
 * Full-screen tablet chrome for operator screens — the React port of the
 * operator branch of layouts/app.blade.php. Big touch targets, a slim top bar
 * showing the selected line/workstation, and view switches (Queue / Workstation).
 *
 * Pages opt in via:  Page.layout = (page) => <OperatorLayout>{page}</OperatorLayout>
 *
 * Reads from shared Inertia props: auth, line, selectedWorkstation, csrf_token,
 * flash. `line` is present once a line is selected (all operator pages but
 * select-line pass it).
 *
 * Geist White restyle: light-only v1 — former `dark:` classes removed.
 */
export default function OperatorLayout({ children }) {
    const { auth, line, selectedWorkstation, csrf_token } = usePage().props;
    const path = typeof window !== 'undefined' ? window.location.pathname : '';
    const isActive = (prefix) => path === prefix || path.startsWith(prefix);

    return (
        <div className="min-h-screen flex flex-col bg-om-bg font-sans">
            <header className="shrink-0 bg-om-card border-b border-om-line">
                <div className="flex items-center gap-4 px-4 h-16">
                    <Link href="/operator/select-line" className="flex items-center gap-2.5 shrink-0">
                        <div aria-hidden className="size-7 rounded-md bg-[linear-gradient(135deg,#EA5A2B_0_50%,#1A1917_50%_100%)]" />
                        <span className="hidden sm:block text-[15px] font-semibold tracking-[-0.01em] text-om-ink">openmes</span>
                    </Link>

                    {line && (
                        <div className="min-w-0 border-l border-om-line pl-4">
                            <p className="text-[15px] font-semibold leading-tight text-om-ink truncate">{line.name}</p>
                            {selectedWorkstation && (
                                <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint truncate">{selectedWorkstation.name}</p>
                            )}
                        </div>
                    )}

                    <OnlineDot label="ONLINE" pulse className="hidden md:inline-flex shrink-0" />

                    {line && (
                        <nav className="ml-auto flex items-center gap-2">
                            <TopLink href="/operator/queue" active={isActive('/operator/queue') || isActive('/operator/work-order')}>
                                Queue
                            </TopLink>
                            <TopLink href="/operator/workstation" active={isActive('/operator/workstation')}>
                                Workstation
                            </TopLink>
                            <Link
                                href="/operator/select-line"
                                className="px-3 py-2.5 rounded-om-sm text-sm font-medium text-om-muted border border-om-line hover:bg-om-chip hover:text-om-ink transition-colors"
                            >
                                Switch Line
                            </Link>
                        </nav>
                    )}

                    <div className={`flex items-center gap-3 ${line ? '' : 'ml-auto'}`}>
                        <div className="flex items-center gap-2">
                            <div className="w-8 h-8 rounded-full bg-om-ink flex items-center justify-center text-white text-sm font-semibold">
                                {auth?.user?.initial ?? '?'}
                            </div>
                            <span className="text-sm text-om-ink hidden md:block">{auth?.user?.name}</span>
                        </div>
                        <form action="/logout" method="POST">
                            <input type="hidden" name="_token" value={csrf_token} />
                            <button
                                type="submit"
                                title="Logout"
                                className="p-2.5 rounded-om-sm text-om-faint hover:text-om-blocked hover:bg-om-chip transition-colors"
                            >
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <main className="flex-1 overflow-auto p-4 md:p-6">
                <FlashMessages />
                {children}
            </main>
        </div>
    );
}

function TopLink({ href, active, children }) {
    return (
        <Link
            href={href}
            className={`px-4 py-2.5 rounded-om-sm text-sm font-semibold transition-colors ${
                active ? 'bg-om-ink text-white' : 'text-om-muted hover:bg-om-chip hover:text-om-ink'
            }`}
        >
            {children}
        </Link>
    );
}

function FlashMessages() {
    const { flash } = usePage().props;
    if (!flash) return null;
    const items = [
        ['success', 'bg-om-running-bg border-om-running/30 text-om-running'],
        ['error', 'bg-om-blocked-bg border-om-blocked/30 text-om-blocked'],
        ['warning', 'bg-om-downtime-bg border-om-downtime/30 text-om-downtime'],
        ['info', 'bg-om-chip border-om-line text-om-muted'],
    ].filter(([k]) => flash[k]);
    if (!items.length) return null;
    return (
        <div className="mb-4 space-y-2 max-w-3xl mx-auto">
            {items.map(([k, cls]) => (
                <div key={k} className={`p-3 rounded-om-sm border text-sm ${cls}`}>{flash[k]}</div>
            ))}
        </div>
    );
}
