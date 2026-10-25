import { Link } from '@inertiajs/react';
import { __ } from '../../../lib/i18n';
import { nextOrderLabel } from './lineCardFormat';

// Column count by how many fields are shown, so a hidden module never leaves
// an empty, line-coloured slot in the strip.
const COLS = {
    1: '',
    2: 'sm:grid-cols-2',
    3: 'sm:grid-cols-3',
    4: 'sm:grid-cols-2 lg:grid-cols-4',
};

/**
 * Summary strip at the bottom of the plant overview (`overview.summary`):
 * work orders today, materials below minimum, open non-conformances and the
 * next order. A field whose module is off arrives as null and is left out; a
 * field links to its list only when the user can open that tab.
 */
export default function SummaryBar({ overview, accessibleTabs = [] }) {
    const summary = overview?.summary;
    if (!summary) return null;

    const can = (tab) => accessibleTabs.includes(tab);
    const { work_orders: wo, materials, non_conformances: nc, next_order: next } = summary;

    const fields = [
        wo && {
            id: 'work_orders',
            label: __('Work orders today'),
            value: __(':running running · :pending pending', { running: wo.running, pending: wo.pending }),
            href: can('orders') ? '/admin/work-orders' : null,
        },
        materials && {
            id: 'materials',
            label: __('Materials'),
            value: __(':count below minimum', { count: materials.below_min }),
            href: can('materials') ? '/admin/materials' : null,
        },
        nc && {
            id: 'non_conformances',
            label: __('Open non-conformances'),
            value: __(':count open', { count: nc.open }),
            href: can('quality') ? '/admin/issues' : null,
        },
        {
            id: 'next_order',
            label: __('Next order'),
            value: next ? nextOrderLabel(next) : __('No upcoming orders'),
            href: next && can('orders') ? `/admin/work-orders/${next.id}` : null,
        },
    ].filter(Boolean);

    return (
        <section
            aria-label={__('Plant summary')}
            className={`grid grid-cols-1 ${COLS[fields.length] ?? COLS[4]} gap-px overflow-hidden rounded-[14px] border border-om-line bg-om-line`}
        >
            {fields.map(({ id, label, value, href }) => {
                const inner = (
                    <>
                        <span className="text-[13px] text-om-muted">{label}</span>
                        <span className="text-[15px] font-semibold text-om-ink">{value}</span>
                    </>
                );
                const className = 'flex flex-col gap-0.5 bg-om-card px-5 py-4 no-underline';

                return href ? (
                    <Link key={id} href={href} className={`${className} transition-colors hover:bg-om-panel`} data-testid={`summary-${id}`}>
                        {inner}
                    </Link>
                ) : (
                    <div key={id} className={className} data-testid={`summary-${id}`}>
                        {inner}
                    </div>
                );
            })}
        </section>
    );
}
