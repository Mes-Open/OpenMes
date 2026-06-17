import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import * as logs from '@/api/systemLogs';

/**
 * App-log tail. Polls every 5s while the screen is mounted so the admin
 * can watch live errors. Filters (level, search, date) bust the cache.
 */
export function useLogTail(filters: Parameters<typeof logs.tailLogs>[0] = {}) {
  return useQuery({
    queryKey: ['system-logs', 'tail', filters],
    queryFn: () => logs.tailLogs(filters),
    refetchInterval: 5_000,
  });
}

export function useFailedJobs(filters: Parameters<typeof logs.listFailedJobs>[0] = {}) {
  return useQuery({
    queryKey: ['system-logs', 'failed-jobs', filters],
    queryFn: () => logs.listFailedJobs(filters),
  });
}

export function useRetryFailedJob() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: logs.retryFailedJob,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['system-logs', 'failed-jobs'] }),
  });
}

export function useDeployments(filters: Parameters<typeof logs.listDeployments>[0] = {}) {
  return useQuery({
    queryKey: ['system-logs', 'deployments', filters],
    queryFn: () => logs.listDeployments(filters),
  });
}
