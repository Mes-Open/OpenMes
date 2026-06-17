import { useMutation, useQueryClient } from '@tanstack/react-query';

import * as schedule from '@/api/schedule';

/**
 * Mutations for the planner write API. Both invalidate the system schedule
 * query (the planner's source of truth) and the work-orders list so any
 * other surface showing the WO picks up the new plan.
 */
function invalidateAll(qc: ReturnType<typeof useQueryClient>) {
  qc.invalidateQueries({ queryKey: ['system-schedule'] });
  qc.invalidateQueries({ queryKey: ['work-orders'] });
}

export function useUpdateScheduleOrder() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, input }: { id: number; input: schedule.ScheduleUpdateInput }) =>
      schedule.updateScheduleOrder(id, input),
    onSuccess: () => invalidateAll(qc),
  });
}

export function useResizeScheduleOrder() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, input }: { id: number; input: schedule.ScheduleResizeInput }) =>
      schedule.resizeScheduleOrder(id, input),
    onSuccess: () => invalidateAll(qc),
  });
}
