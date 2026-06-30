import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  createDivision,
  createFactory,
  createLineStatus,
  deleteDivision,
  deleteFactory,
  deleteLineStatus,
  getDivision,
  getFactory,
  listLineStatuses,
  reorderLineStatuses,
  toggleDivisionActive,
  toggleFactoryActive,
  updateDivision,
  updateFactory,
  updateLineStatus,
  type Division,
  type Factory,
} from '@/api/orgStructure';
import { useElectricShape, type Row } from '@/hooks/useElectricShape';

const inv = (qc: ReturnType<typeof useQueryClient>, key: string) => qc.invalidateQueries({ queryKey: [key] });

// ── Factories ────────────────────────────────────────────────────────────────

/**
 * Live factories list via Electric (migrated from REST `listFactories`).
 * Uses `factories` shape (all factories).
 * `include_inactive` filter applied client-side.
 *
 * Shape fidelity note: `factories` shape carries raw table columns only —
 * the REST-computed `divisions_count` is not present in the shape rows.
 */
export function useFactories(includeInactive = false) {
  return useElectricShape<Row, Factory[]>('factories', {
    select: (rows) => {
      let out = rows as unknown as Factory[];
      if (!includeInactive) out = out.filter((r) => r.is_active !== false);
      return [...out].sort((a, b) => a.name.localeCompare(b.name));
    },
  });
}

// Detail-by-id — stays on REST.
export function useFactory(id: number | undefined) {
  return useQuery({
    queryKey: ['factory', id],
    queryFn: () => getFactory(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

export function useCreateFactory() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: createFactory, onSuccess: () => inv(qc, 'factories') });
}
export function useUpdateFactory() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; payload: Partial<{ code: string; name: string; description: string; is_active: boolean }> }) =>
      updateFactory(vars.id, vars.payload),
    onSuccess: () => inv(qc, 'factories'),
  });
}
export function useDeleteFactory() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: deleteFactory, onSuccess: () => inv(qc, 'factories') });
}
export function useToggleFactoryActive() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: toggleFactoryActive, onSuccess: () => inv(qc, 'factories') });
}

// ── Divisions ────────────────────────────────────────────────────────────────

/**
 * Live divisions for a factory via Electric (migrated from REST
 * `listFactoryDivisions`). Uses `divisions` shape (all divisions, all
 * factories) and filters by `factory_id` + `include_inactive` client-side.
 *
 * Shape fidelity note: `divisions` shape carries raw table columns only —
 * the REST-computed `lines_count` is not present in the shape rows.
 */
export function useFactoryDivisions(factoryId: number | undefined, includeInactive = false) {
  return useElectricShape<Row, Division[]>('divisions', {
    enabled: typeof factoryId === 'number' && Number.isFinite(factoryId),
    select: (rows) => {
      let out = (rows as unknown as Division[]).filter(
        (r) => Number(r.factory_id) === (factoryId as number),
      );
      if (!includeInactive) out = out.filter((r) => r.is_active !== false);
      return [...out].sort((a, b) => a.name.localeCompare(b.name));
    },
  });
}

// Detail-by-id — stays on REST.
export function useDivision(id: number | undefined) {
  return useQuery({
    queryKey: ['division', id],
    queryFn: () => getDivision(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

export function useCreateDivision() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { factoryId: number; payload: { code: string; name: string; description?: string; is_active?: boolean } }) =>
      createDivision(vars.factoryId, vars.payload),
    onSuccess: (_d, vars) => qc.invalidateQueries({ queryKey: ['factory', vars.factoryId, 'divisions'] }),
  });
}
export function useUpdateDivision() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; payload: Partial<{ code: string; name: string; description: string; is_active: boolean }> }) =>
      updateDivision(vars.id, vars.payload),
    onSuccess: () => inv(qc, 'factory'),
  });
}
export function useDeleteDivision() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: deleteDivision, onSuccess: () => inv(qc, 'factory') });
}
export function useToggleDivisionActive() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: toggleDivisionActive, onSuccess: () => inv(qc, 'factory') });
}

// ── Line Statuses ─────────────────────────────────────────────────────────────
// `line_statuses_global` shape only contains rows where line_id IS NULL
// (global/template statuses). Per-line statuses must remain on REST.

export function useLineStatuses(lineId: number | undefined) {
  return useQuery({
    queryKey: ['line', lineId, 'statuses'],
    queryFn: () => listLineStatuses(lineId as number),
    enabled: typeof lineId === 'number' && Number.isFinite(lineId),
  });
}
export function useCreateLineStatus() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { lineId: number; payload: { name: string; color?: string; is_default?: boolean; is_done_status?: boolean } }) =>
      createLineStatus(vars.lineId, vars.payload),
    onSuccess: (_d, vars) => qc.invalidateQueries({ queryKey: ['line', vars.lineId, 'statuses'] }),
  });
}
export function useUpdateLineStatus() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; payload: Partial<{ name: string; color: string; is_default: boolean; is_done_status: boolean; sort_order: number }> }) =>
      updateLineStatus(vars.id, vars.payload),
    onSuccess: () => inv(qc, 'line'),
  });
}
export function useDeleteLineStatus() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: deleteLineStatus, onSuccess: () => inv(qc, 'line') });
}
export function useReorderLineStatuses() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { lineId: number; statusIds: number[] }) => reorderLineStatuses(vars.lineId, vars.statusIds),
    onSuccess: () => inv(qc, 'line'),
  });
}
