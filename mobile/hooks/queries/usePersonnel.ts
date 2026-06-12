import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import * as personnel from '@/api/personnel';
import { useElectricShape, type Row } from '@/hooks/useElectricShape';

const inv = (qc: ReturnType<typeof useQueryClient>, key: string) =>
  qc.invalidateQueries({ queryKey: [key] });

// ── Personnel Classes (Electric) ─────────────────────────────────────────────
// Shape `personnel_classes` columns: id, code, name, description,
// required_skill_ids, default_required_cert_level, is_active.
// REST-computed `workers_count` and hydrated `required_skills` are absent.
// REST returns ApiPaginated<PersonnelClass>; Electric returns a flat array.
// We wrap in `{ data, meta: undefined }` to keep the return shape compatible.

export function usePersonnelClasses(
  opts: personnel.PersonnelClassFilters = {},
) {
  const includeInactive = opts.include_inactive ?? false;
  const isActiveFilter = opts.is_active;
  const search = opts.search?.trim().toLowerCase() ?? '';

  const result = useElectricShape<Row, personnel.PersonnelClass[]>('personnel_classes', {
    select: (rows) => {
      let out = rows as unknown as personnel.PersonnelClass[];

      // is_active explicit filter takes precedence; otherwise fall back to
      // include_inactive flag (matching REST behaviour).
      if (isActiveFilter !== undefined) {
        out = out.filter((r) => r.is_active === isActiveFilter);
      } else if (!includeInactive) {
        out = out.filter((r) => r.is_active !== false);
      }

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

  // Preserve the ApiPaginated<PersonnelClass> return shape (screens read .data).
  return {
    ...result,
    data: { data: result.data, meta: undefined } as {
      data: personnel.PersonnelClass[];
      meta: undefined;
    },
  };
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
