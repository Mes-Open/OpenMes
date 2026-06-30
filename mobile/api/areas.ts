import { api } from './client';
import type { ApiEnvelope } from '@/types/api';
import type { Area } from './sites';

// Re-exported so call sites can `import type { Area } from '@/api/areas'`.
export type { Area };

export interface AreaFilters {
  include_inactive?: boolean;
  site_id?: number;
}

export interface AreaInput {
  name: string;
  code: string;
  site_id: number;
  description?: string | null;
  is_active?: boolean;
}

export const listAreas = (opts: AreaFilters = {}): Promise<Area[]> =>
  api
    .get<ApiEnvelope<Area[]>>('/api/v1/areas', { params: opts })
    .then((r) => r.data.data);

export const getArea = (id: number): Promise<Area> =>
  api.get<ApiEnvelope<Area>>(`/api/v1/areas/${id}`).then((r) => r.data.data);

export const createArea = (input: AreaInput): Promise<Area> =>
  api.post<ApiEnvelope<Area>>('/api/v1/areas', input).then((r) => r.data.data);

export const updateArea = (id: number, input: AreaInput): Promise<Area> =>
  api.patch<ApiEnvelope<Area>>(`/api/v1/areas/${id}`, input).then((r) => r.data.data);

export const deleteArea = (id: number): Promise<void> =>
  api.delete(`/api/v1/areas/${id}`).then(() => undefined);
