// Maintenance schedules — recurring templates that auto-generate
// MaintenanceEvents via the backend GenerateMaintenanceEvents service.
// API: /api/v1/maintenance-schedules (admin/supervisor for writes).

import type { ApiEnvelope, ApiPaginated } from '@/types/api';
import { api } from './client';

export type ScheduleFrequency =
  | 'daily'
  | 'weekly'
  | 'monthly'
  | 'quarterly'
  | 'annually'
  | 'by_hours';

export type MaintenanceEventType = 'planned' | 'corrective' | 'inspection';

export interface MaintenanceSchedule {
  id: number;
  name: string;
  description?: string | null;
  tool_id?: number | null;
  line_id?: number | null;
  workstation_id?: number | null;
  event_type: MaintenanceEventType | string;
  assigned_to_id?: number | null;
  cost_source_id?: number | null;
  frequency: ScheduleFrequency | string;
  interval_value: number;
  preferred_time?: string | null;
  lead_time_days?: number | null;
  last_executed_at?: string | null;
  next_due_at?: string | null;
  is_active: boolean;
  created_by_id?: number;
  created_at?: string;
  updated_at?: string;
  tool?: { id: number; name: string; code?: string } | null;
  line?: { id: number; name: string; code?: string } | null;
  workstation?: { id: number; name: string } | null;
  assignedTo?: { id: number; name?: string; username?: string } | null;
  costSource?: { id: number; name: string; code?: string } | null;
}

export interface MaintenanceScheduleFilters {
  is_active?: boolean;
  frequency?: ScheduleFrequency;
  search?: string;
  per_page?: number;
}

/** Payload for create + update (same shape on both — backend uses partial validation). */
export interface MaintenanceScheduleInput {
  name: string;
  description?: string | null;
  tool_id?: number | null;
  line_id?: number | null;
  workstation_id?: number | null;
  event_type: MaintenanceEventType;
  assigned_to_id?: number | null;
  cost_source_id?: number | null;
  frequency: ScheduleFrequency;
  interval_value: number;
  /** HH:mm — e.g. "08:00" — when in the day the generated event lands. */
  preferred_time?: string | null;
  /** How many days before next_due_at the event is actually generated. */
  lead_time_days?: number | null;
  /** ISO datetime. */
  next_due_at: string;
  is_active?: boolean;
}

/**
 * Returns the schedules as a flat array. The backend paginates (default 25
 * per page, max 100); callers that care about the meta can switch back to
 * the paginated shape later. For the mobile UI we lift per_page in the form
 * filter and consume the array directly.
 */
export const listMaintenanceSchedules = (
  filters: MaintenanceScheduleFilters = {},
): Promise<MaintenanceSchedule[]> =>
  api
    .get<ApiPaginated<MaintenanceSchedule>>('/api/v1/maintenance-schedules', { params: filters })
    .then((r) => r.data.data);

export const getMaintenanceSchedule = (id: number): Promise<MaintenanceSchedule> =>
  api
    .get<ApiEnvelope<MaintenanceSchedule>>(`/api/v1/maintenance-schedules/${id}`)
    .then((r) => r.data.data);

export const createMaintenanceSchedule = (
  input: MaintenanceScheduleInput,
): Promise<MaintenanceSchedule> =>
  api
    .post<ApiEnvelope<MaintenanceSchedule>>('/api/v1/maintenance-schedules', input)
    .then((r) => r.data.data);

export const updateMaintenanceSchedule = (
  id: number,
  input: MaintenanceScheduleInput,
): Promise<MaintenanceSchedule> =>
  api
    .patch<ApiEnvelope<MaintenanceSchedule>>(`/api/v1/maintenance-schedules/${id}`, input)
    .then((r) => r.data.data);

export const deleteMaintenanceSchedule = (id: number): Promise<void> =>
  api.delete(`/api/v1/maintenance-schedules/${id}`).then(() => undefined);

/** Force-generate one event from this schedule immediately. */
export const generateNow = (id: number): Promise<{ generated: number }> =>
  api
    .post<ApiEnvelope<{ generated: number }>>(
      `/api/v1/maintenance-schedules/${id}/generate-now`,
    )
    .then((r) => r.data.data);
