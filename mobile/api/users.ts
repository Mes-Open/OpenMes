import { api } from './client';
import type { ApiEnvelope, ApiPaginated, Line, User } from '@/types/api';

export interface UserFilters {
  role?: string;
  line_id?: number;
  account_type?: 'user' | 'workstation';
  q?: string;
  per_page?: number;
  page?: number;
}

export const listUsers = (
  filters: UserFilters = {},
): Promise<{ data: User[]; meta?: ApiPaginated<User>['meta'] }> =>
  api
    .get<ApiPaginated<User>>('/api/v1/users', { params: filters })
    .then((r) => ({ data: r.data.data, meta: r.data.meta }));

export const getUser = (id: number): Promise<User> =>
  api.get<ApiEnvelope<User>>(`/api/v1/users/${id}`).then((r) => r.data.data);

export interface CreateUserPayload {
  name: string;
  username: string;
  email: string;
  password: string;
  account_type: 'user' | 'workstation';
  role?: string;
  workstation_id?: number;
  worker_id?: number;
  force_password_change?: boolean;
  line_ids?: number[];
}

export const createUser = (payload: CreateUserPayload): Promise<User> =>
  api.post<ApiEnvelope<User>>('/api/v1/users', payload).then((r) => r.data.data);

export type UpdateUserPayload = Partial<Omit<CreateUserPayload, 'password'>>;

export const updateUser = (id: number, payload: UpdateUserPayload): Promise<User> =>
  api.patch<ApiEnvelope<User>>(`/api/v1/users/${id}`, payload).then((r) => r.data.data);

export const deleteUser = (id: number): Promise<void> =>
  api.delete(`/api/v1/users/${id}`).then(() => undefined);

export const resetUserPassword = (
  id: number,
  payload: { password: string; force_password_change?: boolean },
): Promise<void> =>
  api.post(`/api/v1/users/${id}/reset-password`, payload).then(() => undefined);

export const getUserLines = (id: number): Promise<Line[]> =>
  api.get<ApiEnvelope<Line[]>>(`/api/v1/users/${id}/lines`).then((r) => r.data.data);

export const setUserLines = (id: number, lineIds: number[]): Promise<Line[]> =>
  api
    .post<ApiEnvelope<Line[]>>(`/api/v1/users/${id}/lines`, { line_ids: lineIds })
    .then((r) => r.data.data);

export interface RoleSummary {
  id: number;
  name: string;
}

export const listRoles = (): Promise<RoleSummary[]> =>
  api.get<ApiEnvelope<RoleSummary[]>>('/api/v1/roles').then((r) => r.data.data);
