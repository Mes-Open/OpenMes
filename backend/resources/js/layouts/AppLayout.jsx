import { createContext, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { ICONS, ICON_LUCIDE, ADMIN_LINKS, ADMIN_GROUPS } from './adminNav';
import { SUPERVISOR_LINKS, SUPERVISOR_GROUPS } from './supervisorNav';
import LiveAlertCount from '../components/LiveAlertCount';
import LatestAlerts from '../components/LatestAlerts';
import Tooltip from '../components/Tooltip';
import HoverPanel from '../components/HoverPanel';
import { ToastProvider } from '@openmes/ui';
import { LiveShapesProvider } from '../components/LiveShapesProvider';
import { __ } from '../lib/i18n';
import { Breadcrumbs, Icon as UiIcon } from '@openmes/ui';

// ── Module menu hooks ────────────────────────────────────────────────────────
// Modules register nav entries server-side via MenuRegistry; HandleInertiaRequests
// exposes them as the `moduleNav` prop. We merge them into the static admin nav
// here so module hooks render in the SPA (they used to render in the deleted
// Blade sidebar). MenuRegistry's built-in group key `admin` maps to the React
// dropdown key `adminGroup`.
const MODULE_GROUP_ALIASES = { admin: 'adminGroup' };

// Module pages are legacy server-rendered (Blade), not Inertia components, so
// their links must trigger a full navigation (`external`) — an Inertia <Link>
// would fetch JSON for a non-Inertia route and fail.
function moduleItemToChild(item) {
    return { label: item.label, href: item.url, match: [item.url], external: true };
}

/**
 * How many `PageTitle`s are currently mounted. The header shows a trail derived
 * from the nav only when a page hasn't supplied one of its own — a count rather
 * than a boolean because React mounts and unmounts components in either order
 * during a page transition, and two mounts followed by one unmount must not read
 * as "nothing here".
 */
export const PageTitleContext = createContext({ register: () => () => {} });

function usePageTitlePresence(navTrail) {
    const [count, setCount] = useState(0);
    const register = useMemo(
        () => () => {
            setCount((n) => n + 1);
            return () => setCount((n) => n - 1);
        },
        [],
    );
    // `navTrail` rides along so a page that renders its own title can still
    // inherit the ancestors the nav already knows, instead of restating them.
    const value = useMemo(() => ({ register, navTrail }), [register, navTrail]);
    return [count > 0, value];
}

/**
 * The breadcrumb trail for a path, read off the sidebar nav: the group(s) the
 * page sits under, then the page itself.
 *
 * Deriving it beats asking ~180 pages to each declare their own — the nav
 * already knows where every route lives, so a page can never disagree with the
 * menu that got you there, and moving an entry in the nav moves its breadcrumb
 * with it. A page that wants something the nav can't know (a record's number)
 * still renders `PageTitle` and wins.
 */
/**
 * Drops a crumb that repeats the one before it. The nav legitimately nests a
 * landing page inside a group of the same name ("Production Lines" the section,
 * "Production Lines" the list), which is fine in a menu and reads as a stutter
 * in a trail.
 */
export function dedupeTrail(items) {
    return items.filter((item, i) => i === 0 || item.label !== items[i - 1].label);
}

function navTrailFor(path, groups, links) {
    let best = null;

    const consider = (node, chain) => {
        const matches = node.match ?? (node.href ? [node.href] : []);
        // Score on the prefixes that actually matched, not the longest in the
        // array: a group lists every child's path, so scoring the whole array let
        // "Work Orders" (which also owns /admin/work-orders) outrank the CSV
        // import child it matched through.
        const hit = matches.filter((m) => isActive(path, [m], node.exact));
        if (hit.length === 0) return;
        // Longest matching prefix wins, so /admin/schedule/capacity resolves to
        // the child rather than stopping at the group that also matches.
        const score = Math.max(...hit.map((m) => m.length));
        // Groups repeat their children's paths in `match` (that is how a group
        // highlights while you are inside it), so equal scores are common — the
        // deeper chain is the more specific answer.
        const better = !best || score > best.score
            || (score === best.score && chain.length > best.chain.length);
        if (better) best = { score, chain };
    };

    const SETTINGS = { label: 'Settings', href: '/settings', match: ['/settings'], lucide: 'settings' };
    [...links, SETTINGS].forEach((link) => consider(link, [link]));

    const walk = (nodes, chain) => {
        (nodes ?? []).forEach((node) => {
            consider(node, [...chain, node]);
            if (node.children) walk(node.children, [...chain, node]);
        });
    };
    walk(groups, []);

    if (!best) return [];

    const items = best.chain.map((node) => ({
        label: __(node.label),
        href: node.href,
        icon: node.lucide ?? ICON_LUCIDE[node.icon],
    }));

    // Every trail starts at the section's dashboard, matching the list pages' own.
    const root = navRoot(links);
    if (root && items[0]?.href !== root.href) {
        items.unshift({ label: __(root.label), href: root.href, icon: root.lucide ?? ICON_LUCIDE[root.icon] });
    }
    return dedupeTrail(items);
}

/**
 * Drop entries whose optional feature module this install has switched off, and
 * any group left empty by that.
 *
 * The admin tree gets this for free — its entries carry tab keys, and
 * TabRegistry already consults ModuleRegistry. The supervisor tree is gated by
 * role instead, so it states the module on the entry and is filtered here,
 * matching the `module:` middleware on the routes themselves.
 */
function withEnabledModules(nodes, enabledModules) {
    if (! Array.isArray(enabledModules)) {
        return nodes;
    }

    return nodes
        .filter((node) => ! node.module || enabledModules.includes(node.module))
        .map((node) => (node.children
            ? { ...node, children: withEnabledModules(node.children, enabledModules) }
            : node))
        .filter((node) => ! node.children || node.children.length > 0);
}

/**
 * A nav tree's home: /admin/dashboard for an admin, /supervisor/dashboard for a
 * supervisor. Derived rather than declared, so a third section can't set one and
 * forget the other.
 */
function navRoot(links) {
    return links.find((link) => link.href?.endsWith('/dashboard'));
}

// Merge module-registered hooks into ADMIN_GROUPS: inject items into built-in
// dropdowns (by aliased key) and append any custom top-level dropdowns.
function mergeModuleNav(moduleNav) {
    const items = moduleNav?.items ?? {};
    const groups = moduleNav?.groups ?? [];

    const injected = {};
    for (const [group, list] of Object.entries(items)) {
        const key = MODULE_GROUP_ALIASES[group] ?? group;
        injected[key] = (list ?? []).map(moduleItemToChild);
    }

    const base = ADMIN_GROUPS.map((g) =>
        injected[g.key]?.length ? { ...g, children: [...g.children, ...injected[g.key]] } : g,
    );

    const custom = groups.map((g) => ({
        key: `module:${g.id}`,
        label: g.label,
        icon: 'cube',
        moduleGroup: true,
        children: (g.items ?? []).map(moduleItemToChild),
    }));

    return [...base, ...custom];
}

/**
 * App chrome (sidebar + header) for authenticated React pages.
 *
 * This is a PERSISTENT Inertia layout (pages opt in via
 * `Page.layout = (page) => <AppLayout>{page}</AppLayout>`), so it stays mounted
 * across client-side navigations — the sidebar, its collapse/dark-mode state,
 * and the live alert badge's collection subscription survive page changes.
 *
 * Nav uses Inertia <Link> (XHR, swaps only the page component — no full reload).
 * Active state is derived from the REACTIVE `usePage().url` (not
 * window.location, which wouldn't update while the layout stays mounted).
 *
 * Persists collapse (`sb`) + dark mode (`theme`) in localStorage.
 *
 * STYLING (Geist White v1): this chrome is light-only for now — `dark:` variant
 * classes were removed; the dark shop-floor variant returns later via om-* token
 * theming. The theme toggle below stays functional (it still flips the `dark`
 * class + localStorage) but is visually neutral here.
 */
/** Marks every element a page's heading is portaled into (one per breakpoint). */
export const PAGE_TITLE_SLOT = '[data-page-title-slot]';

/**
 * The menu a user gets. Admins and supervisors have separate route trees, so
 * they get separate menus — pointing a supervisor at `/admin` would only send
 * them somewhere the middleware refuses.
 *
 * Admin wins when a user holds both roles: it is the larger tree, and it is the
 * one the per-tab access matrix governs. Module hooks register against the admin
 * groups, so they merge only there.
 */
function useNavTree() {
    const page = usePage();
    const isAdmin = page.props.auth?.user?.roles?.includes('Admin');
    const isSupervisor = page.props.auth?.user?.roles?.includes('Supervisor');
    const moduleNav = page.props.moduleNav;
    // Which admin tabs this user may open (#144) — role grants and enabled
    // modules, resolved server-side.
    const allowedTabs = page.props.auth?.user?.accessibleTabs;
    // Optional feature modules this install has switched on.
    const enabledModules = page.props.enabledModules;

    return useMemo(() => {
        if (! isAdmin && isSupervisor) {
            return {
                links: withEnabledModules(SUPERVISOR_LINKS, enabledModules),
                groups: withEnabledModules(SUPERVISOR_GROUPS, enabledModules),
                // The supervisor tree carries no tab keys: its gate is the route
                // group's role middleware, not the admin access matrix. Filtering
                // it through that matrix would hide every entry, since a
                // supervisor holds no tab:* grants.
                showTab: () => true,
            };
        }

        return {
            links: ADMIN_LINKS,
            groups: mergeModuleNav(moduleNav),
            showTab: (key) => ! Array.isArray(allowedTabs) || ! key || allowedTabs.includes(key),
        };
    }, [isAdmin, isSupervisor, moduleNav, allowedTabs, enabledModules]);
}

export default function AppLayout({ children }) {
    const page = usePage();
    const { auth, nav, csrf_token, appVersion } = page.props;
    // usePage().url is reactive across SPA navigation; strip the query string
    // so prefix matching for active-state works (e.g. /admin/work-orders?status=).
    const path = (page.url || '').split('?')[0];

    const [collapsed, setCollapsed] = useState(
        () => typeof window !== 'undefined' && localStorage.getItem('sb') === '1',
    );
    // Module groups matter here too: a module's page should sit under the group
    // the module registered, not fall off the trail entirely.
    const { links: navLinks, groups: navGroups } = useNavTree();
    const navTrail = useMemo(
        () => navTrailFor(path, navGroups, navLinks),
        [path, navGroups, navLinks],
    );
    const [hasPageTitle, pageTitleCtx] = usePageTitlePresence(navTrail);

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
        <PageTitleContext.Provider value={pageTitleCtx}>
        {/* App-wide toast stack — the replacement for window.alert(). */}
        <ToastProvider dismissLabel={__('Close')}>
        <div className="flex h-screen overflow-hidden bg-om-bg">
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
                <header className="lg:hidden shrink-0 flex items-center gap-3 h-14 px-4 bg-om-card border-b border-om-line z-20">
                    <button
                        onClick={() => setMobileOpen(true)}
                        className="p-2 rounded-om-sm text-om-muted hover:bg-om-chip hover:text-om-ink"
                    >
                        <UiIcon name="menu" size={24} />
                    </button>
                    <span className="flex shrink-0 items-center gap-2.5">
                        <img src="/logo_open_mes.png" alt="OpenMES" className="h-8 w-auto" />
                    </span>
                    {/* Below lg the desktop bar is hidden, so the page title lands
                        here beside the logo instead of falling back into the
                        content area. */}
                    <div data-page-title-slot className="flex min-w-0 items-center gap-3">
                        <NavTrail items={navTrail} show={!hasPageTitle} />
                    </div>
                </header>

                {/* Desktop clock (top-right) — ported from app.blade.php's Europe/Warsaw clock */}
                <DesktopClock
                    collapsed={collapsed}
                    onToggleCollapsed={toggleCollapsed}
                    navTrail={navTrail}
                    showNavTrail={!hasPageTitle}
                />

                <main className="flex-1 overflow-auto">
                    <FlashMessages />
                    {children}
                </main>
            </div>
        </div>
        </ToastProvider>
        </PageTitleContext.Provider>
        </LiveShapesProvider>
    );
}

