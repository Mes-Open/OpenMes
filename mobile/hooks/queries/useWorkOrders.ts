import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  createWorkOrder,
  deleteWorkOrder,
  getWorkOrder,
  listWorkOrders,
  transitionWorkOrder,
  updateWorkOrder,
  type CreateWorkOrderPayload,
  type WorkOrderFilters,
  type WorkOrderTransition,
} from '@/api/workOrders';

/**
 * Work orders via REST (React Query). The API
 * applies filters (status, line_id, week_number) server-side, so callers get
 * exactly the rows they ask for. `refetchInterval` polls for liveness.
 */
export function useWorkOrders(
  filters: WorkOrderFilters = {},
  options: { refetchInterval?: number } = {},
) {
  return useQuery({
    queryKey: ['work-orders', filters],
    queryFn: () => listWorkOrders(filters),
    refetchInterval: options.refetchInterval,
  });
}

export function useWorkOrder(id: number | undefined) {
  return useQuery({
    queryKey: ['work-order', id],
    queryFn: () => getWorkOrder(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

export function useCreateWorkOrder() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: createWorkOrder,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['work-orders'] }),
  });
}

export function useUpdateWorkOrder() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; payload: Partial<CreateWorkOrderPayload> }) =>
      updateWorkOrder(vars.id, vars.payload),
    onSuccess: (_d, vars) => {
      qc.invalidateQueries({ queryKey: ['work-orders'] });
      qc.invalidateQueries({ queryKey: ['work-order', vars.id] });
    },
  });
}

export function useTransitionWorkOrder() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: {
      id: number;
      transition: WorkOrderTransition;
      data?: Record<string, unknown>;
    }) => transitionWorkOrder(vars.id, vars.transition, vars.data),
    onSuccess: (_d, vars) => {
      qc.invalidateQueries({ queryKey: ['work-orders'] });
      qc.invalidateQueries({ queryKey: ['work-order', vars.id] });
    },
  });
}

export function useDeleteWorkOrder() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteWorkOrder(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['work-orders'] }),
  });
}
