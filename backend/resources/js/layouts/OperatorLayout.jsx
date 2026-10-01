import { Link, router, usePage } from '@inertiajs/react';
import { OnlineDot, ToastProvider } from '@openmes/ui';
import Tooltip from '../components/Tooltip';
import { Hook } from '../lib/hooks';
import { __ } from '../lib/i18n';

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
    const props = usePage().props;
    const {
        auth, line, selectedWorkstation, csrf_token, moduleNav,
        operatorTabs = [], operatorCanLogout = true, operatorHooks = {}, operatorCanSwitchLine = true,
    } = props;
    // Tabs an enabled module registered via MenuRegistry::addOperatorItem().
    const moduleTabs = moduleNav?.operator ?? [];
    const path = typeof window !== 'undefined' ? window.location.pathname : '';
    // Matched at a path boundary, so a tab for /operator/team is not also
    // active on /operator/teams.
    const isActive = (prefix) => path === prefix || path.startsWith(prefix.endsWith('/') ? prefix : `${prefix}/`);
    // The selected line and bench travel in the address, so a station link
    // opens the same context when it is bookmarked, shared or reloaded.
    // A page that does not say which bench it is on (the quantity correction)
    // leaves the bench out, so a tab click keeps the one the session holds.
    const knowsBench = Object.prototype.hasOwnProperty.call(props, 'selectedWorkstation');
    const ctx = line ? `?line=${line.id}${knowsBench ? `&workstation=${selectedWorkstation?.id ?? 'all'}` : ''}` : '';
    // The core tabs (the server keeps only the ones this bench needs) carry that context.
    const withCtx = (url) => (url.startsWith('/operator/') && !url.includes('?') ? `${url}${ctx}` : url);

    return (
        <ToastProvider dismissLabel={__('Close')}>
        <div className="min-h-screen flex flex-col bg-om-bg font-sans">
            <header className="shrink-0 bg-om-card border-b border-om-line">
                {/* One row from lg up; below that the links take a full row of their own
                    under the line and user, and the bar grows with them. */}
                <div className="flex flex-wrap items-center gap-3 px-4 py-2 min-h-16 lg:h-16">
                    <Link href="/operator/select-line" className="flex items-center shrink-0">
                        <img src="/logo_open_mes.png" alt="OpenMES" className="h-8 w-auto" />
                    </Link>

                    {line && (
                        <div className="min-w-0 flex-1 lg:flex-none border-l border-om-line pl-4">
                            <p className="text-[15px] font-semibold leading-tight text-om-ink truncate">{line.name}</p>
                            {selectedWorkstation && (
                                <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint truncate">{selectedWorkstation.name}</p>
                            )}
                        </div>
                    )}

                    <OnlineDot label={__("ONLINE")} pulse className="hidden md:inline-flex shrink-0" />

                    {line && (
                        <nav className="order-last w-full flex flex-wrap items-center gap-2 pt-2 border-t border-om-line lg:order-none lg:w-auto lg:ml-auto lg:pt-0 lg:border-t-0">
                            {/* Core tabs arrive as data (filter `operator.tabs`), so a module can drop or reorder them. */}
                            {operatorTabs.map((tab) => (
                                <TopLink key={tab.key} href={withCtx(tab.url)} active={(tab.prefixes ?? [tab.url]).some(isActive)}>
                                    {__(tab.label)}
                                </TopLink>
                            ))}
                            {moduleTabs.map((tab) => (
                                <TopLink key={tab.url} href={tab.url} active={isActive(tab.prefix)} module>
                                    {__(tab.label)}
                                </TopLink>
                            ))}
                            {operatorCanSwitchLine && (
                                <Link
                                    href="/operator/select-line"
                                    className="px-3 py-2.5 rounded-om-sm text-sm font-medium text-om-muted border border-om-line hover:bg-om-chip hover:text-om-ink transition-colors"
                                >
                                    {__('Switch Line')}
                                </Link>
                            )}
                        </nav>
                    )}

                    <div className={`flex items-center gap-3 ${line ? 'ml-auto lg:ml-0' : 'ml-auto'}`}>
                        <div className="flex items-center gap-2">
                            <div className="w-8 h-8 rounded-full bg-om-ink flex items-center justify-center text-om-on-ink text-sm font-semibold">
                                {auth?.user?.initial ?? '?'}
                            </div>
                            <span className="text-sm text-om-ink hidden md:block">{auth?.user?.name}</span>
                        </div>
                        {operatorCanLogout && (
                            <form action="/logout" method="POST">
                                <input type="hidden" name="_token" value={csrf_token} />
                                <Tooltip label="Logout">
                                    <button
                                        type="submit"
                                        aria-label="Logout"
                                        className="p-2.5 rounded-om-sm text-om-faint hover:text-om-blocked hover:bg-om-chip transition-colors"
                                    >
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                        </svg>
                                    </button>
                                </Tooltip>
                            </form>
                        )}
                    </div>
                </div>
            </header>

            <main className="flex-1 overflow-auto p-4 md:p-6">
                <Hook name="display.operator.layout" hooks={operatorHooks} />
                <FlashMessages />
                {children}
            </main>
        </div>
        </ToastProvider>
    );
}

// `module` marks a tab an installed module contributed: tinted with the accent
// so the panel shows at a glance which tabs are core and which are not.
function TopLink({ href, active, module = false, children }) {
    return (
        <Link
            href={href}
            className={`px-4 py-2.5 rounded-om-sm text-sm font-semibold transition-colors ${
                active ? 'bg-om-ink text-om-on-ink' : module ? 'bg-om-accent-bg text-om-accent hover:bg-om-accent hover:text-om-on-ink' : 'text-om-muted hover:bg-om-chip hover:text-om-ink'
            }${module && active ? ' ring-2 ring-om-accent' : ''}`}
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
