import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import * as schedule from '@/api/schedule';

/**
 * Mutations for the planner write API. All of them invalidate the planner board
 * (the weekly grid's source of truth), the system schedule query and the
 * work-orders list, so every surface showing the WO picks up the new plan.
 */
function invalidateAll(qc: ReturnType<typeof useQueryClient>) {
  qc.invalidateQueries({ queryKey: ['planner-board'] });
  qc.invalidateQueries({ queryKey: ['schedule-changes'] });
  qc.invalidateQueries({ queryKey: ['system-schedule'] });
  qc.invalidateQueries({ queryKey: ['work-orders'] });
}

/**
 * The weekly planner board. `keepPreviousData` holds the current grid on screen
 * while a week/line change loads, so navigating doesn't flash a skeleton.
 */
export function usePlannerBoard(params: schedule.PlannerBoardParams) {
  return useQuery({
    queryKey: ['planner-board', params.view_mode ?? 'weekly', params.start_date ?? null, params.line_id ?? null],
    queryFn: () => schedule.fetchPlannerBoard(params),
    placeholderData: keepPreviousData,
  });
}

export function useScheduleChanges(enabled = true) {
  return useQuery({
    queryKey: ['schedule-changes'],
    queryFn: schedule.fetchScheduleChanges,
    enabled,
  });
}

export function useUndoScheduleChange() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (changeId: number) => schedule.undoScheduleChange(changeId),
    onSuccess: () => invalidateAll(qc),
  });
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
