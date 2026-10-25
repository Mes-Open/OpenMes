import { Link } from '@inertiajs/react';
import { __, formatNumber } from '../../../lib/i18n';

// Caption colour by the tone PlantOverviewService/ShiftKpiService decided.
const TONE_CLASS = {
    ok: 'text-om-running',
    warn: 'text-om-downtime',
    bad: 'text-om-blocked',
    mute: 'text-om-muted',
};

const DASH = '—';

const pct = (value, digits = 1) => `${formatNumber(value, { minimumFractionDigits: digits, maximumFractionDigits: digits })}%`;
const target = (value, minDigits = 0) => formatNumber(value, { minimumFractionDigits: minDigits, maximumFractionDigits: 1 });

/** "3 due this week · 1 overdue" — zero parts left out. */
function backlogTile(b) {
    const parts = [
        b.due_this_week > 0 && __(':count due this week', { count: b.due_this_week }),
        b.overdue > 0 && __(':count overdue', { count: b.overdue }),
    ].filter(Boolean);

    return {
        id: 'backlog',
        label: __('Orders not scheduled'),
        value: formatNumber(b.count),
        note: b.count === 0 ? __('All orders scheduled') : parts.join(' · ') || __('No due dates set'),
        tone: b.tone,
        href: b.href,
    };
}

/** "2 / 5" with "1 down · 1 blocked · 1 idle" — zero parts left out. */
function linesTile(l) {
    const parts = [
        l.down > 0 && __(':count down', { count: l.down }),
        l.blocked > 0 && __(':count blocked', { count: l.blocked }),
        l.idle > 0 && __(':count idle', { count: l.idle }),
    ].filter(Boolean);

    return {
        id: 'lines',
        label: __('Lines producing'),
        value: l.total > 0 ? `${formatNumber(l.running)} / ${formatNumber(l.total)}` : DASH,
        note: l.total === 0 ? __('No active lines') : parts.join(' · ') || __('All lines running'),
        tone: l.tone,
        href: l.href,
    };
}

function scrapTile(s) {
    return {
        id: 'scrap',
        label: __('Scrap rate'),
        value: s.rate_pct == null ? DASH : pct(s.rate_pct),
        note: s.rate_pct == null
            ? __('No completed output this shift')
            : __('Target ≤ :pct%', { pct: target(s.target_max_pct, 1) }),
        tone: s.tone,
        href: s.href,
    };
}

function fpyTile(f) {
    return {
        id: 'fpy',
        label: __('First-pass yield'),
        value: f.pct == null ? DASH : pct(f.pct),
        note: f.pct == null
            ? __('No quality checks this shift')
            : __('Target ≥ :pct%', { pct: target(f.target_min_pct) }),
        tone: f.tone,
        href: f.href,
    };
}

function Tile({ id, label, value, note, tone, href }) {
    const body = (
        <>
            <span className="text-[14px] text-om-muted">{label}</span>
            <span className="font-mono text-[30px] font-semibold leading-[1.15] tracking-[-0.03em] text-om-ink">{value}</span>
            <span className={`text-[13px] font-medium ${TONE_CLASS[tone] ?? TONE_CLASS.mute}`}>{note}</span>
        </>
    );
    const className = 'min-h-[96px] bg-om-card border border-om-line rounded-[14px] px-5 py-3.5 flex flex-col justify-center gap-0.5 text-om-ink no-underline';

    return href ? (
        <Link href={href} data-testid={`kpi-${id}`} className={`${className} transition-colors hover:border-om-faintest`}>
            {body}
        </Link>
    ) : (
        <div data-testid={`kpi-${id}`} className={className}>{body}</div>
    );
}

/**
 * "Shift at a glance" — four stacked tiles beside the production chart:
 * orders not scheduled, lines producing, scrap rate and first-pass yield, from
 * `overview.kpis`. A tile links to its screen only when the server resolved an
 * `href` (the user can open that tab and its module is on).
 */
export default function KpiTiles({ overview }) {
    const kpis = overview?.kpis;
    if (!kpis) return null;

    const tiles = [backlogTile(kpis.backlog), linesTile(kpis.lines), scrapTile(kpis.scrap), fpyTile(kpis.fpy)];

    return (
        // Container query, not viewport: beside the production chart the column
        // is ~300–460 px and the tiles stack as in the mockup; given a whole row
        // (narrow screens, or no chart next to it) they go two or four across.
        <section aria-label={__('Shift at a glance')} className="@container h-full">
            <div className="grid h-full auto-rows-fr grid-cols-1 gap-3 @xl:grid-cols-2 @4xl:grid-cols-4">
                {tiles.map((tile) => <Tile key={tile.id} {...tile} />)}
            </div>
        </section>
    );
}