/**
 * The default "that worked" for a write that redirects: any controller ending in
 * `->with('success'|'error', …)` lands here, and the page itself needs no code.
 *
 * In the document flow on purpose — it should push the page down and be read,
 * having just arrived from somewhere else. That makes it the wrong choice under
 * a rapid in-place interaction (dragging rows, say), where the shift moves what
 * you were looking at; those use `useToast()` instead.
 */
function FlashMessages() {
    const { flash } = usePage().props;
    if (!flash?.success && !flash?.error) return null;
    return (
        <div className="mb-4 space-y-2">
            {flash.success && (
                <div className="p-3 rounded-om-sm bg-om-running-bg border border-om-line text-om-running text-[13px]">
                    {__(flash.success)}
                </div>
            )}
            {flash.error && (
                <div className="p-3 rounded-om-sm bg-om-blocked-bg border border-om-line text-om-blocked text-[13px]">
                    {__(flash.error)}
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
function NavTrail({ items, show }) {
    if (!show || items.length === 0) return null;
    return <Breadcrumbs linkAs={Link} items={items} />;
}

function DesktopClock({ collapsed, onToggleCollapsed, navTrail = [], showNavTrail = false }) {
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
        // `h-11` and the hairline both match the sidebar's logo header, so the two
        // bars read as one band across the top with one rule under it, instead of
        // a step where they meet and a border that stops halfway.
        <div className="hidden lg:flex h-11 items-center justify-between gap-3 border-b border-om-line bg-om-card px-4 shrink-0">
            {/* Sidebar toggle. It lives here rather than at the foot of the rail
                because that is where the thing it controls begins — and the rail's
                own footer is the one part of it that scrolls out of reach. */}
            <Tooltip label={collapsed ? __('Expand sidebar') : __('Collapse sidebar')} placement="bottom">
                <button
                    type="button"
                    onClick={onToggleCollapsed}
                    aria-label={collapsed ? __('Expand sidebar') : __('Collapse sidebar')}
                    aria-expanded={!collapsed}
                    className="-ml-1 flex shrink-0 cursor-pointer items-center justify-center rounded-om-sm p-1.5 text-om-faint transition-colors hover:bg-om-chip hover:text-om-ink"
                >
                    <UiIcon name={collapsed ? 'panel-left-open' : 'panel-left-close'} size={18} />
                </button>
            </Tooltip>
            {/* Page-title slot. Pages portal their heading in here (see
                ResourceTable) so the title shares this bar with the clock
                instead of taking a row of its own above the list. */}
            <div data-page-title-slot className="flex min-w-0 flex-1 items-center gap-3">
                <NavTrail items={navTrail} show={showNavTrail} />
            </div>
            <div className="flex shrink-0 items-center gap-2 text-[13px] text-om-faint">
                <UiIcon name="clock" size={14} />
                <span>{t.date}</span>
                <span className="font-mono text-om-muted">{t.time}</span>
            </div>
        </div>
    );
}

/**
 * Flat list of every navigable sidebar item (top links, group/subgroup headers
 * that have their own landing page, and children) for the sidebar search.
 * `trail` is the group path shown under a result, e.g. "Production /
 * Production Lines". Disabled entries are skipped.
 */
// A nav group is visible when its own tab is accessible OR at least one of its
// descendants carries its own (different) accessible tab. The second clause lets
// a group survive when its header key is off but a fine-grained child module is
// on — e.g. Reports hidden but Advanced reports enabled. Groups whose children
// have no explicit tab fall back to the header key alone.
function groupVisible(group, showTab) {
    if (showTab(group.tab ?? group.key)) return true;
    const anyChild = (nodes) => (nodes || []).some((n) =>
        n.children ? anyChild(n.children) : (n.tab && showTab(n.tab)));
    return anyChild(group.children);
}

function flattenNavItems(showTab, groups, links) {
    const items = links.filter((link) => showTab(link.key)).map((link) => ({
        label: link.label, href: link.href, match: link.match, exact: link.exact, trail: [],
    }));
    const walk = (nodes, trail) => {
        nodes.filter((node) => showTab(node.tab)).forEach((node) => {
            if (node.href && !node.disabled) {
                items.push({ label: node.label, href: node.href, match: node.match, exact: node.exact, external: node.external, trail });
            }
            if (node.children) {
                walk(node.children, [...trail, node.label]);
            }
        });
    };
    // Index groups with at least one accessible tab (role + enabled module — #144);
    // module-declared groups are always indexed (already gated server-side).
    walk(groups.filter((group) => group.moduleGroup || groupVisible(group, showTab)), []);
    items.push({ label: 'Settings', href: '/settings', match: ['/settings'], trail: [] });
    return items;
}

function Sidebar({
    auth, alertCount, csrfToken, appVersion, path, collapsed, mobileOpen, showLabels,
    dark, onToggleCollapsed, onToggleDark, onCloseMobile,
}) {
    const isAdmin = auth?.user?.roles?.includes('Admin');
    const widthClass = collapsed ? 'lg:w-16' : 'lg:w-64';
    const translate = mobileOpen ? 'translate-x-0' : '-translate-x-full';

    // Which entries this user may see. The admin tree hides tabs the role can't
    // reach and modules switched off for this install (#144); the supervisor
    // tree is gated by its routes, so everything in it shows.
    const { links: navLinks, groups: navGroups, showTab } = useNavTree();

    // Menu search: a non-empty query swaps the nav tree for a flat result list.
    // Matches both the English label and its translation so users can search
    // in the active locale.
    const [query, setQuery] = useState('');
    const searchItems = useMemo(() => flattenNavItems(showTab, navGroups, navLinks), [showTab, navGroups, navLinks]);
    const q = query.trim().toLowerCase();
    const results = q
        ? searchItems.filter((item) =>
            [item.label, __(item.label), ...item.trail.flatMap((t) => [t, __(t)])]
                .join(' ')
                .toLowerCase()
                .includes(q))
        : null;

    const clearSearch = () => setQuery('');
    const submitSearch = () => {
        if (results?.length) {
            const first = results[0];
            // Module (legacy Blade) targets need a full load, not an Inertia visit.
            if (first.external) window.location.href = first.href;
            else router.visit(first.href);
            clearSearch();
        }
    };

    return (
        <aside
            className={`fixed inset-y-0 left-0 z-40 flex flex-col shrink-0 bg-om-panel text-om-ink w-64
                        border-r border-om-line
                        lg:relative lg:inset-auto lg:z-auto lg:translate-x-0 overflow-hidden
                        transition-[width,transform] duration-300 ease-in-out ${translate} ${widthClass}`}
        >
            {/* Logo / header — split orange/black brand mark + lowercase wordmark */}
            <div className="flex items-center h-11 px-3 shrink-0 border-b border-om-line">
                <Link href={navRoot(navLinks)?.href} className="flex items-center gap-2 min-w-0 overflow-hidden">
                    {showLabels ? (
                        <>
                            <img src="/logo_open_mes.png" alt="OpenMES" className="h-9 w-auto shrink-0" />
                            {appVersion && (
                                <span className="shrink-0 rounded border border-om-line px-[5px] py-px font-mono text-[9px] text-om-faint">
                                    {appVersion}
                                </span>
                            )}
                        </>
                    ) : (
                        // Collapsed: the square brand mark, not the wordmark cropped
                        // to a square. The full logo is a wide lockup, so clipping it
                        // to 36px showed the gear and half a letter.
                        <img
                            src="/logo_open_mes_mark.png"
                            alt="OpenMES"
                            className="size-9 shrink-0 object-contain"
                        />
                    )}
                </Link>

                {/* Setup wizard (Admin only) — the "?" the demo shows in the header */}
                {showLabels && isAdmin && (
                    <Tooltip label={__('Setup Wizard')} placement="bottom">
                        <Link
                            href="/onboarding/step/1"
                            prefetch
                            aria-label={__('Setup Wizard')}
                            className="ml-auto p-1.5 rounded-full text-om-faint hover:text-om-ink hover:bg-om-chip shrink-0"
                        >
                            <UiIcon name="circle-help" size={20} />
                        </Link>
                    </Tooltip>
                )}
                <button
                    onClick={onCloseMobile}
                    className={`lg:hidden ${showLabels && isAdmin ? '' : 'ml-auto'} p-1.5 rounded-om-sm text-om-faint hover:text-om-ink hover:bg-om-chip shrink-0`}
                >
                    <UiIcon name="x" size={20} />
                </button>
            </div>

            {/* Menu search */}
            <NavSearch
                query={query}
                onChange={setQuery}
                onSubmit={submitSearch}
                collapsed={collapsed}
                showLabels={showLabels}
                onExpand={onToggleCollapsed}
            />

            {/* Navigation */}
            <nav className="sidebar-scroll flex-1 overflow-y-auto overflow-x-hidden pb-3 space-y-0.5">
                {results ? (
                    results.length ? (
                        results.map((item) => (
                            // Group headers can share an href with their first child
                            // (e.g. Orders and All Orders), so href alone isn't unique.
                            <SearchResultLink
                                key={`${item.trail.join('/')}>${item.label}`}
                                item={item}
                                path={path}
                                onNavigate={clearSearch}
                            />
                        ))
                    ) : (
                        <p className="px-5 py-3 text-[13px] text-om-faint">{__('No results')}</p>
                    )
                ) : (
                    <>
                        {navLinks.filter((link) => showTab(link.key)).map((link) => (
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
                        {showLabels && <div className="mx-4 my-2 border-t border-om-line" />}

                        {navGroups
                            .filter((group) => group.moduleGroup || groupVisible(group, showTab))
                            .map((group) => (
                                <NavGroup
                                    key={group.key}
                                    group={group}
                                    path={path}
                                    collapsed={collapsed}
                                    showLabels={showLabels}
                                    showTab={showTab}
                                />
                            ))}
                    </>
                )}
            </nav>

            {/* Footer */}
            <div className="border-t border-om-line shrink-0">
                {/* Dark mode toggle — functional but visually neutral (light-only v1) */}
                <div className="px-2 pt-2">
                    <button
                        onClick={onToggleDark}
                        className={`flex items-center gap-3 w-full px-3 py-2.5 rounded-om-sm text-[13px] font-medium
                                    text-om-muted hover:bg-om-chip hover:text-om-ink transition-colors
                                    ${collapsed && !mobileOpen ? 'justify-center !px-0' : ''}`}
                    >
                        <UiIcon name={dark ? 'sun' : 'moon'} size={20} className="shrink-0" />
                        {showLabels && <span>{dark ? __('Light Mode') : __('Dark Mode')}</span>}
                    </button>
                </div>

                {/* Settings */}
                <div className="px-2 pt-2">
                    <Link
                        href="/settings"
                        prefetch
                        className={`flex items-center gap-3 px-3 py-2.5 rounded-om-sm text-[13px] font-medium transition-colors
                                    ${isActive(path, ['/settings'])
                                        ? 'bg-om-ink text-om-on-ink'
                                        : 'text-om-muted hover:bg-om-chip hover:text-om-ink'}
                                    ${collapsed && !mobileOpen ? 'justify-center !px-0' : ''}`}
                    >
                        <UiIcon name="settings" size={20} className="shrink-0" />
                        {showLabels && <span>{__('Settings')}</span>}
                    </Link>
                </div>

                {/* User + logout */}
                <div className="px-2 py-2">
                    {/* Side by side when there is a name to sit beside; stacked when
                        collapsed, because the rail is 64px wide and an avatar next
                        to a logout button needs more than twice that — they ended up
                        on top of each other. Stacked, each gets its own row like
                        every other control in the collapsed rail. */}
                    <div
                        className={
                            collapsed && !mobileOpen
                                ? 'flex flex-col items-center gap-1 py-2'
                                : 'flex items-center gap-3 px-3 py-2 rounded-lg'
                        }
                    >
                        {/* Avatar + name link to the user's own profile/settings */}
                        <Tooltip label={__('Profile')} placement="right" disabled={showLabels}>
                            <Link
                                href="/settings/profile"
                                prefetch
                                aria-label={__('Profile')}
                                className={`flex items-center gap-3 min-w-0 rounded-om-sm hover:bg-om-chip transition-colors
                                            ${collapsed && !mobileOpen ? '' : 'flex-1 -ml-1 pl-1 pr-2 py-0.5'}`}
                            >
                                <div className="w-8 h-8 rounded-full bg-om-ink flex items-center justify-center shrink-0 text-om-on-ink text-[12px] font-semibold">
                                    {auth?.user?.initial ?? '?'}
                                </div>
                                {showLabels && (
                                    <div className="flex-1 min-w-0 text-left">
                                        <p className="text-[13px] font-medium text-om-ink truncate">{auth?.user?.name}</p>
                                        <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint truncate">{auth?.user?.roles?.[0] ?? 'User'}</p>
                                    </div>
                                )}
                            </Link>
                        </Tooltip>
                        <form action="/logout" method="POST" className="shrink-0">
                            <input type="hidden" name="_token" value={csrfToken} />
                            <Tooltip label={__('Logout')}>
                                <button
                                    type="submit"
                                    aria-label={__('Logout')}
                                    className="p-1.5 rounded-om-sm text-om-faint hover:text-om-blocked hover:bg-om-chip transition-colors"
                                >
                                    <UiIcon name="log-out" size={16} />
                                </button>
                            </Tooltip>
                        </form>
                    </div>
                </div>

            </div>
        </aside>
    );
}

const SEARCH_ICON = 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z';

/**
 * Sidebar menu search input. On a collapsed desktop sidebar it renders as an
 * icon button that expands the sidebar and focuses the input (same pattern as
 * collapsed groups). Escape clears, Enter opens the first result.
 */
function NavSearch({ query, onChange, onSubmit, collapsed, showLabels, onExpand }) {
    const inputRef = useRef(null);
    const focusAfterExpand = useRef(false);

    useEffect(() => {
        if (showLabels && focusAfterExpand.current) {
            focusAfterExpand.current = false;
            inputRef.current?.focus();
        }
    }, [showLabels]);

    if (collapsed && !showLabels) {
        return (
            <div className="px-2 pt-3">
                <Tooltip label={__('Search')} placement="right">
                    <button
                        onClick={() => {
                            focusAfterExpand.current = true;
                            onExpand();
                        }}
                        className="flex items-center justify-center w-full py-2.5 rounded-om-sm text-om-faint hover:text-om-ink hover:bg-om-chip transition-colors"
                    >
                        <UiIcon name="search" size={20} />
                    </button>
                </Tooltip>
            </div>
        );
    }

    return (
        <div className="px-2 pt-3 pb-2">
            <div className="relative">
                <UiIcon
                    name="search"
                    size={16}
                    className="absolute left-3 top-1/2 -translate-y-1/2 text-om-faint pointer-events-none"
                />
                <input
                    ref={inputRef}
                    type="search"
                    value={query}
                    onChange={(e) => onChange(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Escape') onChange('');
                        if (e.key === 'Enter') onSubmit();
                    }}
                    placeholder={__('Search menu…')}
                    aria-label={__('Search menu…')}
                    className="w-full pl-9 pr-3 py-2 rounded-om-sm bg-om-bg border border-om-line text-[13px]
                               text-om-ink placeholder:text-om-faint
                               focus:outline-none focus:border-om-ink focus:ring-1 focus:ring-om-ink"
                />
            </div>
        </div>
    );
}

function SearchResultLink({ item, path, onNavigate }) {
    const active = isActive(path, item.match, item.exact);
    const className = `flex flex-col gap-0.5 px-3 py-2 rounded-om-sm text-[13px] transition-colors
                            ${active ? 'bg-om-ink text-om-on-ink' : 'text-om-muted hover:bg-om-chip hover:text-om-ink'}`;
    const inner = (
        <>
            <span className="font-medium">{__(item.label)}</span>
            {item.trail.length > 0 && (
                <span className={`text-xs ${active ? 'text-white/60' : 'text-om-faint'}`}>
                    {item.trail.map((t) => __(t)).join(' / ')}
                </span>
            )}
        </>
    );
    return (
        <div className="px-2">
            {item.external ? (
                <a href={item.href} onClick={onNavigate} className={className}>{inner}</a>
            ) : (
                <Link href={item.href} prefetch onClick={onNavigate} className={className}>{inner}</Link>
            )}
        </div>
    );
}

function NavLink({ link, path, collapsed, showLabels, alertCount }) {
    const active = isActive(path, link.match, link.exact);
    const activeClass = active ? 'bg-om-ink text-om-on-ink' : 'text-om-muted hover:bg-om-chip hover:text-om-ink';

    // The alerts entry answers its own badge on hover: a count tells you
    // something is wrong but not what, and the list is already in the browser
    // (LatestAlerts reads the same shared collections the badge counts). It
    // replaces the label tooltip rather than joining it — two things opening off
    // one hover fight each other, and the panel names itself.
    const Wrapper = link.alert && alertCount > 0 ? HoverPanel : Tooltip;
    const wrapperProps = link.alert && alertCount > 0
        ? { panel: <LatestAlerts /> }
        : { label: __(link.label), disabled: showLabels };

    return (
        <div className="px-2">
            <Wrapper placement="right" {...wrapperProps}>
                <Link
                    href={link.href}
                    prefetch
                    className={`flex items-center gap-3 px-3 py-2.5 rounded-om-sm text-[13px] font-medium transition-colors
                                ${activeClass} ${collapsed && !showLabels ? 'justify-center !px-0' : ''}`}
                >
                    <span className="relative shrink-0">
                        <UiIcon name={link.lucide ?? ICON_LUCIDE[link.icon] ?? "circle"} size={20} />
                        {alertCount > 0 && (
                            <span className="absolute -top-1.5 -right-1.5 flex items-center justify-center w-4 h-4 rounded-full bg-om-blocked text-white font-mono text-[9px] leading-none">
                                {alertCount > 9 ? '9+' : alertCount}
                            </span>
                        )}
                    </span>
                    {showLabels && (
                        <span className="flex items-center gap-2">
                            {__(link.label)}
                            {link.alert && alertCount > 0 && (
                                <span className="inline-flex items-center justify-center px-[7px] py-px rounded-full bg-om-blocked-bg text-om-blocked font-mono text-[10px]">
                                    {alertCount}
                                </span>
                            )}
                        </span>
                    )}
                </Link>
            </Wrapper>
        </div>
    );
}

function NavGroup({ group, path, collapsed, showLabels, showTab = () => true }) {
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
    // A group with its own landing page (`href`) also navigates there when it is
    // opened — otherwise the header looks unresponsive. Clicking an already-open
    // group only collapses it (never re-navigates), so `href` groups stay
    // collapsible like the rest.
    const toggle = () => {
        if (open) {
            setOpen(false);
            return;
        }
        setOpen(true);
        if (group.href) router.visit(group.href);
    };

    return (
        <div className="px-2">
            <Tooltip label={__(group.label)} placement="right" disabled={showLabels}>
                <button
                    onClick={toggle}
                    className={`flex items-center gap-3 w-full px-3 py-2.5 rounded-om-sm transition-colors
                                text-om-faint hover:bg-om-chip hover:text-om-ink
                                ${collapsed && !showLabels ? 'justify-center !px-0' : ''}
                                ${groupActive && showLabels ? 'text-om-ink' : ''}`}
                >
                    {group.lucide
                        ? <UiIcon name={group.lucide} size={20} className="shrink-0" />
                        : <UiIcon name={ICON_LUCIDE[group.icon] ?? "circle"} size={20} className="shrink-0" />}
                    {showLabels && (
                        <span className="flex-1 text-left font-mono text-[10px] uppercase tracking-[0.12em]">
                            {__(group.label)}
                        </span>
                    )}
                    {showLabels && (
                        <UiIcon name={open ? 'chevron-up' : 'chevron-down'} size={16} className="shrink-0" />
                    )}
                </button>
            </Tooltip>

            {open && showLabels && (
                <div className="mt-0.5 ml-4 space-y-0.5 border-l border-om-line pl-3">
                    {group.children.filter((child) => showTab(child.tab)).map((child) =>
                        child.children ? (
                            <SubGroup key={child.key} group={child} path={path} showTab={showTab} />
                        ) : (
                            <ChildLink key={child.href} child={child} path={path} />
                        ),
                    )}
                </div>
            )}
        </div>
    );
}

function SubGroup({ group, path, showTab = () => true }) {
    const active = isActive(path, group.match);
    const [open, setOpen] = useState(active);
    useEffect(() => {
        if (active) setOpen(true);
    }, [active]);
    return (
        <div>
            <button
                onClick={() => setOpen((o) => !o)}
                className={`flex items-center gap-2 w-full px-2 py-1.5 rounded-om-sm text-[13px] transition-colors
                            ${active ? 'text-om-ink font-medium' : 'text-om-muted hover:bg-om-chip hover:text-om-ink'}`}
            >
                {group.lucide
                    ? <UiIcon name={group.lucide} size={15} className="shrink-0" />
                    : <span className="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60" />}
                {__(group.label)}
                <UiIcon name={open ? 'chevron-up' : 'chevron-down'} size={13} className="ml-auto shrink-0" />
            </button>
            {open && (
                <div className="ml-3 mt-0.5 space-y-0.5 border-l border-om-line2 pl-3">
                    {group.children.filter((child) => showTab(child.tab)).map((child) => (
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
                className="flex items-center gap-2 px-2 py-1.5 rounded-om-sm text-[13px] text-om-faintest cursor-not-allowed select-none"
            >
                {child.lucide
                    ? <UiIcon name={child.lucide} size={15} className="shrink-0" />
                    : <span className={`rounded-full bg-current shrink-0 ${dotClass}`} />}
                {__(child.label)}
                {child.badge && (
                    <span className="ml-auto font-mono text-[10px] bg-om-chip text-om-faint px-1.5 py-0.5 rounded">
                        {__(child.badge)}
                    </span>
                )}
            </span>
        );
    }

    const className = `flex items-center gap-2 px-2 py-1.5 rounded-om-sm text-[13px] transition-colors
                        ${active ? 'bg-om-ink text-om-on-ink font-medium' : 'text-om-muted hover:bg-om-chip hover:text-om-ink'}`;

    // Module (legacy Blade) target — plain anchor for a full page load.
    if (child.external) {
        return (
            <a href={child.href} className={className}>
                {child.lucide
                    ? <UiIcon name={child.lucide} size={15} className="shrink-0" />
                    : <span className={`rounded-full bg-current shrink-0 ${dotClass}`} />}
                {__(child.label)}
            </a>
        );
    }

    return (
        <Link href={child.href} prefetch className={className}>
            {child.lucide
                ? <UiIcon name={child.lucide} size={15} className="shrink-0" />
                : <span className={`rounded-full bg-current shrink-0 ${dotClass}`} />}
            {__(child.label)}
        </Link>
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
