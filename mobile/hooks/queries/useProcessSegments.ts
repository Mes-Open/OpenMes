import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import * as segments from '@/api/processSegments';
import type { ApiPaginated } from '@/types/api';

const inv = (qc: ReturnType<typeof useQueryClient>, key: string) =>
  qc.invalidateQueries({ queryKey: [key] });

/**
 * Process segments via REST `listProcessSegments`.
 * All filters (`segment_type`, `workstation_type_id`, `is_active`,
 * `include_inactive`, `search`, `per_page`, `page`) are pushed to the backend.
 * The `ApiPaginated<ProcessSegment>` envelope is preserved so screens that read
 * `query.data?.data` are unchanged; rows are sorted by name client-side.
 */
export function useProcessSegments(opts: segments.ProcessSegmentFilters = {}) {
  return useQuery({
    queryKey: ['process-segments', opts],
    queryFn: () => segments.listProcessSegments(opts),
    select: (page): ApiPaginated<segments.ProcessSegment> => ({
      ...page,
      data: [...page.data].sort((a, b) => a.name.localeCompare(b.name)),
    }),
  });
}

/** REST: detail-by-id — includes standard_instruction, parameters, relation
 *  fields, and template_steps_count not in the list response (use the detail endpoint). */
export function useProcessSegment(id: number | undefined) {
  return useQuery({
    queryKey: ['process-segment', id],
    queryFn: () => segments.getProcessSegment(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

// ── Mutations (always REST) ──────────────────────────────────────────────────

export function useCreateProcessSegment() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: segments.createProcessSegment,
    onSuccess: () => inv(qc, 'process-segments'),
  });
}

export function useUpdateProcessSegment() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: {
      id: number;
      payload: Partial<segments.CreateProcessSegmentPayload>;
    }) => segments.updateProcessSegment(vars.id, vars.payload),
    onSuccess: () => inv(qc, 'process-segments'),
  });
}

export function useDeleteProcessSegment() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: segments.deleteProcessSegment,
    onSuccess: () => inv(qc, 'process-segments'),
  });
}
