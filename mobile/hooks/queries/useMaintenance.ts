import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import * as m from '@/api/maintenance';
import * as schedules from '@/api/maintenanceSchedules';

const inv = (qc: ReturnType<typeof useQueryClient>, key: string) => qc.invalidateQueries({ queryKey: [key] });

// ── Tools ──────────────────────────────────────────────────────────────────

/**
 * Tools via REST `listTools`.
 * The server applies status / workstation_type_id / q filters and returns the
 * workstation_type relation.
 */
export function useTools(filters: m.ToolFilters = {}) {
  return useQuery({
    queryKey: ['tools', filters],
    queryFn: () => m.listTools(filters),
  });
}

/** REST: detail-by-id — includes the workstation_type relation. */
export function useTool(id: number | undefined) {
  return useQuery({
    queryKey: ['tool', id],
    queryFn: () => m.getTool(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

// ── Tool mutations (always REST) ───────────────────────────────────────────

export function useCreateTool() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: m.createTool, onSuccess: () => inv(qc, 'tools') });
}
export function useUpdateTool() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; payload: Partial<m.CreateToolPayload> }) => m.updateTool(vars.id, vars.payload),
    onSuccess: () => inv(qc, 'tools'),
  });
}
export function useDeleteTool() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: m.deleteTool, onSuccess: () => inv(qc, 'tools') });
}
export function useTransitionToolStatus() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; status: m.ToolStatus }) => m.transitionToolStatus(vars.id, vars.status),
    onSuccess: () => inv(qc, 'tools'),
  });
}

// ── Maintenance events ─────────────────────────────────────────────────────

/**
 * Maintenance events via REST `listMaintenanceEvents`.
 * The server applies status / tool_id / line_id / from / to filters and returns
 * the `{ data, meta }` envelope (screens read `query.data?.data` /
 * `query.data?.meta?.total`). Detail-only fields (started_at, completed_at,
 * description, resolution_notes) and relations come from `useMaintenanceEvent(id)`.
 */
export function useMaintenanceEvents(filters: m.MaintenanceEventFilters = {}) {
  return useQuery({
    queryKey: ['maintenance-events', filters],
    queryFn: () => m.listMaintenanceEvents(filters),
  });
}

/** REST: detail-by-id — includes tool/line/workstation relations and
 *  started_at, completed_at, description, resolution_notes columns. */
export function useMaintenanceEvent(id: number | undefined) {
  return useQuery({
    queryKey: ['maintenance-event', id],
    queryFn: () => m.getMaintenanceEvent(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

// ── Maintenance event mutations (always REST) ──────────────────────────────

export function useCreateMaintenanceEvent() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: m.createMaintenanceEvent, onSuccess: () => inv(qc, 'maintenance-events') });
}
export function useUpdateMaintenanceEvent() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; payload: Partial<m.CreateMaintenanceEventPayload> & { resolution_notes?: string } }) =>
      m.updateMaintenanceEvent(vars.id, vars.payload),
    onSuccess: () => inv(qc, 'maintenance-events'),
  });
}
export function useDeleteMaintenanceEvent() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: m.deleteMaintenanceEvent, onSuccess: () => inv(qc, 'maintenance-events') });
}
export function useStartMaintenanceEvent() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: m.startMaintenanceEvent,
    onSuccess: (_d, id) => {
      inv(qc, 'maintenance-events');
      qc.invalidateQueries({ queryKey: ['maintenance-event', id] });
    },
  });
}
export function useCompleteMaintenanceEvent() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; resolution_notes?: string; actual_cost?: number; currency?: string }) =>
      m.completeMaintenanceEvent(vars.id, vars),
    onSuccess: (_d, vars) => {
      inv(qc, 'maintenance-events');
      qc.invalidateQueries({ queryKey: ['maintenance-event', vars.id] });
    },
  });
}
export function useCancelMaintenanceEvent() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: m.cancelMaintenanceEvent,
    onSuccess: (_d, id) => {
      inv(qc, 'maintenance-events');
      qc.invalidateQueries({ queryKey: ['maintenance-event', id] });
    },
  });
}

// ── Maintenance schedules ──────────────────────────────────────────────────

/**
 * Maintenance schedules via REST `listMaintenanceSchedules`.
 * The server applies is_active / frequency / search filters and returns the
 * description, last_executed_at, and relation fields.
 */
export function useMaintenanceSchedules(opts: schedules.MaintenanceScheduleFilters = {}) {
  return useQuery({
    queryKey: ['maintenance-schedules', opts],
    queryFn: () => schedules.listMaintenanceSchedules(opts),
  });
}

/** REST: detail-by-id — includes description, last_executed_at, and all
 *  relation fields not in the list response (use the detail endpoint). */
export function useMaintenanceSchedule(id: number | undefined) {
  return useQuery({
    queryKey: ['maintenance-schedule', id],
    queryFn: () => schedules.getMaintenanceSchedule(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

// ── Maintenance schedule mutations (always REST) ───────────────────────────

const invSchedules = (qc: ReturnType<typeof useQueryClient>) =>
  qc.invalidateQueries({ queryKey: ['maintenance-schedules'] });

export function useCreateMaintenanceSchedule() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: schedules.createMaintenanceSchedule,
    onSuccess: () => invSchedules(qc),
  });
}

export function useUpdateMaintenanceSchedule() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, input }: { id: number; input: schedules.MaintenanceScheduleInput }) =>
      schedules.updateMaintenanceSchedule(id, input),
    onSuccess: (_, vars) => {
      invSchedules(qc);
      qc.invalidateQueries({ queryKey: ['maintenance-schedule', vars.id] });
    },
  });
}

export function useDeleteMaintenanceSchedule() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: schedules.deleteMaintenanceSchedule,
    onSuccess: () => invSchedules(qc),
  });
}

export function useGenerateScheduleNow() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: schedules.generateNow,
    onSuccess: () => {
      invSchedules(qc);
      // Also refresh the maintenance events list since a new event was made.
      qc.invalidateQueries({ queryKey: ['maintenance-events'] });
    },
  });
}
