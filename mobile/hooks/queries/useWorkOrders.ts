import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  createWorkOrder,
  getWorkOrder,
  updateWorkOrder,
  type CreateWorkOrderPayload,
  type WorkOrderFilters,
} from '@/api/workOrders';
import { useElectricShape, type Row } from '@/hooks/useElectricShape';
import type { WorkOrder } from '@/types/api';

/**
 * Live work orders via Electric (migrated from REST `listWorkOrders`).
 * Uses `work_orders_all` so every status value is available for client-side
 * filtering. Filters applied client-side:
 *   - status  → string equality / array membership
 *   - line_id → equality
 * Fidelity note: `week_number` (a REST-only computed filter) cannot be
 * applied client-side — rows are not filtered by week when that param is
 * passed. Callers that need strict week filtering should continue to use
 * the REST hook directly.
 * The `refetchInterval` option is intentionally ignored (Electric streams
 * live updates continuously).
 */
export function useWorkOrders(
  filters: WorkOrderFilters = {},
  _options: { refetchInterval?: number } = {},
) {
  const { status, line_id } = filters;

  return useElectricShape<Row, WorkOrder[]>('work_orders_all', {
    select: (rows) => {
      let out = rows as unknown as WorkOrder[];

      if (status !== undefined) {
        const statuses = Array.isArray(status) ? status : [status];
        out = out.filter((r) => statuses.includes(r.status));
      }

      if (line_id !== undefined) {
        out = out.filter((r) => Number(r.line_id) === line_id);
      }

      // Preserve the REST default order: most-recently-updated first.
      return [...out].sort((a, b) => {
        const ta = a.updated_at ?? a.created_at ?? '';
        const tb = b.updated_at ?? b.created_at ?? '';
        return tb.localeCompare(ta);
      });
    },
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
