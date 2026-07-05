import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  createScrapReason,
  deleteScrapReason,
  getScrapReason,
  listScrapReasons,
  updateScrapReason,
  type ScrapReasonInput,
} from '@/api/scrapReasons';

export function useScrapReasons(includeInactive = false) {
  return useQuery({
    queryKey: ['scrap-reasons', includeInactive],
    queryFn: () => listScrapReasons(includeInactive),
  });
}

export function useScrapReason(id: number | undefined) {
  return useQuery({
    queryKey: ['scrap-reason', id],
    queryFn: () => getScrapReason(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

export function useCreateScrapReason() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: ScrapReasonInput) => createScrapReason(input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['scrap-reasons'] }),
  });
}

export function useUpdateScrapReason() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; input: Partial<ScrapReasonInput> }) => updateScrapReason(vars.id, vars.input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['scrap-reasons'] }),
  });
}

export function useDeleteScrapReason() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: deleteScrapReason,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['scrap-reasons'] }),
  });
}
