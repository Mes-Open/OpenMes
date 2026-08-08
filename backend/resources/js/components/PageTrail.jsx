import { useContext } from 'react';
import { Link } from '@inertiajs/react';
import { Breadcrumbs } from '@openmes/ui';

import PageTitle from './PageTitle';
import { dedupeTrail, PageTitleContext } from '../layouts/AppLayout';

/**
 * A page's breadcrumb trail in the app header.
 *
 * Ancestors come from the sidebar nav, so a page only says the part the nav
 * can't know — which record you opened:
 *
 *     <PageTrail append={material.name} />   → Panel / Production / Materials / Steel
 *
 * That is the whole reason this exists. The hand-written trails it replaces each
 * restated "Dashboard / Materials", and every one of them was a separate place
 * to forget when a section moved or got renamed.
 *
 * `items` overrides the lot for a page whose trail the nav genuinely can't
 * describe. `append` takes a string or a list of crumbs.
 */
export default function PageTrail({ append, items }) {
    const { navTrail = [] } = useContext(PageTitleContext);

    const extra = append == null ? [] : Array.isArray(append) ? append : [{ label: append }];
    const trail = items ?? dedupeTrail([...navTrail, ...extra]);

    if (trail.length === 0) return null;

    return (
        <PageTitle>
            <Breadcrumbs linkAs={Link} items={trail} />
        </PageTitle>
    );
}
