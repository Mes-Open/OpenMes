import { api } from './client';
import type { ApiEnvelope } from '@/types/api';

export interface WorkstationType {
  id: number;
  code: string;
  name: string;
  description?: string | null;
  is_active: boolean;
  workstations_count?: number;
}

export interface WorkstationTypeFilters {
  include_inactive?: boolean;
  q?: string;
}

export const listWorkstationTypes = (
  filters: WorkstationTypeFilters = {},
): Promise<WorkstationType[]> =>
  api
    .get<ApiEnvelope<WorkstationType[]>>('/api/v1/workstation-types', { params: filters })
    .then((r) => r.data.data);

export const getWorkstationType = (id: number): Promise<WorkstationType> =>
  api
    .get<ApiEnvelope<WorkstationType>>(`/api/v1/workstation-types/${id}`)
    .then((r) => r.data.data);

export interface CreateWorkstationTypePayload {
  code: string;
  name: string;
  description?: string;
  is_active?: boolean;
}

export const createWorkstationType = (
  payload: CreateWorkstationTypePayload,
): Promise<WorkstationType> =>
  api
    .post<ApiEnvelope<WorkstationType>>('/api/v1/workstation-types', payload)
    .then((r) => r.data.data);

export const updateWorkstationType = (
  id: number,
  payload: Partial<CreateWorkstationTypePayload>,
): Promise<WorkstationType> =>
  api
    .patch<ApiEnvelope<WorkstationType>>(`/api/v1/workstation-types/${id}`, payload)
    .then((r) => r.data.data);

export const deleteWorkstationType = (id: number): Promise<void> =>
  api.delete(`/api/v1/workstation-types/${id}`).then(() => undefined);

export const toggleWorkstationTypeActive = (id: number): Promise<WorkstationType> =>
  api
    .post<ApiEnvelope<WorkstationType>>(`/api/v1/workstation-types/${id}/toggle-active`)
    .then((r) => r.data.data);
