import { Fragment } from 'react';

import { Icon } from '../Icon';

/**
 * Breadcrumbs — the page's position in the app, rendered as a trail.
 *
 * Sits in the app header's title slot in place of a bare heading, so a list
 * page says both what it is and where it lives without costing a content row.
 *
 *   items    — [{ label, href?, icon? }]. `icon` is a Lucide name rendered
 *              before the label. The last entry is the current page and is
 *              never a link, whatever it carries.
 *   linkAs   — component used for entries with an `href`. Defaults to `a`,
 *              which does a full page load; apps using a router pass their own
 *              link (Inertia's `Link`) so the trail navigates client-side.
 *              `packages/ui` stays router-agnostic this way.
 *
 * The last item is the accessible current page (`aria-current="page"`), and the
 * separators are decorative — a screen reader reads the trail, not the slashes.
 */
export function Breadcrumbs({ items = [], linkAs: LinkComponent = 'a', className = '', ...props }) {
    const trail = items.filter(Boolean);

    if (trail.length === 0) {
        return null;
    }

    return (
        <nav aria-label="Breadcrumb" className={`flex min-w-0 items-center gap-2 text-[13px] ${className}`} {...props}>
            {trail.map((item, i) => {
                const isLast = i === trail.length - 1;

                return (
                    <Fragment key={item.key ?? `${item.label}-${i}`}>
                        {i > 0 && (
                            <span aria-hidden="true" className="shrink-0 text-om-faintest">
                                /
                            </span>
                        )}
                        {item.href && !isLast ? (
                            <LinkComponent
                                href={item.href}
                                className="flex shrink-0 items-center gap-1.5 text-om-muted transition-colors hover:text-om-ink"
                            >
                                {item.icon && <Icon name={item.icon} size={14} className="shrink-0" />}
                                <span className="hover:underline">{item.label}</span>
                            </LinkComponent>
                        ) : (
                            <span
                                aria-current={isLast ? 'page' : undefined}
                                className={`flex items-center gap-1.5 ${isLast ? 'min-w-0 font-semibold text-om-ink' : 'shrink-0 text-om-muted'}`}
                            >
                                {item.icon && <Icon name={item.icon} size={14} className="shrink-0" />}
                                <span className="truncate">{item.label}</span>
                            </span>
                        )}
                    </Fragment>
                );
            })}
        </nav>
    );
}

export default Breadcrumbs;
