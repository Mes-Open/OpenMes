import { useQuery } from '@tanstack/react-query';

import {
  getCrew,
  getCrewWorkers,
  getSkill,
  getWageGroup,
  getWorker,
  listCrews,
  listSkills,
  listWageGroups,
  listWorkers,
  type WorkerFilters,
} from '@/api/hr';

// ── Skills (REST) ─────────────────────────────────────────────────────────────
// REST — the server applies the q filter and returns the skill rows.

export function useSkills(q?: string) {
  return useQuery({
    queryKey: ['skills', q],
    queryFn: () => listSkills(q),
  });
}

// REST — detail-by-id.
export function useSkill(id: number | undefined) {
  return useQuery({
    queryKey: ['skill', id],
    queryFn: () => getSkill(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

// ── Wage Groups (REST) ────────────────────────────────────────────────────────
// REST — the server applies the include_inactive filter and returns the
// wage-group rows (with computed workers_count).

export function useWageGroups(includeInactive = false) {
  return useQuery({
    queryKey: ['wage-groups', { includeInactive }],
    queryFn: () => listWageGroups(includeInactive),
  });
}

// REST — detail-by-id.
export function useWageGroup(id: number | undefined) {
  return useQuery({
    queryKey: ['wage-group', id],
    queryFn: () => getWageGroup(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

// ── Crews ─────────────────────────────────────────────────────────────────
// Shape `crews` columns: id, code, name, leader_id, division_id, description,
// is_active.
// Note: REST-computed `workers_count`, and the hydrated `leader`/`division`
// relation objects are absent from the shape.

export function useCrews(includeInactive = false) {
  return useQuery({
    queryKey: ['crews', { includeInactive }],
    queryFn: () => listCrews(includeInactive),
  });
}

// REST — crews list (for pickers that just need id/name).
export function useCrewsList(includeInactive = false) {
  return useQuery({
    queryKey: ['crews', { includeInactive }],
    queryFn: () => listCrews(includeInactive),
  });
}

// REST — detail-by-id (includes hydrated leader/division relations).
export function useCrew(id: number | undefined) {
  return useQuery({
    queryKey: ['crew', id],
    queryFn: () => getCrew(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

// REST — relation endpoint: workers belonging to a specific crew.
export function useCrewWorkers(id: number | undefined) {
  return useQuery({
    queryKey: ['crew', id, 'workers'],
    queryFn: () => getCrewWorkers(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

// ── Workers ─────────────────────────────────────────────────────────────
// REST — server applies q / crew_id / wage_group_id / include_inactive filters
// and returns the paginated { data, meta } shape callers already read (with the
// hydrated crew / wage_group / personnel_class relations).
export function useWorkers(filters: WorkerFilters = {}) {
  return useQuery({
    queryKey: ['workers', filters],
    queryFn: () => listWorkers(filters),
  });
}

// REST — detail-by-id (includes hydrated relations and skills pivot).
export function useWorker(id: number | undefined) {
  return useQuery({
    queryKey: ['worker', id],
    queryFn: () => getWorker(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}
