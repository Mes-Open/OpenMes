import { api } from './client';
import type { ApiEnvelope, Line, ProductType, User } from '@/types/api';

export interface LineFilters {
  include_inactive?: boolean;
  division_id?: number;
  q?: string;
}

export const listLines = (filters: LineFilters = {}): Promise<Line[]> =>
  api.get<ApiEnvelope<Line[]>>('/api/v1/lines', { params: filters }).then((r) => r.data.data);

export const getLine = (id: number): Promise<Line> =>
  api.get<ApiEnvelope<Line>>(`/api/v1/lines/${id}`).then((r) => r.data.data);

export interface CreateLinePayload {
  code: string;
  name: string;
  description?: string;
  division_id?: number;
  is_active?: boolean;
}

export const createLine = (payload: CreateLinePayload): Promise<Line> =>
  api.post<ApiEnvelope<Line>>('/api/v1/lines', payload).then((r) => r.data.data);

export type UpdateLinePayload = Partial<CreateLinePayload>;

export const updateLine = (id: number, payload: UpdateLinePayload): Promise<Line> =>
  api.patch<ApiEnvelope<Line>>(`/api/v1/lines/${id}`, payload).then((r) => r.data.data);

export const deleteLine = (id: number): Promise<void> =>
  api.delete(`/api/v1/lines/${id}`).then(() => undefined);

export const toggleLineActive = (id: number): Promise<Line> =>
  api.post<ApiEnvelope<Line>>(`/api/v1/lines/${id}/toggle-active`).then((r) => r.data.data);

export const getLineUsers = (id: number): Promise<User[]> =>
  api.get<ApiEnvelope<User[]>>(`/api/v1/lines/${id}/users`).then((r) => r.data.data);

export const syncLineUsers = (id: number, userIds: number[]): Promise<User[]> =>
  api
    .post<ApiEnvelope<User[]>>(`/api/v1/lines/${id}/users`, { user_ids: userIds })
    .then((r) => r.data.data);

export const unassignLineUser = (lineId: number, userId: number): Promise<void> =>
  api.delete(`/api/v1/lines/${lineId}/users/${userId}`).then(() => undefined);

export const getLineProductTypes = (id: number): Promise<ProductType[]> =>
  api.get<ApiEnvelope<ProductType[]>>(`/api/v1/lines/${id}/product-types`).then((r) => r.data.data);

export const syncLineProductTypes = (
  id: number,
  productTypeIds: number[],
): Promise<ProductType[]> =>
  api
    .post<ApiEnvelope<ProductType[]>>(`/api/v1/lines/${id}/product-types`, {
      product_type_ids: productTypeIds,
    })
    .then((r) => r.data.data);
