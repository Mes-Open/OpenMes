import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { useToast } from '@openmes/ui';
import { __ } from '../../../lib/i18n';
import { apiCall } from '../../../lib/http';
import { formatAge, visibleItems, NEEDS_ACTION_COLLAPSED } from '../../../lib/needsAction';

const DOT = {
    critical: 'bg-om-blocked',
    warning: 'bg-om-downtime',
};

const BUTTON_CLASS =
    'inline-flex h-9 shrink-0 items-center whitespace-nowrap rounded-[8px] border border-om-line bg-om-card px-3.5 text-[14px] font-medium text-om-ink no-underline transition-colors hover:bg-om-chip disabled:cursor-wait disabled:opacity-60';

/**
 * "Needs action · N" — what is wrong on the floor right now, from
 * `overview.needs_action` (PlantOverviewService::needsAction). Critical rows
 * first, then oldest first; the server sorts and decides each row's button.
 * Six rows, then "Show all" expands in place.
 *
 * A button is either a link to an existing page or the shift monitor's
 * existing stop escalation. The server sends no button the user may not use.
 */
export default function NeedsAction({ overview, accessibleTabs = [] }) {
    const data = overview?.needs_action;
    const [expanded, setExpanded] = useState(false);

    if (!data) return null;

    const items = data.items ?? [];
    const total = data.total ?? items.length;
    const shown = visibleItems(items, expanded);
    const now = Date.now();

    return (
        <section aria-labelledby="needs-action-h" className="flex flex-col gap-3" data-testid="needs-action">
            <div className="flex items-baseline justify-between gap-3">
                <h2 id="needs-action-h" className="m-0 text-[18px] font-semibold text-om-ink">
                    {__('Needs action')}
                    {total > 0 && <span className="text-om-blocked"> · {total}</span>}
                </h2>
                {accessibleTabs.includes('alerts') && (
                    <Link href="/admin/alerts" className="text-[14px] text-om-accent hover:underline">
                        {__('All alerts')} →
                    </Link>
                )}
            </div>
            <div className="overflow-hidden rounded-[14px] border border-om-line bg-om-card">
                {items.length === 0 ? (
                    <p className="m-0 px-5 py-6 text-center text-[14px] text-om-muted">
                        {__('Nothing needs action right now')}
                    </p>
                ) : (
                    <ul className="m-0 list-none p-0">
                        {shown.map((item, i) => (
                            <Row key={item.key} item={item} now={now} first={i === 0} />
                        ))}
                    </ul>
                )}
                {items.length > NEEDS_ACTION_COLLAPSED && (
                    <div className="flex flex-wrap items-center justify-between gap-2 border-t border-om-line2 px-5 py-3 text-[13px] text-om-muted">
                        <button
                            type="button"
                            onClick={() => setExpanded((v) => !v)}
                            aria-expanded={expanded}
                            className="font-medium text-om-accent hover:underline"
                        >
                            {expanded ? __('Show less') : __('Show all (:n)', { n: items.length })}
                        </button>
                        {expanded && total > items.length && (
                            <span>{__('Showing :shown of :total', { shown: items.length, total })}</span>
                        )}
                    </div>
                )}
            </div>
        </section>
    );
}

function Row({ item, now, first }) {
    const age = formatAge(item.since, now);

    return (
        <li
            className={`flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-4 ${first ? '' : 'border-t border-om-line2'}`}
            data-testid="needs-action-item"
            data-type={item.type}
            data-severity={item.severity}
        >
            <span aria-hidden="true" className={`h-2.5 w-2.5 flex-none rounded-full ${DOT[item.severity] ?? DOT.warning}`} />
            <span className="sr-only">{item.severity === 'critical' ? __('Critical') : __('Warning')}</span>
            <div className="flex min-w-0 flex-[1_1_260px] flex-col">
                <span className="text-[15px] font-semibold text-om-ink">{item.title}</span>
                {item.description && <span className="text-[14px] text-om-muted">{item.description}</span>}
            </div>
            <div className="ml-auto flex items-center gap-4">
                {age && (
                    <span className="min-w-16 text-right font-mono text-[13px] text-om-muted" title={__('How long')}>
                        {age}
                    </span>
                )}
                <Action action={item.action} />
            </div>
        </li>
    );
}

function Action({ action }) {
    if (!action) return null;
    if (action.kind === 'escalate') return <EscalateButton action={action} />;

    return (
        <Link href={action.href} className={BUTTON_CLASS}>
            {action.label}
        </Link>
    );
}

/**
 * The shift monitor's own escalation endpoint — raises the maintenance issue
 * against the stop. The page stays put, so the outcome is a toast, and the
 * overview reloads so the row turns into "View ticket".
 */
function EscalateButton({ action }) {
    const toast = useToast();
    const [busy, setBusy] = useState(false);

    const escalate = async () => {
        setBusy(true);
        try {
            const res = await apiCall(action.href, 'POST', { note: null });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) {
                toast({ severity: 'error', title: json.message || __('Something went wrong.') });
                return;
            }
            toast({ severity: 'success', title: json.message || __('Saved') });
            router.reload({ only: ['overview'] });
        } catch {
            toast({ severity: 'error', title: __('Connection error') });
        } finally {
            setBusy(false);
        }
    };

    return (
        <button type="button" onClick={escalate} disabled={busy} aria-busy={busy} className={BUTTON_CLASS}>
            {action.label}
        </button>
    );
}
