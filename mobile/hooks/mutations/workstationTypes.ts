import { useMutation, useQueryClient } from '@tanstack/react-query';

import {
  createWorkstationType,
  deleteWorkstationType,
  toggleWorkstationTypeActive,
  updateWorkstationType,
  type CreateWorkstationTypePayload,
} from '@/api/workstationTypes';

const invalidate = (qc: ReturnType<typeof useQueryClient>) => {
  qc.invalidateQueries({ queryKey: ['workstation-types'] });
};

export function useCreateWorkstationType() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateWorkstationTypePayload) => createWorkstationType(payload),
    onSuccess: () => invalidate(qc),
  });
}

export function useUpdateWorkstationType() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; payload: Partial<CreateWorkstationTypePayload> }) =>
      updateWorkstationType(vars.id, vars.payload),
    onSuccess: (_d, vars) => {
      invalidate(qc);
      qc.invalidateQueries({ queryKey: ['workstation-type', vars.id] });
    },
  });
}

export function useDeleteWorkstationType() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteWorkstationType(id),
    onSuccess: () => invalidate(qc),
  });
}

export function useToggleWorkstationTypeActive() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => toggleWorkstationTypeActive(id),
    onSuccess: () => invalidate(qc),
  });
}
