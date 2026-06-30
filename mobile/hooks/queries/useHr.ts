import { useQuery } from '@tanstack/react-query';

import {
  getCrew,
  getCrewWorkers,
  getSkill,
  getWageGroup,
  getWorker,
  type Crew,
  type Skill,
  type WageGroup,
  type Worker,
  type WorkerFilters,
} from '@/api/hr';
import { useElectricShape, type Row } from '@/hooks/useElectricShape';

// ── Skills (Electric) ────────────────────────────────────────────────────────
// Shape `skills` columns: id, code, name, description.
// No is_active column on skills — filter by q only.

export function useSkills(q?: string) {
  const search = q?.trim().toLowerCase() ?? '';

  return useElectricShape<Row, Skill[]>('skills', {
    select: (rows) => {
      let out = rows as unknown as Skill[];
      if (search) {
        out = out.filter(
          (r) =>
            r.name.toLowerCase().includes(search) ||
            r.code.toLowerCase().includes(search),
        );
      }
      return [...out].sort((a, b) => a.name.localeCompare(b.name));
    },
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

// ── Wage Groups (Electric) ───────────────────────────────────────────────────
// Shape `wage_groups` columns: id, code, name, description, base_hourly_rate,
// currency, is_active.
// Note: REST-computed `workers_count` is absent from the shape.

export function useWageGroups(includeInactive = false) {
  return useElectricShape<Row, WageGroup[]>('wage_groups', {
    select: (rows) => {
      let out = rows as unknown as WageGroup[];
      if (!includeInactive) out = out.filter((r) => r.is_active !== false);
      return [...out].sort((a, b) => a.name.localeCompare(b.name));
    },
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

// ── Crews (Electric) ─────────────────────────────────────────────────────────
// Shape `crews` columns: id, code, name, leader_id, division_id, description,
// is_active.
// Note: REST-computed `workers_count`, and the hydrated `leader`/`division`
// relation objects are absent from the shape.

export function useCrews(includeInactive = false) {
  return useElectricShape<Row, Crew[]>('crews', {
    select: (rows) => {
      let out = rows as unknown as Crew[];
      if (!includeInactive) out = out.filter((r) => r.is_active !== false);
      return [...out].sort((a, b) => a.name.localeCompare(b.name));
    },
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

// ── Workers (Electric) ───────────────────────────────────────────────────────
// Shape `workers` columns: id, code, name, email, phone, crew_id,
// wage_group_id, personnel_class_id, workstation_id, is_active.
// Hydrated relations (crew, wage_group, workstation, personnel_class, skills)
// are absent — FK ids are present.
// REST-returned pagination meta is not available from Electric; `meta` is
// always undefined here.

export function useWorkers(filters: WorkerFilters = {}) {
  const includeInactive = filters.include_inactive ?? false;
  const crewId = filters.crew_id;
  const wageGroupId = filters.wage_group_id;
  const search = filters.q?.trim().toLowerCase() ?? '';

  const result = useElectricShape<Row, Worker[]>('workers', {
    select: (rows) => {
      let out = rows as unknown as Worker[];
      if (!includeInactive) out = out.filter((r) => r.is_active !== false);
      if (crewId !== undefined) out = out.filter((r) => r.crew_id === crewId);
      if (wageGroupId !== undefined) out = out.filter((r) => r.wage_group_id === wageGroupId);
      if (search) {
        out = out.filter(
          (r) =>
            r.name.toLowerCase().includes(search) ||
            r.code.toLowerCase().includes(search),
        );
      }
      return [...out].sort((a, b) => a.name.localeCompare(b.name));
    },
  });

  // Preserve the {data, meta} return shape that listWorkers returns.
  return {
    ...result,
    data: { data: result.data, meta: undefined } as {
      data: Worker[];
      meta: undefined;
    },
  };
}

// REST — detail-by-id (includes hydrated relations and skills pivot).
export function useWorker(id: number | undefined) {
  return useQuery({
    queryKey: ['worker', id],
    queryFn: () => getWorker(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}
