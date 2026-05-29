import { useEffect, useMemo, useRef } from 'react';
import { router } from '@inertiajs/react';
import { useLiveQuery } from '@tanstack/react-db';
import { useShapeConfigs } from '../lib/useShapeConfigs';
import { electricCollection } from '../lib/electricCollection';

/**
 * Live-refresh trigger for operator screens (non-optimistic, server-authoritative).
 *
 * Operator pages render deep relational data (batches, steps, quality checks)
 * that is impractical to reproduce as client-side joins, so the page keeps using
 * server-computed Inertia props. This component subscribes to the
 * `work_orders_active` Electric shape and, whenever the active work orders for
 * the selected line change (another operator/supervisor writes, a batch
 * completes, etc.), asks Inertia to reload the given props. Data is always
 * re-fetched from the server — no optimistic state — so what the operator sees
 * is always correct.
 *
 * Render it once near the top of an operator page:
 *   <LineSync lineId={line.id} reloadOnly={['activeWorkOrders']} />
 *
 * Props:
 *   lineId      — currently selected line id
 *   reloadOnly  — Inertia prop keys to partial-reload (empty = full reload)
 */
export default function LineSync({ lineId, reloadOnly = [] }) {
    const { configs } = useShapeConfigs(['work_orders_active']);
    if (!configs) return null;
    return <LineSyncLive configs={configs} lineId={lineId} reloadOnly={reloadOnly} />;
}

function LineSyncLive({ configs, lineId, reloadOnly }) {
    const collection = useMemo(
        () => electricCollection('work_orders_active', configs['work_orders_active'], (r) => r.id),
        [configs],
    );

    const { data: rows = [] } = useLiveQuery((q) => q.from({ r: collection }));

    // A signal that changes whenever a relevant row's sync-visible state changes.
    // Electric serialises ids/line_id as strings — compare with String().
    const signal = useMemo(
        () =>
            rows
                .filter((r) => String(r.line_id) === String(lineId))
                .map((r) => `${r.id}:${r.status}:${r.produced_qty}:${r.line_status_id ?? ''}:${r.updated_at ?? ''}`)
                .sort()
                .join('|'),
        [rows, lineId],
    );

    const first = useRef(true);
    useEffect(() => {
        if (first.current) {
            first.current = false; // skip the initial settle so we don't double-load
            return;
        }
        // preserveState/preserveScroll keep the page's React state (open modals,
        // in-progress form input, scroll position) intact across a live refresh —
        // otherwise the periodic reload would close an operator's open issue /
        // downtime modal and lose their typing.
        router.reload({
            preserveState: true,
            preserveScroll: true,
            ...(reloadOnly.length ? { only: reloadOnly } : {}),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [signal]);

    return null;
}
