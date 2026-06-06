import { useEffect, useMemo, useRef } from 'react';
import { useLiveQuery } from '@tanstack/react-db';
import { realtimeCollection } from '../lib/realtimeCollection';

/**
 * Invisible live watcher: subscribes to a collection and calls `onChange` the
 * instant any watched field of any row changes (a row added/removed/edited).
 * Use it to drive a live refresh of a server-rendered page (e.g. the schedule
 * planner) without polling — push instead of a 10s interval.
 *
 * `onChange` is read through a ref so a changing callback identity never
 * re-subscribes or re-fires; the effect fires only on the data signature.
 *
 * Props:
 *   shape     — registered collection name (default work_orders_all)
 *   fields    — row fields whose change should trigger onChange (default covers
 *               schedule-relevant work-order fields incl. updated_at, so any
 *               edit/add/remove is detected)
 *   onChange  — called (no args) on each post-mount change
 */
export default function ShapeChangeWatcher({
    shape = 'work_orders_all',
    fields = ['id', 'status', 'line_id', 'planned_start_at', 'planned_end_at', 'due_date', 'updated_at'],
    onChange,
}) {
    const collection = useMemo(() => realtimeCollection(shape, (r) => r.id), [shape]);
    const { data: rows = [] } = useLiveQuery((q) => q.from({ r: collection }));

    const signature = useMemo(
        () => rows.map((r) => fields.map((f) => String(r[f] ?? '')).join(':')).sort().join('|'),
        [rows, fields],
    );

    const cbRef = useRef(onChange);
    cbRef.current = onChange;
    const first = useRef(true);

    useEffect(() => {
        if (first.current) {
            first.current = false; // skip the initial settle
            return;
        }
        cbRef.current?.();
    }, [signature]);

    return null;
}
