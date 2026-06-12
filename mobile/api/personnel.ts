import { api } from './client';
import type { ApiEnvelope, ApiPaginated } from '@/types/api';

export interface PersonnelClass {
  id: number;
  code: string;
  name: string;
  description?: string | null;
  required_skill_ids?: number[] | null;
  /** Optional override map { [skillId]: certLevel }. Shape isn't strict on the
   *  server — see backend docs/isa95.md. */
  default_required_cert_level?: Record<string, number> | null;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
  workers_count?: number;
  /** On show endpoint, hydrated skills inferred from required_skill_ids. */
  required_skills?: Array<{ id: number; name: string; code?: string }>;
}

export interface PersonnelClassFilters {
  is_active?: boolean;
  include_inactive?: boolean;
  search?: string;
  per_page?: number;
  page?: number;
}

export interface CreatePersonnelClassPayload {
  code: string;
  name: string;
  description?: string;
  required_skill_ids?: number[];
  default_required_cert_level?: Record<string, number>;
  is_active?: boolean;
}

export const listPersonnelClasses = (
  opts: PersonnelClassFilters = {},
): Promise<ApiPaginated<PersonnelClass>> =>
  api
    .get<ApiPaginated<PersonnelClass>>('/api/v1/personnel-classes', { params: opts })
    .then((r) => r.data);

export const getPersonnelClass = (id: number): Promise<PersonnelClass> =>
  api
    .get<ApiEnvelope<PersonnelClass>>(`/api/v1/personnel-classes/${id}`)
    .then((r) => r.data.data);

export const createPersonnelClass = (
  payload: CreatePersonnelClassPayload,
): Promise<PersonnelClass> =>
  api
    .post<ApiEnvelope<PersonnelClass>>('/api/v1/personnel-classes', payload)
    .then((r) => r.data.data);

export const updatePersonnelClass = (
  id: number,
  payload: Partial<CreatePersonnelClassPayload>,
): Promise<PersonnelClass> =>
  api
    .patch<ApiEnvelope<PersonnelClass>>(`/api/v1/personnel-classes/${id}`, payload)
    .then((r) => r.data.data);

export const deletePersonnelClass = (
  id: number,
  opts: { force?: boolean } = {},
): Promise<void> =>
  api
    .delete(`/api/v1/personnel-classes/${id}`, { params: opts })
    .then(() => undefined);
