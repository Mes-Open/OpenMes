import { useQuery } from '@tanstack/react-query';

import { getCapacityGrid, type CapacityParams } from '@/api/scheduleCapacity';

/** Schedule capacity grid via REST. Keyed on axis/granularity/anchor date. */
export function useCapacityGrid(params: CapacityParams = {}) {
  return useQuery({
    queryKey: ['schedule-capacity', params.axis ?? 'line', params.granularity ?? 'week', params.start_date ?? null],
    queryFn: () => getCapacityGrid(params),
  });
}
