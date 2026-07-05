import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import * as sites from '@/api/sites';
import * as areas from '@/api/areas';

const inv = (qc: ReturnType<typeof useQueryClient>, key: string) =>
  qc.invalidateQueries({ queryKey: [key] });

// ── Sites ───────────────────────────────────────────────────────────────────

/**
 * Sites list via REST `listSites`.
 * `include_inactive` and `company_id` are pushed to the backend;
 * results sorted by name.
 */
export function useSites(opts: sites.SiteFilters = {}) {
  return useQuery({
    queryKey: ['sites', opts],
    queryFn: () => sites.listSites(opts),
    select: (rows) => [...rows].sort((a, b) => a.name.localeCompare(b.name)),
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
 * Areas list via REST `listAreas`.
 * `include_inactive` and `site_id` are pushed to the backend;
 * results sorted by name.
 */
export function useAreas(opts: areas.AreaFilters = {}) {
  return useQuery({
    queryKey: ['areas', opts],
    queryFn: () => areas.listAreas(opts),
    select: (rows) => [...rows].sort((a, b) => a.name.localeCompare(b.name)),
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
