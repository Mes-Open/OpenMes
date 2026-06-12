import { useQuery } from '@tanstack/react-query';

import { getOeeForLine, listOee, type OeeFilters } from '@/api/oee';

export function useOee(filters: OeeFilters = {}) {
  return useQuery({
    queryKey: ['oee', filters],
    queryFn: () => listOee(filters),
    refetchInterval: 60_000,
  });
}

export function useOeeForLine(lineId: number | undefined, days = 7) {
  return useQuery({
    queryKey: ['oee', 'line', lineId, days],
    queryFn: () => getOeeForLine(lineId as number, days),
    enabled: lineId != null && lineId > 0,
    refetchInterval: 60_000,
  });
}
