import { api } from './client';
import type { ApiEnvelope } from '@/types/api';

export interface Factory {
  id: number;
  code: string;
  name: string;
  description?: string | null;
  is_active: boolean;
  divisions_count?: number;
  divisions?: Division[];
}

export interface Division {
  id: number;
  factory_id: number;
  code: string;
  name: string;
  description?: string | null;
  is_active: boolean;
  lines_count?: number;
  crews_count?: number;
  factory?: Factory;
}

export interface LineStatus {
  id: number;
  line_id: number;
  name: string;
  color?: string | null;
  sort_order: number;
  is_default: boolean;
  is_done_status: boolean;
}

// Factories
export const listFactories = (includeInactive = false): Promise<Factory[]> =>
  api
    .get<ApiEnvelope<Factory[]>>('/api/v1/factories', {
      params: { include_inactive: includeInactive },
    })
    .then((r) => r.data.data);

export const getFactory = (id: number): Promise<Factory> =>
  api.get<ApiEnvelope<Factory>>(`/api/v1/factories/${id}`).then((r) => r.data.data);

export const createFactory = (payload: {
  code: string;
  name: string;
  description?: string;
  is_active?: boolean;
}): Promise<Factory> =>
  api.post<ApiEnvelope<Factory>>('/api/v1/factories', payload).then((r) => r.data.data);

export const updateFactory = (
  id: number,
  payload: Partial<{ code: string; name: string; description: string; is_active: boolean }>,
): Promise<Factory> =>
  api.patch<ApiEnvelope<Factory>>(`/api/v1/factories/${id}`, payload).then((r) => r.data.data);

export const deleteFactory = (id: number): Promise<void> =>
  api.delete(`/api/v1/factories/${id}`).then(() => undefined);

export const toggleFactoryActive = (id: number): Promise<Factory> =>
  api
    .post<ApiEnvelope<Factory>>(`/api/v1/factories/${id}/toggle-active`)
    .then((r) => r.data.data);

// Divisions
/** Global divisions list (all factories) — the admin Divisions page. */
export const listAllDivisions = (includeInactive = false): Promise<Division[]> =>
  api
    .get<ApiEnvelope<Division[]>>('/api/v1/divisions', {
      params: { include_inactive: includeInactive },
    })
    .then((r) => r.data.data);

export const listFactoryDivisions = (
  factoryId: number,
  includeInactive = false,
): Promise<Division[]> =>
  api
    .get<ApiEnvelope<Division[]>>(`/api/v1/factories/${factoryId}/divisions`, {
      params: { include_inactive: includeInactive },
    })
    .then((r) => r.data.data);

export const getDivision = (id: number): Promise<Division> =>
  api.get<ApiEnvelope<Division>>(`/api/v1/divisions/${id}`).then((r) => r.data.data);

export const createDivision = (
  factoryId: number,
  payload: { code: string; name: string; description?: string; is_active?: boolean },
): Promise<Division> =>
  api
    .post<ApiEnvelope<Division>>(`/api/v1/factories/${factoryId}/divisions`, payload)
    .then((r) => r.data.data);

export const updateDivision = (
  id: number,
  payload: Partial<{ code: string; name: string; description: string; is_active: boolean }>,
): Promise<Division> =>
  api.patch<ApiEnvelope<Division>>(`/api/v1/divisions/${id}`, payload).then((r) => r.data.data);

export const deleteDivision = (id: number): Promise<void> =>
  api.delete(`/api/v1/divisions/${id}`).then(() => undefined);

export const toggleDivisionActive = (id: number): Promise<Division> =>
  api
    .post<ApiEnvelope<Division>>(`/api/v1/divisions/${id}/toggle-active`)
    .then((r) => r.data.data);

// Line Statuses
export const listLineStatuses = (lineId: number): Promise<LineStatus[]> =>
  api.get<ApiEnvelope<LineStatus[]>>(`/api/v1/lines/${lineId}/statuses`).then((r) => r.data.data);

/** Global status catalog (line_id = null) — the admin Line Statuses page. */
export const listGlobalLineStatuses = (): Promise<LineStatus[]> =>
  api.get<ApiEnvelope<LineStatus[]>>('/api/v1/line-statuses').then((r) => r.data.data);

export const createLineStatus = (
  lineId: number,
  payload: { name: string; color?: string; is_default?: boolean; is_done_status?: boolean },
): Promise<LineStatus> =>
  api
    .post<ApiEnvelope<LineStatus>>(`/api/v1/lines/${lineId}/statuses`, payload)
    .then((r) => r.data.data);

export const updateLineStatus = (
  id: number,
  payload: Partial<{
    name: string;
    color: string;
    is_default: boolean;
    is_done_status: boolean;
    sort_order: number;
  }>,
): Promise<LineStatus> =>
  api.patch<ApiEnvelope<LineStatus>>(`/api/v1/line-statuses/${id}`, payload).then((r) => r.data.data);

export const deleteLineStatus = (id: number): Promise<void> =>
  api.delete(`/api/v1/line-statuses/${id}`).then(() => undefined);

export const reorderLineStatuses = (lineId: number, statusIds: number[]): Promise<LineStatus[]> =>
  api
    .post<ApiEnvelope<LineStatus[]>>(`/api/v1/lines/${lineId}/statuses/reorder`, {
      status_ids: statusIds,
    })
    .then((r) => r.data.data);
