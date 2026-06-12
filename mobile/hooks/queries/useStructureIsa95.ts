import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import * as sites from '@/api/sites';
import * as areas from '@/api/areas';
import type { Area, Site } from '@/api/sites';
import { useElectricShape, type Row } from '@/hooks/useElectricShape';

const inv = (qc: ReturnType<typeof useQueryClient>, key: string) =>
  qc.invalidateQueries({ queryKey: [key] });

// ── Sites ───────────────────────────────────────────────────────────────────

/**
 * Live sites list via Electric (migrated from REST `listSites`).
 * Uses `sites` shape (all sites). Filters applied client-side.
 *
 * Shape fidelity note: `sites` shape carries raw table columns only — the
 * REST-computed `areas_count` and `lines_count` are not present in shape rows.
 * The nested `company` relation object is also absent (only `company_id` is
 * available).
 */
export function useSites(opts: sites.SiteFilters = {}) {
  const includeInactive = opts.include_inactive ?? false;
  const companyId = opts.company_id;

  return useElectricShape<Row, Site[]>('sites', {
    select: (rows) => {
      let out = rows as unknown as Site[];
      if (!includeInactive) out = out.filter((r) => r.is_active !== false);
      if (companyId !== undefined) out = out.filter((r) => Number(r.company_id) === companyId);
      return [...out].sort((a, b) => a.name.localeCompare(b.name));
    },
  });
}

// Detail-by-id — stays on REST.
export function useSite(id: number | undefined) {
  return useQuery({
    queryKey: ['site', id],
    queryFn: () => sites.getSite(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

export function useCreateSite() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: sites.createSite,
    onSuccess: () => inv(qc, 'sites'),
  });
}

export function useUpdateSite() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, input }: { id: number; input: sites.SiteInput }) =>
      sites.updateSite(id, input),
    onSuccess: (_, vars) => {
      inv(qc, 'sites');
      qc.invalidateQueries({ queryKey: ['site', vars.id] });
    },
  });
}

export function useDeleteSite() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: sites.deleteSite,
    onSuccess: () => inv(qc, 'sites'),
  });
}

// ── Areas ───────────────────────────────────────────────────────────────────

/**
 * Live areas list via Electric (migrated from REST `listAreas`).
 * Uses `areas` shape (all areas). Filters applied client-side.
 *
 * Shape fidelity note: `areas` shape carries raw table columns only — the
 * REST-computed `lines_count` is not present in shape rows. The nested `site`
 * relation object is also absent (only `site_id` is available).
 */
export function useAreas(opts: areas.AreaFilters = {}) {
  const includeInactive = opts.include_inactive ?? false;
  const siteId = opts.site_id;

  return useElectricShape<Row, Area[]>('areas', {
    select: (rows) => {
      let out = rows as unknown as Area[];
      if (!includeInactive) out = out.filter((r) => r.is_active !== false);
      if (siteId !== undefined) out = out.filter((r) => Number(r.site_id) === siteId);
      return [...out].sort((a, b) => a.name.localeCompare(b.name));
    },
  });
}

// Detail-by-id — stays on REST.
export function useArea(id: number | undefined) {
  return useQuery({
    queryKey: ['area', id],
    queryFn: () => areas.getArea(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

export function useCreateArea() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: areas.createArea,
    onSuccess: () => inv(qc, 'areas'),
  });
}

export function useUpdateArea() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, input }: { id: number; input: areas.AreaInput }) =>
      areas.updateArea(id, input),
    onSuccess: (_, vars) => {
      inv(qc, 'areas');
      qc.invalidateQueries({ queryKey: ['area', vars.id] });
    },
  });
}

export function useDeleteArea() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: areas.deleteArea,
    onSuccess: () => inv(qc, 'areas'),
  });
}
