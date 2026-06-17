// React Query hooks for the tachograph-style employee activity API.

import {
  keepPreviousData,
  useMutation,
  useQuery,
  useQueryClient,
} from '@tanstack/react-query';

import {
  createActivity,
  createCustomType,
  deleteActivity,
  deleteCustomType,
  fetchActivityTypes,
  fetchDayPlan,
  fetchMonthPlan,
  fetchTeamDay,
  listActivities,
  showActivity,
  updateActivity,
  updateCustomType,
  type CreateActivityInput,
  type ListActivitiesParams,
  type UpdateActivityInput,
} from '@/api/employeeActivities';

// Reusable invalidator — touches every tachograph cache key.
function invalidateAll(qc: ReturnType<typeof useQueryClient>) {
  qc.invalidateQueries({ queryKey: ['employee-activities'] });
  qc.invalidateQueries({ queryKey: ['employee-day-plan'] });
  qc.invalidateQueries({ queryKey: ['employee-month-plan'] });
  qc.invalidateQueries({ queryKey: ['employee-team-day'] });
}

// ── Queries ──────────────────────────────────────────────────────────────────

export function useEmployeeActivities(params: ListActivitiesParams = {}) {
  return useQuery({
    queryKey: ['employee-activities', params],
    queryFn: () => listActivities(params),
  });
}

export function useEmployeeActivity(id: number | undefined) {
  return useQuery({
    queryKey: ['employee-activity', id],
    queryFn: () => showActivity(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

export function useEmployeeDayPlan(workerId: number | undefined, date?: string) {
  return useQuery({
    queryKey: ['employee-day-plan', workerId, date],
    queryFn: () => fetchDayPlan(workerId as number, date),
    enabled: typeof workerId === 'number' && Number.isFinite(workerId),
    // Keep the prior worker/date data visible while the new one loads —
    // avoids the skeleton flash on every chip click. `isPlaceholderData`
    // tells the screen when it's looking at stale-but-acceptable data
    // so it can dim or show a subtle indicator instead.
    placeholderData: keepPreviousData,
  });
}

export function useEmployeeMonthPlan(workerId: number | undefined, month?: string) {
  return useQuery({
    queryKey: ['employee-month-plan', workerId, month],
    queryFn: () => fetchMonthPlan(workerId as number, month),
    enabled: typeof workerId === 'number' && Number.isFinite(workerId),
    placeholderData: keepPreviousData,
  });
}

export function useTeamDay(date?: string) {
  return useQuery({
    queryKey: ['employee-team-day', date],
    queryFn: () => fetchTeamDay(date),
    placeholderData: keepPreviousData,
  });
}

export function useActivityTypes() {
  return useQuery({
    queryKey: ['employee-activity-types'],
    // Types rarely change at runtime — cache aggressively.
    queryFn: fetchActivityTypes,
    staleTime: 60 * 60 * 1000,
  });
}

// ── Mutations ────────────────────────────────────────────────────────────────

export function useCreateActivity() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: CreateActivityInput) => createActivity(input),
    onSuccess: () => invalidateAll(qc),
  });
}

export function useUpdateActivity() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, input }: { id: number; input: UpdateActivityInput }) =>
      updateActivity(id, input),
    onSuccess: () => invalidateAll(qc),
  });
}

export function useDeleteActivity() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteActivity(id),
    onSuccess: () => invalidateAll(qc),
  });
}

export function useCreateCustomType() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: Parameters<typeof createCustomType>[0]) => createCustomType(input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['employee-activity-types'] }),
  });
}

export function useUpdateCustomType() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, input }: { id: number; input: Parameters<typeof updateCustomType>[1] }) =>
      updateCustomType(id, input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['employee-activity-types'] }),
  });
}

export function useDeleteCustomType() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteCustomType(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['employee-activity-types'] }),
  });
}
