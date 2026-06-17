// URL-driven state for the employee-schedule routes. Mirrors the design's
// notion of "the URL is the source of truth for what the user is looking at":
// switching workers, dates, view modes all update query params so the back
// button works and the URL is shareable.

import { useLocalSearchParams, useRouter } from 'expo-router';
import { useCallback, useMemo } from 'react';
import { format, parseISO, startOfDay, startOfMonth } from 'date-fns';

export type ViewMode = 'day' | 'week' | 'month';

interface UrlState {
  view: ViewMode;
  /** Selected worker id (single-worker views). null when no param. */
  workerId: number | null;
  /** Selected day (day + team views). */
  date: Date;
  /** Anchor month (first day, month view only). */
  monthAnchor: Date;
  /** Selected day inside the month grid (defaults to `date`). */
  selectedDate: Date;
}

interface Setters {
  setView: (m: ViewMode) => void;
  setWorkerId: (id: number | null) => void;
  setDate: (d: Date) => void;
  setMonthAnchor: (d: Date) => void;
  setSelectedDate: (d: Date) => void;
}

export function useEmployeeScheduleUrlState(): UrlState & Setters {
  const router = useRouter();
  const raw = useLocalSearchParams<{
    view?: string;
    worker_id?: string;
    date?: string;
    month?: string;
    selected_date?: string;
  }>();

  const today = startOfDay(new Date());

  const view: ViewMode = useMemo(() => {
    const v = raw.view ?? 'day';
    return v === 'week' || v === 'month' ? v : 'day';
  }, [raw.view]);

  const workerId: number | null = useMemo(() => {
    if (!raw.worker_id) return null;
    const n = Number(raw.worker_id);
    return Number.isFinite(n) ? n : null;
  }, [raw.worker_id]);

  const date: Date = useMemo(() => {
    if (!raw.date) return today;
    try {
      return startOfDay(parseISO(raw.date));
    } catch {
      return today;
    }
  }, [raw.date, today.getTime()]);

  const monthAnchor: Date = useMemo(() => {
    if (!raw.month) return startOfMonth(today);
    try {
      return startOfMonth(parseISO(`${raw.month}-01`));
    } catch {
      return startOfMonth(today);
    }
  }, [raw.month, today.getTime()]);

  const selectedDate: Date = useMemo(() => {
    if (!raw.selected_date) return date;
    try {
      return startOfDay(parseISO(raw.selected_date));
    } catch {
      return date;
    }
  }, [raw.selected_date, date.getTime()]);

  const patch = useCallback(
    (next: Record<string, string | undefined>) => {
      // Spread current params so we keep `view` / `worker_id` etc. across
      // updates. setParams shallow-merges so undefined removes a key.
      router.setParams({ ...raw, ...next });
    },
    [router, raw],
  );

  return {
    view,
    workerId,
    date,
    monthAnchor,
    selectedDate,
    setView: (m) => patch({ view: m }),
    setWorkerId: (id) => patch({ worker_id: id == null ? undefined : String(id) }),
    setDate: (d) => patch({ date: format(d, 'yyyy-MM-dd') }),
    setMonthAnchor: (d) => patch({ month: format(d, 'yyyy-MM') }),
    setSelectedDate: (d) => patch({ selected_date: format(d, 'yyyy-MM-dd') }),
  };
}
