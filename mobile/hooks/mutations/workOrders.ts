import { useMutation, useQueryClient } from '@tanstack/react-query';

import { transitionWorkOrder, type WorkOrderTransition } from '@/api/workOrders';

export function useTransitionWorkOrder() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; transition: WorkOrderTransition }) =>
      transitionWorkOrder(vars.id, vars.transition),
    onSuccess: (_d, vars) => {
      qc.invalidateQueries({ queryKey: ['work-order', vars.id] });
      qc.invalidateQueries({ queryKey: ['work-orders'] });
    },
  });
}
