import { useEffect, useMemo, useRef, useState } from 'react';
import { FetchError, Shape, ShapeStream, type ExternalParamsRecord } from '@electric-sql/client';

import { fetchShapeConfig } from '@/lib/electric';

/**
 * Subscribe to an Electric shape and expose it with a React-Query-compatible
 * result shape, so existing screens that read `.data` / `.isLoading` / `.error`
 * keep working unchanged when their query hook is swapped from REST to Electric.
 *
 * Liveness: the Shape pushes a fresh row set on every change. NO optimistic
 * state — rows reflect what the server has committed and synced.
 *
 * Auth/expiry: the gatekeeper signs the shape config with a ~1h TTL. When the
 * signature expires the stream gets a 403; we transparently re-mint the config
 * and hand Electric fresh params (the stream resumes without a full reload).
 */
export interface ElectricShapeResult<T> {
  data: T[];
  isLoading: boolean;
  isPending: boolean;
  isError: boolean;
  isSuccess: boolean;
  /** React-Query parity: true while (re)connecting the stream. */
  isFetching: boolean;
  isRefetching: boolean;
  error: Error | null;
  /** Force a fresh config + stream restart. */
  refetch: () => void;
}

export interface UseElectricShapeOptions<T, R = T[]> {
  /** When false, the hook stays idle and subscribes to nothing. */
  enabled?: boolean;
  /** Client-side transform (filter/sort/map) applied to the live row set. */
  select?: (rows: T[]) => R;
}

export function useElectricShape<T extends Row = Row, R = T[]>(
  name: string,
  options: UseElectricShapeOptions<T, R> = {},
): Omit<ElectricShapeResult<T>, 'data'> & { data: R } {
  const { enabled = true } = options;
  // Keep `select` stable across renders without forcing callers to memoize it.
  const selectRef = useRef(options.select);
  selectRef.current = options.select;

  const [rows, setRows] = useState<T[]>([]);
  const [status, setStatus] = useState<'idle' | 'loading' | 'success' | 'error'>('idle');
  const [error, setError] = useState<Error | null>(null);
  const [nonce, setNonce] = useState(0);

  useEffect(() => {
    if (!enabled) {
      setStatus('idle');
      return;
    }

    const controller = new AbortController();
    let shape: Shape<T> | null = null;
    let unsubscribe: (() => void) | null = null;
    let cancelled = false;

    setStatus('loading');
    setError(null);

    (async () => {
      try {
        const config = await fetchShapeConfig(name, controller.signal);
        if (cancelled) return;

        const stream = new ShapeStream<T>({
          url: config.url,
          // Gatekeeper params are { table, columns, where, exp, sig } — none of
          // Electric's reserved protocol keys, so this cast is safe.
          params: config.params as ExternalParamsRecord,
          signal: controller.signal,
          // Re-mint a fresh signed config when the gatekeeper signature expires.
          onError: async (err) => {
            if (err instanceof FetchError && err.status === 403) {
              try {
                const fresh = await fetchShapeConfig(name, controller.signal);
                return { params: fresh.params as ExternalParamsRecord };
              } catch (e) {
                if (!cancelled) setError(e as Error);
                return; // stop the stream
              }
            }
            if (!cancelled) {
              setError(err as Error);
              setStatus('error');
            }
            return; // stop the stream on unexpected errors
          },
        });

        shape = new Shape<T>(stream);
        unsubscribe = shape.subscribe(({ rows: next }) => {
          if (cancelled) return;
          setRows([...next]);
          setStatus('success');
        });
      } catch (e) {
        if (!cancelled && (e as Error)?.name !== 'AbortError') {
          setError(e as Error);
          setStatus('error');
        }
      }
    })();

    return () => {
      cancelled = true;
      unsubscribe?.();
      controller.abort();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [name, enabled, nonce]);

  const data = useMemo(() => {
    const sel = selectRef.current;
    return (sel ? sel(rows) : rows) as R;
  }, [rows]);

  return {
    data,
    isLoading: status === 'loading' || status === 'idle',
    isPending: status === 'loading' || status === 'idle',
    isError: status === 'error',
    isSuccess: status === 'success',
    isFetching: status === 'loading',
    isRefetching: false,
    error,
    refetch: () => setNonce((n) => n + 1),
  };
}

/** Minimal row constraint — Electric rows are plain JSON objects. */
export type Row = Record<string, unknown>;
