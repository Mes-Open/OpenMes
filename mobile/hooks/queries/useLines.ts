import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { deleteLine, getLine, getLineProductTypes, getLineUsers, listLines, toggleLineActive, type LineFilters } from '@/api/lines';
import { getWorkstation, listWorkstations } from '@/api/workstations';

/**
 * Admin lines list via REST `listLines`.
 * The server applies include_inactive / division_id / q filters and returns the
 * computed counts (workstations_count, work_orders_count, users_count).
 */
export function useAdminLines(filters: LineFilters = {}) {
  return useQuery({
    queryKey: ['admin', 'lines', filters],
    queryFn: () => listLines(filters),
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

// Workstations stay on REST.
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

export function useToggleLineActive() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => toggleLineActive(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['lines'] }),
  });
}

export function useDeleteLine() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteLine(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['lines'] }),
  });
}
