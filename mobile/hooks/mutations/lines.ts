import { useMutation, useQueryClient } from '@tanstack/react-query';

import {
  createLine,
  deleteLine,
  syncLineProductTypes,
  syncLineUsers,
  toggleLineActive,
  updateLine,
  type CreateLinePayload,
  type UpdateLinePayload,
} from '@/api/lines';
import {
  createWorkstation,
  deleteWorkstation,
  toggleWorkstationActive,
  updateWorkstation,
  type CreateWorkstationPayload,
} from '@/api/workstations';

const invalidateLines = (qc: ReturnType<typeof useQueryClient>) => {
  qc.invalidateQueries({ queryKey: ['admin', 'lines'] });
  qc.invalidateQueries({ queryKey: ['lines'] });
};

export function useCreateLine() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateLinePayload) => createLine(payload),
    onSuccess: () => invalidateLines(qc),
  });
}

export function useUpdateLine() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; payload: UpdateLinePayload }) => updateLine(vars.id, vars.payload),
    onSuccess: (_d, vars) => {
      invalidateLines(qc);
      qc.invalidateQueries({ queryKey: ['line', vars.id] });
    },
  });
}

export function useDeleteLine() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteLine(id),
    onSuccess: () => invalidateLines(qc),
  });
}

export function useToggleLineActive() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => toggleLineActive(id),
    onSuccess: (_d, id) => {
      invalidateLines(qc);
      qc.invalidateQueries({ queryKey: ['line', id] });
    },
  });
}

export function useSyncLineUsers() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; user_ids: number[] }) => syncLineUsers(vars.id, vars.user_ids),
    onSuccess: (_d, vars) => qc.invalidateQueries({ queryKey: ['line', vars.id, 'users'] }),
  });
}

export function useSyncLineProductTypes() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; product_type_ids: number[] }) =>
      syncLineProductTypes(vars.id, vars.product_type_ids),
    onSuccess: (_d, vars) => qc.invalidateQueries({ queryKey: ['line', vars.id, 'product-types'] }),
  });
}

export function useCreateWorkstation() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { lineId: number; payload: CreateWorkstationPayload }) =>
      createWorkstation(vars.lineId, vars.payload),
    onSuccess: (_d, vars) => qc.invalidateQueries({ queryKey: ['line', vars.lineId, 'workstations'] }),
  });
}

export function useUpdateWorkstation() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; payload: Partial<CreateWorkstationPayload> }) =>
      updateWorkstation(vars.id, vars.payload),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['workstation'] });
      qc.invalidateQueries({ queryKey: ['line'] });
    },
  });
}

export function useDeleteWorkstation() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteWorkstation(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['line'] }),
  });
}

export function useToggleWorkstationActive() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => toggleWorkstationActive(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['line'] }),
  });
}
