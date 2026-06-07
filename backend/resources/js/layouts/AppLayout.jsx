import { useEffect, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { ICONS, ADMIN_LINKS, ADMIN_GROUPS } from './adminNav';
import LiveAlertCount from '../components/LiveAlertCount';
import { LiveShapesProvider } from '../components/LiveShapesProvider';
import { __ } from '../lib/i18n';

/**
 * App chrome (sidebar + header) for authenticated React pages.
 *
 * This is a PERSISTENT Inertia layout (pages opt in via
 * `Page.layout = (page) => <AppLayout>{page}</AppLayout>`), so it stays mounted
 * across client-side navigations — the sidebar, its collapse/dark-mode state,
 * and the live alert badge's Electric subscription survive page changes.
 *
 * Nav uses Inertia <Link> (XHR, swaps only the page component — no full reload).
 * Active state is derived from the REACTIVE `usePage().url` (not
 * window.location, which wouldn't update while the layout stays mounted).
 *
 * Persists collapse (`sb`) + dark mode (`theme`) in localStorage.
 */
export default function AppLayout({ children }) {
    const page = usePage();
    const { auth, nav, csrf_token, appVersion } = page.props;
    // usePage().url is reactive across SPA navigation; strip the query string
    // so prefix matching for active-state works (e.g. /admin/work-orders?status=).
    const path = (page.url || '').split('?')[0];

    const [collapsed, setCollapsed] = useState(
        () => typeof window !== 'undefined' && localStorage.getItem('sb') === '1',
    );
    const [mobileOpen, setMobileOpen] = useState(false);
    const [dark, setDark] = useState(
        () => typeof document !== 'undefined' && document.documentElement.classList.contains('dark'),
    );

    const toggleCollapsed = () => {
        setCollapsed((c) => {
            const next = !c;
            localStorage.setItem('sb', next ? '1' : '0');
            return next;
        });
    };

    const toggleDark = () => {
        setDark((d) => {
            const next = !d;
            document.documentElement.classList.toggle('dark', next);
            localStorage.setItem('theme', next ? 'dark' : 'light');
            return next;
        });
    };

    const showLabels = !collapsed || mobileOpen;

    return (
        <LiveShapesProvider>
        <div className="flex h-screen overflow-hidden bg-gray-100 dark:bg-gray-900">
            <LiveAlertCount fallback={nav?.alertCount ?? 0}>
                {(alertCount) => (
                    <Sidebar
                        auth={auth}
                        alertCount={alertCount}
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
            </LiveAlertCount>

            {/* Mobile backdrop */}
            {mobileOpen && (
                <div
                    onClick={() => setMobileOpen(false)}
                    className="fixed inset-0 bg-black/50 z-30 lg:hidden"
                />
            )}

            <div className="flex flex-col flex-1 min-w-0 overflow-hidden">
                {/* Mobile top bar */}
                <header className="lg:hidden shrink-0 flex items-center gap-3 h-14 px-4 bg-white dark:bg-slate-800 border-b border-gray-200 dark:border-slate-700 shadow-sm z-20">
                    <button
                        onClick={() => setMobileOpen(true)}
                        className="p-2 rounded-md text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700"
                    >
                        <Icon d="M4 6h16M4 12h16M4 18h16" className="w-6 h-6" />
                    </button>
                    <img src="/logo_open_mes.png" alt="OpenMES" className="h-7" />
                </header>

                {/* Desktop clock (top-right) — ported from app.blade.php's Europe/Warsaw clock */}
                <DesktopClock />

                <main className="flex-1 overflow-auto p-4 md:p-6 lg:p-8">
                    <FlashMessages />
                    {children}
                </main>
            </div>
        </div>
        </LiveShapesProvider>
    );
}

function FlashMessages() {
    const { flash } = usePage().props;
    if (!flash?.success && !flash?.error) return null;
    return (
        <div className="mb-4 space-y-2">
            {flash.success && (
                <div className="p-3 rounded-lg bg-green-100 border border-green-300 text-green-800 text-sm">
                    {flash.success}
                </div>
            )}
            {flash.error && (
                <div className="p-3 rounded-lg bg-red-100 border border-red-300 text-red-800 text-sm">
                    {flash.error}
                </div>
            )}
        </div>
    );
}

/**
 * Desktop-only live clock shown top-right on every page (parity with the
 * Europe/Warsaw clock the Blade app.blade.php rendered above <main>). Isolated
 * so its per-second tick only re-renders this component, not the whole layout.
 * Formatted in the active locale; timezone pinned to Europe/Warsaw like the original.
 */
function DesktopClock() {
    const { locale } = usePage().props;
    const fmt = () => {
        const now = new Date();
        const tz = { timeZone: 'Europe/Warsaw' };
        return {
            date: now.toLocaleDateString(locale || 'en', { ...tz, weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }),
            time: now.toLocaleTimeString(locale || 'en', { ...tz, hour: '2-digit', minute: '2-digit', second: '2-digit' }),
        };
    };
    const [t, setT] = useState(fmt);
    useEffect(() => {
        const id = setInterval(() => setT(fmt()), 1000);
        return () => clearInterval(id);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [locale]);
    return (
        <div className="hidden lg:flex items-center justify-end px-4 py-1.5 shrink-0">
            <div className="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <Icon className="w-4 h-4" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                <span>{t.date}</span>
                <span className="font-mono font-semibold text-gray-700 dark:text-gray-200">{t.time}</span>
            </div>
        </div>
    );
}

function Sidebar({
    auth, alertCount, csrfToken, appVersion, path, collapsed, mobileOpen, showLabels,
    dark, onToggleCollapsed, onToggleDark, onCloseMobile,
}) {
    const isAdmin = auth?.user?.roles?.includes('Admin');
    const widthClass = collapsed ? 'lg:w-16' : 'lg:w-64';
    const translate = mobileOpen ? 'translate-x-0' : '-translate-x-full';

    return (
        <aside
            className={`fixed inset-y-0 left-0 z-40 flex flex-col shrink-0 bg-slate-900 text-slate-100 w-64
                        lg:relative lg:inset-auto lg:z-auto lg:translate-x-0 overflow-hidden
                        transition-[width,transform] duration-300 ease-in-out ${translate} ${widthClass}`}
        >
            {/* Logo / header */}
            <div className="flex items-center h-16 px-3 shrink-0 border-b border-slate-700/60">
                <Link href="/admin/dashboard" className="flex items-center gap-2.5 min-w-0 overflow-hidden">
                    <img src="/logo_open_mes.png" alt="OpenMES" className="h-8 w-8 shrink-0 object-contain" />
                    {showLabels && (
                        <span className="min-w-0 overflow-hidden leading-tight">
                            <span className="block text-white font-bold text-sm tracking-tight truncate">OpenMES</span>
                            {appVersion && <span className="block text-[10px] text-slate-400 truncate">{appVersion}</span>}
                        </span>
                    )}
                </Link>

                {/* Setup wizard (Admin only) — the "?" the demo shows in the header */}
                {showLabels && isAdmin && (
                    <Link
                        href="/onboarding/step/1"
                        prefetch
                        title={__('Setup Wizard')}
                        className="ml-auto p-1.5 rounded-full text-slate-400 hover:text-white hover:bg-slate-700 shrink-0"
                    >
                        <Icon d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" className="w-5 h-5" />
                    </Link>
                )}
                <button
                    onClick={onCloseMobile}
                    className={`lg:hidden ${showLabels && isAdmin ? '' : 'ml-auto'} p-1.5 rounded-md text-slate-400 hover:text-white hover:bg-slate-700 shrink-0`}
                >
                    <Icon d="M6 18L18 6M6 6l12 12" className="w-5 h-5" />
                </button>
            </div>

            {/* Navigation */}
            <nav className="sidebar-scroll flex-1 overflow-y-auto overflow-x-hidden py-3 space-y-0.5">
                {ADMIN_LINKS.map((link) => (
                    <NavLink
                        key={link.href}
                        link={link}
                        path={path}
                        collapsed={collapsed}
                        showLabels={showLabels}
                        alertCount={link.alert ? alertCount : 0}
                    />
                ))}

                {/* Separator under the top links (parity with the Blade sidebar) */}
                {showLabels && <div className="mx-4 my-2 border-t border-slate-700/60" />}

                {ADMIN_GROUPS.map((group) => (
                    <NavGroup
                        key={group.key}
                        group={group}
                        path={path}
                        collapsed={collapsed}
                        showLabels={showLabels}
                    />
                ))}
            </nav>

            {/* Footer */}
            <div className="border-t border-slate-700/60 shrink-0">
                {/* Dark mode toggle */}
                <div className="px-2 pt-2">
                    <button
                        onClick={onToggleDark}
                        className={`flex items-center gap-3 w-full px-3 py-2.5 rounded-lg text-sm font-medium
                                    text-slate-300 hover:bg-slate-700 hover:text-white transition-colors
                                    ${collapsed && !mobileOpen ? 'justify-center !px-0' : ''}`}
                    >
                        <Icon
                            className="w-5 h-5 shrink-0"
                            d={dark
                                ? 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z'
                                : 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z'}
                        />
                        {showLabels && <span>{dark ? __('Light Mode') : __('Dark Mode')}</span>}
                    </button>
                </div>

                {/* Settings */}
                <div className="px-2 pt-2">
                    <Link
                        href="/settings"
                        prefetch
                        className={`flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                                    ${isActive(path, ['/settings'])
                                        ? 'bg-blue-600 text-white'
                                        : 'text-slate-300 hover:bg-slate-700 hover:text-white'}
                                    ${collapsed && !mobileOpen ? 'justify-center !px-0' : ''}`}
                    >
                        <Icon d={ICONS.settings} className="w-5 h-5 shrink-0" />
                        {showLabels && <span>{__('Settings')}</span>}
                    </Link>
                </div>

                {/* User + logout */}
                <div className="px-2 py-2">
                    <div className={`flex items-center gap-3 px-3 py-2 rounded-lg ${collapsed && !mobileOpen ? 'justify-center' : ''}`}>
                        {/* Avatar + name link to the user's own profile/settings */}
                        <Link
                            href="/settings/profile"
                            prefetch
                            title={__('Profile')}
                            className={`flex items-center gap-3 min-w-0 rounded-md hover:bg-slate-700 transition-colors
                                        ${collapsed && !mobileOpen ? '' : 'flex-1 -ml-1 pl-1 pr-2 py-0.5'}`}
                        >
                            <div className="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center shrink-0 text-white text-sm font-bold">
                                {auth?.user?.initial ?? '?'}
                            </div>
                            {showLabels && (
                                <div className="flex-1 min-w-0 text-left">
                                    <p className="text-sm font-medium text-white truncate">{auth?.user?.name}</p>
                                    <p className="text-xs text-slate-400 truncate">{auth?.user?.roles?.[0] ?? 'User'}</p>
                                </div>
                            )}
                        </Link>
                        <form action="/logout" method="POST" className="shrink-0">
                            <input type="hidden" name="_token" value={csrfToken} />
                            <button
                                type="submit"
                                title={__('Logout')}
                                className="p-1.5 rounded-md text-slate-400 hover:text-red-400 hover:bg-slate-700 transition-colors"
                            >
                                <Icon d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" className="w-4 h-4" />
                            </button>
                        </form>
                    </div>
                    {showLabels && <LocaleSwitcher />}
                </div>

                {/* Collapse toggle (desktop) */}
                <div className="hidden lg:flex border-t border-slate-700/60 px-2 py-2">
                    <button
                        onClick={onToggleCollapsed}
                        className="flex items-center justify-center w-full py-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-700 transition-colors"
                        title={collapsed ? __('Expand sidebar') : __('Collapse sidebar')}
                    >
                        <Icon
                            className={`w-5 h-5 transition-transform ${collapsed ? 'rotate-180' : ''}`}
                            d="M11 19l-7-7 7-7m8 14l-7-7 7-7"
                        />
                        {!collapsed && <span className="ml-2 text-sm">{__('Collapse')}</span>}
                    </button>
                </div>
            </div>
        </aside>
    );
}

/**
 * Language switcher. Navigates to /locale/{code} with a FULL reload (not Inertia
 * <Link>) on purpose, so app.jsx re-bootstraps and loads the new locale's
 * translation chunk. Hidden when only one locale is available.
 */
function LocaleSwitcher() {
    const { locale, locales } = usePage().props;
    if (!locales || Object.keys(locales).length <= 1) return null;

    return (
        <select
            value={locale}
            onChange={(e) => { window.location.href = `/locale/${e.target.value}`; }}
            aria-label="Language"
            className="w-full mt-1 bg-slate-800 text-slate-300 text-xs rounded-md border border-slate-700 px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
            {Object.entries(locales).map(([code, label]) => (
                <option key={code} value={code}>{label}</option>
            ))}
        </select>
    );
}

function NavLink({ link, path, collapsed, showLabels, alertCount }) {
    const active = isActive(path, link.match, link.exact);
    const activeClass = link.alert && active ? 'bg-red-600 text-white' : active ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white';
    return (
        <div className="relative group px-2">
            <Link
                href={link.href}
                prefetch
                className={`flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                            ${activeClass} ${collapsed && !showLabels ? 'justify-center !px-0' : ''}`}
            >
                <span className="relative shrink-0">
                    <Icon d={ICONS[link.icon]} className="w-5 h-5" />
                    {alertCount > 0 && (
                        <span className="absolute -top-1.5 -right-1.5 flex items-center justify-center w-4 h-4 rounded-full bg-red-500 text-white text-[10px] font-bold leading-none">
                            {alertCount > 9 ? '9+' : alertCount}
                        </span>
                    )}
                </span>
                {showLabels && (
                    <span className="flex items-center gap-2">
                        {__(link.label)}
                        {link.alert && alertCount > 0 && (
                            <span className="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full bg-red-500 text-white text-xs font-bold">
                                {alertCount}
                            </span>
                        )}
                    </span>
                )}
            </Link>
            {collapsed && !showLabels && <Tooltip>{__(link.label)}</Tooltip>}
        </div>
    );
}

function NavGroup({ group, path, collapsed, showLabels }) {
    const groupActive = isActive(path, group.match);
    const [open, setOpen] = useState(groupActive);

    // The layout persists across SPA navigation, so auto-expand a group when you
    // navigate into one of its pages (without forcing it closed when you leave —
    // the user's manual expand/collapse is preserved).
    useEffect(() => {
        if (groupActive) setOpen(true);
    }, [groupActive]);

    // Collapsed sidebar can't show expanded children; clicking a collapsed
    // group expands the sidebar first (parity with Blade expandGroup()).
    const toggle = () => setOpen((o) => !o);

    return (
        <div className="px-2">
            <div className="relative group">
                <button
                    onClick={toggle}
                    className={`flex items-center gap-3 w-full px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                                text-slate-300 hover:bg-slate-700 hover:text-white
                                ${collapsed && !showLabels ? 'justify-center !px-0' : ''}
                                ${groupActive && showLabels ? 'bg-slate-700/50 text-white' : ''}`}
                >
                    <Icon d={ICONS[group.icon]} className="w-5 h-5 shrink-0" />
                    {showLabels && <span className="flex-1 text-left">{__(group.label)}</span>}
                    {showLabels && (
                        <Icon
                            d="M19 9l-7 7-7-7"
                            className={`w-4 h-4 shrink-0 transition-transform ${open ? 'rotate-180' : ''}`}
                        />
                    )}
                </button>
                {collapsed && !showLabels && <Tooltip>{__(group.label)}</Tooltip>}
            </div>

            {open && showLabels && (
                <div className="mt-0.5 ml-4 space-y-0.5 border-l border-slate-700/60 pl-3">
                    {group.children.map((child) =>
                        child.children ? (
                            <SubGroup key={child.key} group={child} path={path} />
                        ) : (
                            <ChildLink key={child.href} child={child} path={path} />
                        ),
                    )}
                </div>
            )}
        </div>
    );
}

function SubGroup({ group, path }) {
    const active = isActive(path, group.match);
    const [open, setOpen] = useState(active);
    useEffect(() => {
        if (active) setOpen(true);
    }, [active]);
    return (
        <div>
            <button
                onClick={() => setOpen((o) => !o)}
                className={`flex items-center gap-2 w-full px-2 py-1.5 rounded-md text-sm transition-colors
                            ${active ? 'text-blue-400 font-medium' : 'text-slate-400 hover:text-white hover:bg-slate-700'}`}
            >
                <span className="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60" />
                {__(group.label)}
                <Icon
                    d="M19 9l-7 7-7-7"
                    className={`w-3 h-3 ml-auto shrink-0 transition-transform ${open ? 'rotate-180' : ''}`}
                />
            </button>
            {open && (
                <div className="ml-3 mt-0.5 space-y-0.5 border-l border-slate-700/40 pl-3">
                    {group.children.map((child) => (
                        <ChildLink key={child.href} child={child} path={path} dot="sm" />
                    ))}
                </div>
            )}
        </div>
    );
}

function ChildLink({ child, path, dot }) {
    const active = isActive(path, child.match, child.exact);
    const dotClass = dot === 'sm' ? 'w-1 h-1 opacity-50' : 'w-1.5 h-1.5 opacity-60';

    // Disabled "coming soon" entry (e.g. Modules → Store) — non-clickable span
    // with a badge, matching the Blade sidebar's disabled item styling.
    if (child.disabled) {
        return (
            <span
                title={child.title ? __(child.title) : undefined}
                className="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-slate-600 cursor-not-allowed select-none"
            >
                <span className={`rounded-full bg-current shrink-0 ${dotClass}`} />
                {__(child.label)}
                {child.badge && (
                    <span className="ml-auto text-[10px] font-medium bg-slate-700 text-slate-400 px-1.5 py-0.5 rounded">
                        {__(child.badge)}
                    </span>
                )}
            </span>
        );
    }

    return (
        <Link
            href={child.href}
            prefetch
            className={`flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-colors
                        ${active ? 'text-blue-400 font-medium' : 'text-slate-400 hover:text-white hover:bg-slate-700'}`}
        >
            <span className={`rounded-full bg-current shrink-0 ${dotClass}`} />
            {__(child.label)}
        </Link>
    );
}

function Tooltip({ children }) {
    return (
        <span className="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-slate-700 text-white text-xs rounded-md whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity shadow-lg pointer-events-none">
            {children}
        </span>
    );
}

function Icon({ d, className }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={d} />
        </svg>
    );
}

/**
 * Active if the current path matches (prefix, or exact when `exact`) any of the
 * given match prefixes.
 */
function isActive(path, matches = [], exact = false) {
    return matches.some((m) => (exact ? path === m : path === m || path.startsWith(m + '/') || path === m));
}
