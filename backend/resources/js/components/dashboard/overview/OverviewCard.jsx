/**
 * Shared card frame for plant-overview sections: title row + body.
 *
 * Its own module on purpose: sections import it, and OverviewGrid imports
 * every section through ./sections.js — taking the card from OverviewGrid
 * would make each section import the whole registry (a cycle, and every
 * section's tests loading every other section).
 */
export default function OverviewCard({ title, action, children, className = '' }) {
    return (
        <section className={`h-full bg-om-card border border-om-line rounded-[14px] px-6 py-5 flex flex-col gap-3.5 ${className}`}>
            {(title || action) && (
                <div className="flex items-center justify-between gap-3">
                    {title && <h2 className="text-[18px] font-semibold text-om-ink">{title}</h2>}
                    {action}
                </div>
            )}
            {children}
        </section>
    );
}
