import { useQuery } from '@tanstack/react-query';

import { getLine, getLineProductTypes, getLineUsers, type LineFilters } from '@/api/lines';
import { getWorkstation, listWorkstations } from '@/api/workstations';
import { useElectricShape, type Row } from '@/hooks/useElectricShape';
import type { Line } from '@/types/api';

/**
 * Live lines list via Electric (migrated from REST `listLines`).
 * Uses `lines_all` shape (all lines including inactive).
 * Filters (include_inactive, division_id, q) are applied client-side.
 *
 * Shape fidelity note: `lines_all` carries raw table columns only — the REST
 * computed counts (workstations_count, work_orders_count, users_count) are not
 * present in the shape rows.
 */
export function useAdminLines(filters: LineFilters = {}) {
  const includeInactive = filters.include_inactive ?? false;
  const divisionId = filters.division_id;
  const q = filters.q?.trim().toLowerCase() ?? '';

  return useElectricShape<Row, Line[]>('lines_all', {
    select: (rows) => {
      let out = rows as unknown as Line[];
      if (!includeInactive) out = out.filter((r) => r.is_active !== false);
      if (divisionId !== undefined) out = out.filter((r) => r.division_id === divisionId);
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

// ── REST (detail / relational / no shape) ───────────────────────────────────

export function useLineDetail(id: number | undefined) {
  return useQuery({
    queryKey: ['line', id],
    queryFn: () => getLine(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

export function useLineUsers(id: number | undefined) {
  return useQuery({
    queryKey: ['line', id, 'users'],
    queryFn: () => getLineUsers(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

export function useLineProductTypes(id: number | undefined) {
  return useQuery({
    queryKey: ['line', id, 'product-types'],
    queryFn: () => getLineProductTypes(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

// No `workstations` Electric shape — stays on REST.
export function useWorkstations(lineId: number | undefined, includeInactive = false) {
  return useQuery({
    queryKey: ['line', lineId, 'workstations', includeInactive],
    queryFn: () => listWorkstations(lineId as number, includeInactive),
    enabled: typeof lineId === 'number' && Number.isFinite(lineId),
  });
}

export function useWorkstation(id: number | undefined) {
  return useQuery({
    queryKey: ['workstation', id],
    queryFn: () => getWorkstation(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}
