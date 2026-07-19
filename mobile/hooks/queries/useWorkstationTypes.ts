import { useQuery } from '@tanstack/react-query';

import {
  getWorkstationType,
  listWorkstationTypes,
  type WorkstationType,
  type WorkstationTypeFilters,
} from '@/api/workstationTypes';

/**
 * Workstation types via REST `listWorkstationTypes`.
 * `include_inactive` and `q` filters are pushed to the backend.
 * Results are sorted by name client-side to preserve prior ordering.
 */
export function useWorkstationTypes(filters: WorkstationTypeFilters = {}) {
  return useQuery({
    queryKey: ['workstation-types', filters],
    queryFn: () => listWorkstationTypes(filters),
    select: (rows) => [...rows].sort((a, b) => a.name.localeCompare(b.name)),
  });
}

export function useWorkstationType(id: number | undefined) {
  return useQuery({
    queryKey: ['workstation-type', id],
    queryFn: () => getWorkstationType(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}
