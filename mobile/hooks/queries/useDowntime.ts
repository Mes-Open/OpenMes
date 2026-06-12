import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  listDowntimeReasons,
  listDowntimes,
  startDowntime,
  stopDowntime,
  type DowntimeFilters,
  type StartDowntimeInput,
} from '@/api/downtime';

export function useDowntimeReasons() {
  return useQuery({
    queryKey: ['downtime-reasons'],
    queryFn: listDowntimeReasons,
    staleTime: 5 * 60_000,
  });
}

export function useDowntimes(filters: DowntimeFilters = {}) {
  return useQuery({
    queryKey: ['downtimes', filters],
    queryFn: () => listDowntimes(filters),
    refetchInterval: 30_000,
  });
}

/** Returns the most recent active downtime for a given line, or null. */
export function useActiveDowntime(lineId: number | undefined) {
  return useQuery({
    queryKey: ['downtimes', 'active', lineId],
    queryFn: async () => {
      const all = await listDowntimes({ line_id: lineId });
      return all.find((d) => d.ended_at == null) ?? null;
    },
    enabled: lineId != null && lineId > 0,
    refetchInterval: 15_000,
  });
}

export function useStartDowntime() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: StartDowntimeInput) => startDowntime(input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['downtimes'] }),
  });
}

export function useStopDowntime() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => stopDowntime(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['downtimes'] });
      qc.invalidateQueries({ queryKey: ['oee'] });
    },
  });
}
