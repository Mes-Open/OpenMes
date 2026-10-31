import { usePage } from '@inertiajs/react';
import { ROWS, SECTIONS } from './sections';

// How much of a row a section takes. flex-basis wraps everything to a single
// column below ~840 px, so tablets in portrait and phones get one section a row.
const SPAN_CLASS = {
    full: 'w-full min-w-0',
    wide: 'flex-[2_1_520px] min-w-0',
    narrow: 'flex-[1_1_300px] min-w-0',
};

/**
 * Lays out the registered plant-overview sections (./sections.js) in the
 * mockup's row order. Renders nothing while no section is registered or
 * visible — no placeholder boxes.
 */
export default function OverviewGrid({ overview, sections = SECTIONS }) {
    const { auth, enabledModules = [] } = usePage().props;
    const accessibleTabs = auth?.user?.accessibleTabs ?? [];
    const ctx = { overview, accessibleTabs, enabledModules };

    const rows = ROWS.map((row) => ({
        row,
        items: sections.filter((s) => s.row === row && (s.visible?.(ctx) ?? true)),
    })).filter((r) => r.items.length > 0);

    if (rows.length === 0) return null;

    return (
        <div className="flex flex-col gap-8">
            {rows.map(({ row, items }) => (
                <div key={row} className="flex flex-wrap items-stretch gap-4">
                    {items.map(({ id, span = 'full', Component }) => (
                        <div key={id} className={SPAN_CLASS[span] ?? SPAN_CLASS.full}>
                            <Component overview={overview} accessibleTabs={accessibleTabs} />
                        </div>
                    ))}
                </div>
            ))}
        </div>
    );
}
