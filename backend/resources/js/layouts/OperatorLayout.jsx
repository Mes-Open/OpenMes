import { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Icon, ToastProvider } from '@openmes/ui';
import Tooltip from '../components/Tooltip';
import ViewAsBar from '../components/ViewAsBar';
import ShiftStrip, { shiftTimes } from '../components/operator/ShiftStrip';
import { initialsOf, pillCls } from '../components/operator/operatorUi';
import { Hook } from '../lib/hooks';
import { __ } from '../lib/i18n';

/**
 * Full-screen tablet chrome for operator screens (design ref: OpenMES
 * Operator.dc.html): one top bar with the line and shift, the view switch
 * (Queue / Workstation / module tabs), connection, theme and the operator;
 * under it the shift strip when the page sends a `shiftSummary`.
 *
 * Pages opt in via:  Page.layout = (page) => <OperatorLayout>{page}</OperatorLayout>
 * A page that lays out its own columns to the viewport's height (the list +
 * execution panel split) passes `flush`: no padding, and it scrolls itself.
 *
 * Reads from shared Inertia props: auth, line, selectedWorkstation, csrf_token,
 * flash. `line` is present once a line is selected (all operator pages but
 * select-line pass it).
 */
export default function OperatorLayout({ children, flush = false }) {
    const props = usePage().props;
    const {
        auth, line, selectedWorkstation, csrf_token, moduleNav, shiftSummary,
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
    // Admin tabs granted to this operator in Settings → Access. When there are
    // any, a sidebar lists them (with "Lines" first) so the operator can reach
    // those panel pages; without a grant the screen stays plain full-screen.
    const tabLinks = (auth?.user?.accessibleTabLinks ?? []).filter((tab) => tab.url);

    // Line sub-caption: code · shift and its hours · the bench (or how many the line has).
    const shift = shiftSummary?.shift;
    const caption = [
        line?.code,
        shift?.code ? `${shift.code} ${shiftTimes(shift)}` : null,
        selectedWorkstation?.name
            ?? (shiftSummary?.workstations ? __(':count workstations', { count: shiftSummary.workstations }) : null),
    ].filter(Boolean).join(' · ');

    return (
        <ToastProvider dismissLabel={__('Close')}>
        <div className={`${flush ? 'h-screen' : 'min-h-screen'} flex bg-om-bg font-sans`}>
            {tabLinks.length > 0 && <OperatorSidebar tabLinks={tabLinks} isActive={isActive} />}
            <div className="flex flex-col flex-1 min-w-0">
            {/* Administrator's view switcher, in the document flow above the bar so
                it never covers a touch target. Renders nothing for operators. */}
            <ViewAsBar />
            <header className="shrink-0 bg-om-card border-b border-om-line">
                {/* One row from xl up; below that the view switch takes a row of its
                    own under the line and the operator, and the bar grows with it. */}
                <div className="flex flex-wrap items-center gap-x-4 gap-y-2 px-[22px] py-2 min-h-[60px]">
                    <Link href="/operator/select-line" className="flex items-center gap-[9px] shrink-0">
                        <img src="/logo_open_mes.png" alt="OpenMES" className="h-7 w-auto" />
                        <span className="font-mono text-[9px] text-om-faint border border-om-line rounded px-[5px] py-px">{__('OPERATOR')}</span>
                    </Link>

                    {line && (
                        <>
                            <div className="hidden sm:block w-px h-[26px] bg-om-line2" />
                            <div className="min-w-0 flex items-center gap-2.5">
                                <div className="min-w-0">
                                    <p className="text-[14.5px] font-semibold leading-tight text-om-ink truncate">{line.name}</p>
                                    {caption && (
                                        <p className="font-mono text-[9.5px] uppercase tracking-[0.05em] text-om-faint truncate">{caption}</p>
                                    )}
                                </div>
                                {operatorCanSwitchLine && (
                                    <Link href="/operator/select-line" className="ml-1.5 shrink-0 text-[12px] text-om-accent hover:text-om-ink">
                                        {__('Change line')}
                                    </Link>
                                )}
                            </div>
                        </>
                    )}

                    {line && (operatorTabs.length > 0 || moduleTabs.length > 0) && (
                        <nav className="order-last w-full xl:order-none xl:w-auto xl:ml-[18px] flex">
                            <div className="flex flex-wrap gap-[3px] bg-om-bg border border-om-line rounded-[10px] p-[3px]">
                                {/* Core tabs arrive as data (filter `operator.tabs`), so a module can drop or reorder them. */}
                                {operatorTabs.map((tab) => (
                                    <Link key={tab.key} href={withCtx(tab.url)} className={pillCls((tab.prefixes ?? [tab.url]).some(isActive))}>
                                        {__(tab.label)}
                                    </Link>
                                ))}
                                {moduleTabs.map((tab) => (
                                    <ModuleTab key={tab.url} href={tab.url} active={isActive(tab.prefix)}>
                                        {__(tab.label)}
                                    </ModuleTab>
                                ))}
                            </div>
                        </nav>
                    )}

                    <div className="flex-1" />

                    <div className="hidden md:flex items-center gap-[7px] px-[11px] py-1.5 border border-om-line2 rounded-lg">
                        <span className="w-[7px] h-[7px] rounded-full bg-om-running animate-om-pulse" />
                        <span className="font-mono text-[10px] tracking-[0.06em] text-om-running">{__('ONLINE')}</span>
                    </div>

                    <ThemeSwitch />

                    <div className="flex items-center gap-[9px]">
                        <span className="w-[30px] h-[30px] rounded-full bg-om-ink text-om-on-ink flex items-center justify-center text-[11px] font-semibold shrink-0">
                            {initialsOf(auth?.user?.name)}
                        </span>
                        <div className="hidden md:block leading-[1.25]">
                            <div className="text-[12.5px] font-medium text-om-ink">{auth?.user?.name}</div>
                            {auth?.user?.username && (
                                <div className="font-mono text-[9.5px] text-om-faint">{auth.user.username}</div>
                            )}
                        </div>
                        {operatorCanLogout && (
                            <form action="/logout" method="POST">
                                <input type="hidden" name="_token" value={csrf_token} />
                                <Tooltip label={__('Logout')}>
                                    <button
                                        type="submit"
                                        aria-label={__('Logout')}
                                        className="p-2 rounded-om-sm text-om-faint hover:text-om-blocked hover:bg-om-chip transition-colors cursor-pointer"
                                    >
                                        <Icon name="log-out" size={18} />
                                    </button>
                                </Tooltip>
                            </form>
                        )}
                    </div>
                </div>
            </header>

            <ShiftStrip summary={shiftSummary} />

            {flush ? (
                <main className="flex-1 min-h-0 flex flex-col">
                    <div className="empty:hidden shrink-0 px-[22px] pt-3">
                        <Hook name="display.operator.layout" hooks={operatorHooks} />
                        <FlashMessages />
                    </div>
                    <div className="flex-1 min-h-0">{children}</div>
                </main>
            ) : (
                <main className="flex-1 overflow-auto p-4 md:p-6">
                    <Hook name="display.operator.layout" hooks={operatorHooks} />
                    <FlashMessages />
                    {children}
                </main>
            )}
            </div>
        </div>
        </ToastProvider>
    );
}

/**
 * Left sidebar on operator screens for an operator granted admin tabs
 * (Settings → Access): "Lines" (back to line selection) first, then each
 * granted tab, mirroring the panel sidebar so navigation works both ways.
 */
function OperatorSidebar({ tabLinks, isActive }) {
    return (
        <aside aria-label={__('Navigation')} className="shrink-0 w-44 bg-om-card border-r border-om-line flex flex-col">
            <nav className="flex-1 overflow-y-auto py-3 px-2 space-y-1">
                <SideLink href="/operator/select-line" active={isActive('/operator')}>{__('Lines')}</SideLink>
                {tabLinks.map((tab) => (
                    <SideLink key={tab.key} href={tab.url}>{__(tab.label)}</SideLink>
                ))}
            </nav>
        </aside>
    );
}

function SideLink({ href, active = false, children }) {
    return (
        <Link
            href={href}
            className={`block px-3 py-3 rounded-om-sm text-sm font-semibold transition-colors ${
                active ? 'bg-om-ink text-om-on-ink' : 'text-om-muted hover:bg-om-chip hover:text-om-ink'
            }`}
        >
            {children}
        </Link>
    );
}

// `module` tabs are the ones an installed module contributed: tinted with the
// accent so the bar shows at a glance which views are core and which are not.
function ModuleTab({ href, active, children }) {
    return (
        <Link
            href={href}
            className={`px-3.5 py-1.5 rounded-[7px] text-[12.5px] font-medium transition-colors whitespace-nowrap ${
                active ? 'bg-om-ink text-om-on-ink ring-2 ring-om-accent' : 'bg-om-accent-bg text-om-accent hover:bg-om-accent hover:text-om-on-ink'
            }`}
        >
            {children}
        </Link>
    );
}

/**
 * Light / Dark — the same switch as the staff sidebar: the `dark` class on
 * <html> (the om-* tokens flip with it) remembered as `theme` in localStorage,
 * which inertia.blade.php applies before first paint.
 */
function ThemeSwitch() {
    const [dark, setDark] = useState(() => typeof document !== 'undefined' && document.documentElement.classList.contains('dark'));
    const set = (next) => {
        setDark(next);
        document.documentElement.classList.toggle('dark', next);
        try { localStorage.setItem('theme', next ? 'dark' : 'light'); } catch { /* private mode: the choice lasts this page only */ }
    };
    return (
        <div className="flex gap-[3px] bg-om-bg border border-om-line rounded-[9px] p-[3px]" role="group" aria-label={__('Theme')}>
            <button type="button" aria-pressed={!dark} onClick={() => set(false)} className={pillCls(!dark)}>{__('Light')}</button>
            <button type="button" aria-pressed={dark} onClick={() => set(true)} className={pillCls(dark)}>{__('Dark')}</button>
        </div>
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
