import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import * as personnel from '@/api/personnel';

const inv = (qc: ReturnType<typeof useQueryClient>, key: string) =>
  qc.invalidateQueries({ queryKey: [key] });

// ── Personnel Classes (REST) ──────────────────────────────────────────────────
// REST — the server applies the is_active / include_inactive / search filters
// and returns the ApiPaginated<PersonnelClass> envelope ({ data, meta }) that
// screens read via `.data.data`.

export function usePersonnelClasses(
  opts: personnel.PersonnelClassFilters = {},
) {
  return useQuery({
    queryKey: ['personnel-classes', opts],
    queryFn: () => personnel.listPersonnelClasses(opts),
  });
}

// REST — detail-by-id (includes hydrated required_skills, workers_count).
export function usePersonnelClass(id: number | undefined) {
  return useQuery({
    queryKey: ['personnel-class', id],
    queryFn: () => personnel.getPersonnelClass(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

export function useCreatePersonnelClass() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: personnel.createPersonnelClass,
    onSuccess: () => inv(qc, 'personnel-classes'),
  });
}

export function useUpdatePersonnelClass() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: {
      id: number;
      payload: Partial<personnel.CreatePersonnelClassPayload>;
    }) => personnel.updatePersonnelClass(vars.id, vars.payload),
    onSuccess: () => inv(qc, 'personnel-classes'),
  });
}

export function useDeletePersonnelClass() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; force?: boolean }) =>
      personnel.deletePersonnelClass(vars.id, { force: vars.force }),
    onSuccess: () => inv(qc, 'personnel-classes'),
  });
}
