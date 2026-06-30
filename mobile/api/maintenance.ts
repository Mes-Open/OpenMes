import { api } from './client';
import type { ApiEnvelope, ApiPaginated } from '@/types/api';

export type ToolStatus = 'available' | 'in_use' | 'maintenance' | 'retired';

export interface Tool {
  id: number;
  code: string;
  name: string;
  description?: string | null;
  workstation_type_id?: number | null;
  workstation_type?: { id: number; name: string } | null;
  status: ToolStatus;
  next_service_at?: string | null;
}

export type MaintenanceEventType = 'planned' | 'corrective' | 'inspection';
export type MaintenanceEventStatus = 'pending' | 'in_progress' | 'completed' | 'cancelled';

export interface MaintenanceEvent {
  id: number;
  title: string;
  event_type: MaintenanceEventType;
  status: MaintenanceEventStatus;
  tool_id?: number | null;
  tool?: { id: number; name: string; code: string } | null;
  line_id?: number | null;
  line?: { id: number; name: string } | null;
  workstation_id?: number | null;
  workstation?: { id: number; name: string } | null;
  cost_source_id?: number | null;
  cost_source?: { id: number; name: string } | null;
  assigned_to_id?: number | null;
  scheduled_at?: string | null;
  started_at?: string | null;
  completed_at?: string | null;
  description?: string | null;
  resolution_notes?: string | null;
  actual_cost?: string | number | null;
  currency?: string | null;
}

// ── Tools ──────────────────────────────────────────────────────────────────

export interface ToolFilters {
  status?: ToolStatus;
  workstation_type_id?: number;
  q?: string;
}

export const listTools = (filters: ToolFilters = {}): Promise<Tool[]> =>
  api.get<ApiEnvelope<Tool[]>>('/api/v1/tools', { params: filters }).then((r) => r.data.data);

export const getTool = (id: number): Promise<Tool> =>
  api.get<ApiEnvelope<Tool>>(`/api/v1/tools/${id}`).then((r) => r.data.data);

export interface CreateToolPayload {
  code: string;
  name: string;
  description?: string;
  workstation_type_id?: number;
  status?: ToolStatus;
  next_service_at?: string;
}

export const createTool = (payload: CreateToolPayload): Promise<Tool> =>
  api.post<ApiEnvelope<Tool>>('/api/v1/tools', payload).then((r) => r.data.data);

export const updateTool = (
  id: number,
  payload: Partial<CreateToolPayload>,
): Promise<Tool> =>
  api.patch<ApiEnvelope<Tool>>(`/api/v1/tools/${id}`, payload).then((r) => r.data.data);

export const deleteTool = (id: number): Promise<void> =>
  api.delete(`/api/v1/tools/${id}`).then(() => undefined);

export const transitionToolStatus = (id: number, status: ToolStatus): Promise<Tool> =>
  api.post<ApiEnvelope<Tool>>(`/api/v1/tools/${id}/status`, { status }).then((r) => r.data.data);

// ── Maintenance events ─────────────────────────────────────────────────────

export interface MaintenanceEventFilters {
  status?: MaintenanceEventStatus;
  tool_id?: number;
  line_id?: number;
  from?: string;
  to?: string;
  per_page?: number;
  page?: number;
}

export const listMaintenanceEvents = (
  filters: MaintenanceEventFilters = {},
): Promise<{ data: MaintenanceEvent[]; meta?: ApiPaginated<MaintenanceEvent>['meta'] }> =>
  api
    .get<ApiPaginated<MaintenanceEvent>>('/api/v1/maintenance-events', { params: filters })
    .then((r) => ({ data: r.data.data, meta: r.data.meta }));

export const getMaintenanceEvent = (id: number): Promise<MaintenanceEvent> =>
  api
    .get<ApiEnvelope<MaintenanceEvent>>(`/api/v1/maintenance-events/${id}`)
    .then((r) => r.data.data);

export interface CreateMaintenanceEventPayload {
  title: string;
  event_type: MaintenanceEventType;
  tool_id?: number;
  line_id?: number;
  workstation_id?: number;
  cost_source_id?: number;
  assigned_to_id?: number;
  scheduled_at?: string;
  description?: string;
  actual_cost?: number;
  currency?: string;
}

export const createMaintenanceEvent = (
  payload: CreateMaintenanceEventPayload,
): Promise<MaintenanceEvent> =>
  api
    .post<ApiEnvelope<MaintenanceEvent>>('/api/v1/maintenance-events', payload)
    .then((r) => r.data.data);

export const updateMaintenanceEvent = (
  id: number,
  payload: Partial<CreateMaintenanceEventPayload> & { resolution_notes?: string },
): Promise<MaintenanceEvent> =>
  api
    .patch<ApiEnvelope<MaintenanceEvent>>(`/api/v1/maintenance-events/${id}`, payload)
    .then((r) => r.data.data);

export const deleteMaintenanceEvent = (id: number): Promise<void> =>
  api.delete(`/api/v1/maintenance-events/${id}`).then(() => undefined);

export const startMaintenanceEvent = (id: number): Promise<MaintenanceEvent> =>
  api
    .post<ApiEnvelope<MaintenanceEvent>>(`/api/v1/maintenance-events/${id}/start`)
    .then((r) => r.data.data);

export const completeMaintenanceEvent = (
  id: number,
  payload: { resolution_notes?: string; actual_cost?: number; currency?: string } = {},
): Promise<MaintenanceEvent> =>
  api
    .post<ApiEnvelope<MaintenanceEvent>>(`/api/v1/maintenance-events/${id}/complete`, payload)
    .then((r) => r.data.data);

export const cancelMaintenanceEvent = (id: number): Promise<MaintenanceEvent> =>
  api
    .post<ApiEnvelope<MaintenanceEvent>>(`/api/v1/maintenance-events/${id}/cancel`)
    .then((r) => r.data.data);
