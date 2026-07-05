import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  listQualityTasks,
  performQualityTask,
  raiseRoamingTask,
  skipQualityTask,
  type PerformQualityTaskPayload,
  type RaiseRoamingPayload,
} from '@/api/qualityTasks';

export function useQualityTasks() {
  return useQuery({
    queryKey: ['quality-tasks'],
    queryFn: listQualityTasks,
    refetchInterval: 30_000,
  });
}

export function useSkipQualityTask() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: skipQualityTask,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['quality-tasks'] }),
  });
}

export function usePerformQualityTask() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: PerformQualityTaskPayload }) =>
      performQualityTask(id, payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['quality-tasks'] }),
  });
}

export function useRaiseRoamingTask() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: RaiseRoamingPayload) => raiseRoamingTask(payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['quality-tasks'] }),
  });
}
