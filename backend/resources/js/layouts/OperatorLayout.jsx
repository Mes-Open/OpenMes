import { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Sidebar } from './AppLayout';

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
 * When the operator has been granted admin tabs (Settings → Access), the SAME
 * sidebar as the admin panel is rendered on the left (it lists "Lines" first
 * for operators), so the chrome is identical to the panel. Its logo / user /
 * logout replace the header's, which then only carries the line context.
 */
export default function OperatorLayout({ children }) {
    const page = usePage();
    const { auth, nav, line, selectedWorkstation, csrf_token, appVersion } = page.props;
    const path = (page.url || '').split('?')[0];
    const isActive = (prefix) => path === prefix || path.startsWith(prefix);
    // Admin tabs granted to this operator via Settings → Access.
    const tabLinks = auth?.user?.accessibleTabLinks ?? [];
    const hasSidebar = tabLinks.length > 0;

    // Mirror the admin layout's persisted collapse / dark-mode state so the
    // reused Sidebar behaves the same here.
    const [collapsed, setCollapsed] = useState(
        () => typeof window !== 'undefined' && localStorage.getItem('sb') === '1',
    );
    const [mobileOpen, setMobileOpen] = useState(false);
    const [dark, setDark] = useState(
        () => typeof document !== 'undefined' && document.documentElement.classList.contains('dark'),
    );
    const toggleCollapsed = () => setCollapsed((c) => {
        const next = !c;
        localStorage.setItem('sb', next ? '1' : '0');
        return next;
    });
    const toggleDark = () => setDark((d) => {
        const next = !d;
        document.documentElement.classList.toggle('dark', next);
        localStorage.setItem('theme', next ? 'dark' : 'light');
        return next;
    });
    const showLabels = !collapsed || mobileOpen;

    // Header carries content only when there's a line context or no sidebar to
    // host the logo/user/logout.
    const showHeader = line || !hasSidebar;

    return (
        <div className="flex h-screen overflow-hidden bg-gray-100 dark:bg-gray-900">
            {hasSidebar && (
                <Sidebar
                    auth={auth}
                    alertCount={nav?.alertCount ?? 0}
                    csrfToken={csrf_token}
                    appVersion={appVersion}
                    path={path}
                    collapsed={collapsed}
                    mobileOpen={mobileOpen}
                    showLabels={showLabels}
                    dark={dark}
                    onToggleCollapsed={toggleCollapsed}
                    onToggleDark={toggleDark}
                    onCloseMobile={() => setMobileOpen(false)}
                />
            )}

            <div className="flex flex-col flex-1 min-w-0 overflow-hidden">
                {showHeader && (
                    <header className="shrink-0 bg-slate-900 text-slate-100 shadow">
                        <div className="flex items-center gap-4 px-4 h-16">
                            {!hasSidebar && (
                                <Link href="/operator/select-line" className="flex items-center gap-2 shrink-0">
                                    <img src="/logo_open_mes.png" alt="OpenMES" className="h-8 w-8 object-contain" />
                                    <span className="font-bold text-sm hidden sm:block">OpenMES</span>
                                </Link>
                            )}

                            {line && (
                                <div className="min-w-0">
                                    <p className="text-base font-bold leading-tight truncate">{line.name}</p>
                                    {selectedWorkstation && (
                                        <p className="text-xs text-slate-400 truncate">{selectedWorkstation.name}</p>
                                    )}
                                </div>
                            )}

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
                                        className="px-3 py-2 rounded-lg text-sm font-medium text-slate-300 hover:bg-slate-700 hover:text-white transition-colors"
                                    >
                                        Switch Line
                                    </Link>
                                </nav>
                            )}

                            {!hasSidebar && (
                                <div className={`flex items-center gap-3 ${line ? '' : 'ml-auto'}`}>
                                    <div className="flex items-center gap-2">
                                        <div className="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm font-bold">
                                            {auth?.user?.initial ?? '?'}
                                        </div>
                                        <span className="text-sm hidden md:block">{auth?.user?.name}</span>
                                    </div>
                                    <form action="/logout" method="POST">
                                        <input type="hidden" name="_token" value={csrf_token} />
                                        <button
                                            type="submit"
                                            title="Logout"
                                            className="p-2 rounded-md text-slate-400 hover:text-red-400 hover:bg-slate-700 transition-colors"
                                        >
                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            )}
                        </div>
                    </header>
                )}

                <main className="flex-1 overflow-auto p-4 md:p-6">
                    <FlashMessages />
                    {children}
                </main>
            </div>
        </div>
    );
}

function TopLink({ href, active, children }) {
    return (
        <Link
            href={href}
            className={`px-4 py-2 rounded-lg text-sm font-semibold transition-colors ${
                active ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white'
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
        ['success', 'bg-green-100 border-green-300 text-green-800'],
        ['error', 'bg-red-100 border-red-300 text-red-800'],
        ['warning', 'bg-amber-100 border-amber-300 text-amber-800'],
        ['info', 'bg-blue-100 border-blue-300 text-blue-800'],
    ].filter(([k]) => flash[k]);
    if (!items.length) return null;
    return (
        <div className="mb-4 space-y-2 max-w-3xl mx-auto">
            {items.map(([k, cls]) => (
                <div key={k} className={`p-3 rounded-lg border text-sm ${cls}`}>{flash[k]}</div>
            ))}
        </div>
    );
}
