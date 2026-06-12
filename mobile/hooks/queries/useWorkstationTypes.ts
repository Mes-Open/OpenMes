import { useQuery } from '@tanstack/react-query';

import {
  getWorkstationType,
  type WorkstationType,
  type WorkstationTypeFilters,
} from '@/api/workstationTypes';
import { useElectricShape, type Row } from '@/hooks/useElectricShape';

/**
 * Live workstation types via Electric (migrated from REST `listWorkstationTypes`).
 * Filtering applied client-side:
 *   - include_inactive → when false (default), hides rows where is_active is false
 *   - q               → case-insensitive substring match on name and code
 * Fidelity note: `workstation_types` shape rows do not include
 * `workstations_count` (a REST-computed join count). That field is optional
 * on the type; pickers that only need name/code/is_active are unaffected.
 */
export function useWorkstationTypes(filters: WorkstationTypeFilters = {}) {
  const includeInactive = filters.include_inactive ?? false;
  const q = filters.q?.trim().toLowerCase() ?? '';

  return useElectricShape<Row, WorkstationType[]>('workstation_types', {
    select: (rows) => {
      let out = rows as unknown as WorkstationType[];

      if (!includeInactive) {
        out = out.filter((r) => r.is_active !== false);
      }

      if (q) {
        out = out.filter(
          (r) =>
            r.name.toLowerCase().includes(q) ||
            (r.code ?? '').toLowerCase().includes(q),
        );
      }

      return [...out].sort((a, b) => a.name.localeCompare(b.name));
    },
  });
}

export function useWorkstationType(id: number | undefined) {
  return useQuery({
    queryKey: ['workstation-type', id],
    queryFn: () => getWorkstationType(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}
