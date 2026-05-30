import { useEffect, useMemo, useRef } from 'react';
import { useLiveQuery } from '@tanstack/react-db';
import { useShapeConfigs } from '../lib/useShapeConfigs';
import { electricCollection } from '../lib/electricCollection';

/**
 * Invisible Electric watcher: subscribes to a shape and calls `onChange` the
 * instant any watched field of any row changes (a row added/removed/edited).
 * Use it to drive a live refresh of a server-rendered page (e.g. the schedule
 * planner) without polling — push instead of a 10s interval.
 *
 * `onChange` is read through a ref so a changing callback identity never
 * re-subscribes or re-fires; the effect fires only on the data signature.
 *
 * Props:
 *   shape     — registered Electric shape name (default work_orders_all)
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
    const { configs } = useShapeConfigs([shape]);
    if (!configs) return null;
    return <Watch configs={configs} shape={shape} fields={fields} onChange={onChange} />;
}

function Watch({ configs, shape, fields, onChange }) {
    const collection = useMemo(
        () => electricCollection(shape, configs[shape], (r) => r.id),
        [configs, shape],
    );
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
