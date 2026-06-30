import { useEffect, useRef } from 'react';
import { useQueryClient, type QueryKey } from '@tanstack/react-query';

import { useElectricShape, type Row } from '@/hooks/useElectricShape';

/**
 * Electric-driven React Query invalidation — the replacement for the old
 * Reverb/Echo realtime hooks. Instead of a WebSocket pushing "something
 * changed" events, we subscribe to an Electric shape and, whenever the
 * relevant rows change, invalidate the given React Query keys so the
 * REST-backed screens (work-order detail, batches, schedule) refetch.
 *
 * This keeps liveness for the screens that are still on REST while removing
 * the Reverb dependency entirely. Server stays the source of truth; no
 * optimistic state.
 *
 * @param shapeName   registered Electric shape that signals the change
 * @param queryKeys   React Query keys to invalidate on change
 * @param options.enabled    gate the subscription
 * @param options.rowMatches narrow to rows that matter (e.g. one work order)
 * @param options.fields     row fields whose change should trigger invalidation
 */
export function useElectricInvalidate(
  shapeName: string,
  queryKeys: QueryKey[],
  options: {
    enabled?: boolean;
    rowMatches?: (row: Row) => boolean;
    fields?: string[];
  } = {},
): void {
  const qc = useQueryClient();
  const { enabled = true, rowMatches, fields = ['id', 'status', 'updated_at'] } = options;

  // Reduce the live row set to a compact signature string; it changes iff a
  // relevant row's watched fields change.
  const { data: signature } = useElectricShape<Row, string>(shapeName, {
    enabled,
    select: (rows) => {
      const relevant = rowMatches ? rows.filter(rowMatches) : rows;
      return relevant
        .map((r) => fields.map((f) => String(r[f] ?? '')).join(':'))
        .sort()
        .join('|');
    },
  });

  const first = useRef(true);
  useEffect(() => {
    if (!enabled) return;
    if (first.current) {
      first.current = false; // skip the initial settle so we don't refetch on mount
      return;
    }
    for (const key of queryKeys) qc.invalidateQueries({ queryKey: key });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [signature, enabled]);
}
