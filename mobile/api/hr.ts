import { api } from './client';
import type { ApiEnvelope, ApiPaginated } from '@/types/api';

// ── Types ────────────────────────────────────────────────────────────────────

export interface Skill {
  id: number;
  code: string;
  name: string;
  description?: string | null;
  workers_count?: number;
}

export interface WageGroup {
  id: number;
  code: string;
  name: string;
  description?: string | null;
  base_hourly_rate?: string | number | null;
  currency?: string | null;
  is_active: boolean;
  workers_count?: number;
}

export interface Crew {
  id: number;
  code: string;
  name: string;
  description?: string | null;
  leader_id?: number | null;
  leader?: { id: number; username: string; name?: string | null } | null;
  division_id?: number | null;
  division?: { id: number; name: string } | null;
  is_active: boolean;
  workers_count?: number;
}

/** Pivot on worker_skill — extended with certification window in migration
 *  `add_certification_to_worker_skills` (2026-05-22). Both timestamps are
 *  optional: skills without a formal certification carry only `level`. */
export interface WorkerSkillPivot {
  level: number;
  certified_at?: string | null;
  expires_at?: string | null;
}

export interface Worker {
  id: number;
  code: string;
  name: string;
  email?: string | null;
  phone?: string | null;
  crew_id?: number | null;
  crew?: Crew | null;
  wage_group_id?: number | null;
  wage_group?: WageGroup | null;
  workstation_id?: number | null;
  workstation?: { id: number; name: string } | null;
  /** Migration `add_personnel_class_id_to_workers` (2026-05-22). When set, the
   *  worker inherits skill requirements + cert defaults from the class. */
  personnel_class_id?: number | null;
  personnel_class?: { id: number; code: string; name: string } | null;
  is_active: boolean;
  skills?: Array<Skill & { pivot?: WorkerSkillPivot }>;
}

// ── Skills ──────────────────────────────────────────────────────────────────

export const listSkills = (q?: string): Promise<Skill[]> =>
  api.get<ApiEnvelope<Skill[]>>('/api/v1/skills', { params: { q } }).then((r) => r.data.data);

export const getSkill = (id: number): Promise<Skill> =>
  api.get<ApiEnvelope<Skill>>(`/api/v1/skills/${id}`).then((r) => r.data.data);

export const createSkill = (payload: {
  code: string;
  name: string;
  description?: string;
}): Promise<Skill> =>
  api.post<ApiEnvelope<Skill>>('/api/v1/skills', payload).then((r) => r.data.data);

export const updateSkill = (
  id: number,
  payload: Partial<{ code: string; name: string; description: string }>,
): Promise<Skill> =>
  api.patch<ApiEnvelope<Skill>>(`/api/v1/skills/${id}`, payload).then((r) => r.data.data);

export const deleteSkill = (id: number): Promise<void> =>
  api.delete(`/api/v1/skills/${id}`).then(() => undefined);

// ── Wage Groups ─────────────────────────────────────────────────────────────

export const listWageGroups = (includeInactive = false): Promise<WageGroup[]> =>
  api
    .get<ApiEnvelope<WageGroup[]>>('/api/v1/wage-groups', {
      params: { include_inactive: includeInactive },
    })
    .then((r) => r.data.data);

export const getWageGroup = (id: number): Promise<WageGroup> =>
  api.get<ApiEnvelope<WageGroup>>(`/api/v1/wage-groups/${id}`).then((r) => r.data.data);

export interface CreateWageGroupPayload {
  code: string;
  name: string;
  description?: string;
  base_hourly_rate?: number;
  currency?: string;
  is_active?: boolean;
}

export const createWageGroup = (payload: CreateWageGroupPayload): Promise<WageGroup> =>
  api.post<ApiEnvelope<WageGroup>>('/api/v1/wage-groups', payload).then((r) => r.data.data);

export const updateWageGroup = (
  id: number,
  payload: Partial<CreateWageGroupPayload>,
): Promise<WageGroup> =>
  api.patch<ApiEnvelope<WageGroup>>(`/api/v1/wage-groups/${id}`, payload).then((r) => r.data.data);

