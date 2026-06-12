import { api } from './client';
import type { ApiEnvelope } from '@/types/api';

export interface Workstation {
  id: number;
  line_id: number;
  workstation_type_id?: number | null;
  workstation_type?: string | null;
  code: string;
  name: string;
  is_active: boolean;
  template_steps_count?: number;
  workers_count?: number;
}

export const listWorkstations = (
  lineId: number,
  includeInactive = false,
): Promise<Workstation[]> =>
  api
    .get<ApiEnvelope<Workstation[]>>(`/api/v1/lines/${lineId}/workstations`, {
      params: { include_inactive: includeInactive },
    })
    .then((r) => r.data.data);

export const getWorkstation = (id: number): Promise<Workstation> =>
  api.get<ApiEnvelope<Workstation>>(`/api/v1/workstations/${id}`).then((r) => r.data.data);

export interface CreateWorkstationPayload {
  code: string;
  name: string;
  workstation_type?: string;
  workstation_type_id?: number;
  is_active?: boolean;
}

export const createWorkstation = (
  lineId: number,
  payload: CreateWorkstationPayload,
): Promise<Workstation> =>
  api
    .post<ApiEnvelope<Workstation>>(`/api/v1/lines/${lineId}/workstations`, payload)
    .then((r) => r.data.data);

export const updateWorkstation = (
  id: number,
  payload: Partial<CreateWorkstationPayload>,
): Promise<Workstation> =>
  api
    .patch<ApiEnvelope<Workstation>>(`/api/v1/workstations/${id}`, payload)
    .then((r) => r.data.data);

export const deleteWorkstation = (id: number): Promise<void> =>
  api.delete(`/api/v1/workstations/${id}`).then(() => undefined);

export const toggleWorkstationActive = (id: number): Promise<Workstation> =>
  api
    .post<ApiEnvelope<Workstation>>(`/api/v1/workstations/${id}/toggle-active`)
    .then((r) => r.data.data);
