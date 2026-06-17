import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import * as m from '@/api/maintenance';
import * as schedules from '@/api/maintenanceSchedules';
import type { ApiPaginated } from '@/types/api';
import { useElectricShape, type Row } from '@/hooks/useElectricShape';

type EventsEnvelope = { data: m.MaintenanceEvent[]; meta?: ApiPaginated<m.MaintenanceEvent>['meta'] };

const inv = (qc: ReturnType<typeof useQueryClient>, key: string) => qc.invalidateQueries({ queryKey: [key] });

// ── Tools ──────────────────────────────────────────────────────────────────

/**
 * Live tools via Electric (migrated from REST `listTools`).
 * Filters are applied client-side over the synced row set.
 *
 * Fidelity note: the `tools` shape carries raw table columns only — the
 * relation field `workstation_type` is NOT present; use `useTool(id)` (REST)
 * when you need the nested object.
 */
export function useTools(filters: m.ToolFilters = {}) {
  const { status, workstation_type_id, q } = filters;
  const search = q?.trim().toLowerCase() ?? '';

  return useElectricShape<Row, m.Tool[]>('tools', {
    select: (rows) => {
      let out = rows as unknown as m.Tool[];

      if (status !== undefined) {
        out = out.filter((r) => r.status === status);
      }
      if (workstation_type_id !== undefined) {
        out = out.filter((r) => r.workstation_type_id === workstation_type_id);
      }
      if (search) {
        out = out.filter(
          (r) =>
            r.name.toLowerCase().includes(search) ||
            r.code.toLowerCase().includes(search),
        );
      }

      return [...out].sort((a, b) => a.name.localeCompare(b.name));
    },
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
 * Live maintenance events via Electric (migrated from REST `listMaintenanceEvents`).
 * Filters are applied client-side over the synced row set.
 *
 * Fidelity note: the `maintenance_events` shape does NOT include `started_at`,
 * `completed_at`, `description`, or `resolution_notes` columns. Consumers
 * that need those fields must use `useMaintenanceEvent(id)` (REST). The shape
 * adds `scheduled_end_at` which is not in the REST MaintenanceEvent type.
 * `_count` absent (no relation counts in this shape).
 */
export function useMaintenanceEvents(filters: m.MaintenanceEventFilters = {}) {
  const { status, tool_id, line_id, from: dateFrom, to: dateTo } = filters;

  // Preserve the REST `{ data, meta }` envelope so screens that read
  // `query.data?.data` / `query.data?.meta?.total` are unchanged (meta
  // undefined — Electric streams the full set rather than server pages).
  return useElectricShape<Row, EventsEnvelope>('maintenance_events', {
    select: (rows): EventsEnvelope => {
      let out = rows as unknown as m.MaintenanceEvent[];

      if (status !== undefined) {
        out = out.filter((r) => r.status === status);
      }
      if (tool_id !== undefined) {
        out = out.filter((r) => r.tool_id === tool_id);
      }
      if (line_id !== undefined) {
        out = out.filter((r) => r.line_id === line_id);
      }
      if (dateFrom !== undefined) {
        const fromMs = new Date(dateFrom).getTime();
        out = out.filter(
          (r) => r.scheduled_at != null && new Date(r.scheduled_at as string).getTime() >= fromMs,
        );
      }
      if (dateTo !== undefined) {
        const toMs = new Date(dateTo).getTime();
        out = out.filter(
          (r) => r.scheduled_at != null && new Date(r.scheduled_at as string).getTime() <= toMs,
        );
      }

      // Sort: most recently scheduled first (mirrors default server ordering).
      const sorted = [...out].sort((a, b) => {
        const aMs = a.scheduled_at ? new Date(a.scheduled_at as string).getTime() : 0;
        const bMs = b.scheduled_at ? new Date(b.scheduled_at as string).getTime() : 0;
        return bMs - aMs;
      });
      return { data: sorted, meta: undefined };
    },
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
 * Live maintenance schedules via Electric (migrated from REST
 * `listMaintenanceSchedules`). Filters are applied client-side.
 *
 * Fidelity note: the `maintenance_schedules` shape does NOT include
 * `description` or `last_executed_at` columns. The shape also omits the
 * relation fields (tool, line, workstation, assignedTo, costSource).
 * `_count` absent (no relation counts in this shape).
 */
export function useMaintenanceSchedules(opts: schedules.MaintenanceScheduleFilters = {}) {
  const { is_active, frequency, search } = opts;
  const q = search?.trim().toLowerCase() ?? '';

  return useElectricShape<Row, schedules.MaintenanceSchedule[]>('maintenance_schedules', {
    select: (rows) => {
      let out = rows as unknown as schedules.MaintenanceSchedule[];

      if (is_active !== undefined) {
        out = out.filter((r) => r.is_active === is_active);
      }
      if (frequency !== undefined) {
        out = out.filter((r) => r.frequency === frequency);
      }
      if (q) {
        out = out.filter((r) => r.name.toLowerCase().includes(q));
      }

      return [...out].sort((a, b) => a.name.localeCompare(b.name));
    },
  });
}

/** REST: detail-by-id — includes description, last_executed_at, and all
 *  relation fields not present in the Electric shape. */
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