export const deleteWageGroup = (id: number): Promise<void> =>
  api.delete(`/api/v1/wage-groups/${id}`).then(() => undefined);

export const toggleWageGroupActive = (id: number): Promise<WageGroup> =>
  api
    .post<ApiEnvelope<WageGroup>>(`/api/v1/wage-groups/${id}/toggle-active`)
    .then((r) => r.data.data);

// ── Crews ───────────────────────────────────────────────────────────────────

export const listCrews = (includeInactive = false): Promise<Crew[]> =>
  api
    .get<ApiEnvelope<Crew[]>>('/api/v1/crews', { params: { include_inactive: includeInactive } })
    .then((r) => r.data.data);

export const getCrew = (id: number): Promise<Crew> =>
  api.get<ApiEnvelope<Crew>>(`/api/v1/crews/${id}`).then((r) => r.data.data);

export const getCrewWorkers = (id: number): Promise<Worker[]> =>
  api.get<ApiEnvelope<Worker[]>>(`/api/v1/crews/${id}/workers`).then((r) => r.data.data);

export interface CreateCrewPayload {
  code: string;
  name: string;
  description?: string;
  leader_id?: number;
  division_id?: number;
  is_active?: boolean;
}

export const createCrew = (payload: CreateCrewPayload): Promise<Crew> =>
  api.post<ApiEnvelope<Crew>>('/api/v1/crews', payload).then((r) => r.data.data);

export const updateCrew = (id: number, payload: Partial<CreateCrewPayload>): Promise<Crew> =>
  api.patch<ApiEnvelope<Crew>>(`/api/v1/crews/${id}`, payload).then((r) => r.data.data);

export const deleteCrew = (id: number): Promise<void> =>
  api.delete(`/api/v1/crews/${id}`).then(() => undefined);

export const toggleCrewActive = (id: number): Promise<Crew> =>
  api.post<ApiEnvelope<Crew>>(`/api/v1/crews/${id}/toggle-active`).then((r) => r.data.data);

// ── Workers ─────────────────────────────────────────────────────────────────

export interface WorkerFilters {
  include_inactive?: boolean;
  crew_id?: number;
  wage_group_id?: number;
  q?: string;
  page?: number;
  per_page?: number;
}

export const listWorkers = (
  filters: WorkerFilters = {},
): Promise<{ data: Worker[]; meta?: ApiPaginated<Worker>['meta'] }> =>
  api
    .get<ApiPaginated<Worker>>('/api/v1/workers', { params: filters })
    .then((r) => ({ data: r.data.data, meta: r.data.meta }));

export const getWorker = (id: number): Promise<Worker> =>
  api.get<ApiEnvelope<Worker>>(`/api/v1/workers/${id}`).then((r) => r.data.data);

export interface CreateWorkerPayload {
  code: string;
  name: string;
  email?: string;
  phone?: string;
  crew_id?: number;
  wage_group_id?: number;
  workstation_id?: number;
  personnel_class_id?: number;
  is_active?: boolean;
  /** Pivot rows: certified_at + expires_at let admins set a cert window
   *  alongside the level. Both timestamps are ISO date strings. */
  skills?: Array<{
    id: number;
    level?: number;
    certified_at?: string;
    expires_at?: string;
  }>;
}

export const createWorker = (payload: CreateWorkerPayload): Promise<Worker> =>
  api.post<ApiEnvelope<Worker>>('/api/v1/workers', payload).then((r) => r.data.data);

export const updateWorker = (
  id: number,
  payload: Partial<Omit<CreateWorkerPayload, 'skills'>>,
): Promise<Worker> =>
  api.patch<ApiEnvelope<Worker>>(`/api/v1/workers/${id}`, payload).then((r) => r.data.data);

export const deleteWorker = (id: number): Promise<void> =>
  api.delete(`/api/v1/workers/${id}`).then(() => undefined);

export const syncWorkerSkills = (
  id: number,
  skills: Array<{ id: number; level?: number }>,
): Promise<Skill[]> =>
  api
    .post<ApiEnvelope<Skill[]>>(`/api/v1/workers/${id}/skills`, { skills })
    .then((r) => r.data.data);
