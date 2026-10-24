import NeedsAction from './NeedsAction';

/**
 * Plant-overview section registry — the sections drawn between the "Plant
 * overview" header and the "Last 30 days" widgets on the admin dashboard.
 *
 * Adding a section is ONE import and ONE entry here; nothing in
 * Dashboard.jsx or OverviewGrid.jsx changes:
 *
 *   import LineActivity from './LineActivity';
 *
 *   export const SECTIONS = [
 *       { id: 'line_activity', row: 'activity', span: 'full', Component: LineActivity },
 *   ];
 *
 * Entry fields:
 *   id        — unique, stable key (also the React key).
 *   row       — which mockup row it sits in, one of ROWS. Sections sharing a row
 *               render side by side and wrap to one column on narrow screens.
 *   span      — 'full' (whole row) | 'wide' (2 parts) | 'narrow' (1 part).
 *   Component — receives { overview, accessibleTabs } where `overview` is the
 *               `overview` Inertia prop ({ shift, lines }, refreshed every 60 s).
 *               Wrap it in `OverviewCard` (./OverviewGrid) for the shared card
 *               style, and render its own empty state — never fake zeros or NaN.
 *   visible   — optional (ctx) => boolean, ctx = { overview, accessibleTabs,
 *               enabledModules }. Return false to hide the section, e.g. when its
 *               module is switched off or the user can't open the linked tab.
 *
 * Need more data than `overview` carries? Add a key to the payload built by
 * App\Services\Dashboard\PlantOverviewService::overview() (keep its query count
 * independent of the number of lines) rather than a new Inertia prop, so the
 * 60 s partial reload refreshes it too.
 *
 * Charts are hand-drawn SVG (see components/shift-monitor/*). Do NOT use
 * viewBox="0 0 100 60": that is OeeGauge's, and the oee-smoke e2e spec finds the
 * gauge on this page by it.
 */

/** Row order, top to bottom, as in the "Plant overview" mockup. */
export const ROWS = [
    'activity', //       Line activity
    'oee_downtime', //   OEE + Downtime
    'production_kpi', // Production planned vs actual + KPI tiles
    'needs_action', //   Needs action
    'output', //         Output per hour
    'lines', //          Lines
    'summary', //        Summary bar
];

export const SECTIONS = [
    { id: 'needs_action', row: 'needs_action', span: 'full', Component: NeedsAction },
];
