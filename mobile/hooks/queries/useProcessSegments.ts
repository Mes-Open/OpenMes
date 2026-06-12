import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import * as segments from '@/api/processSegments';
import type { ApiPaginated } from '@/types/api';
import { useElectricShape, type Row } from '@/hooks/useElectricShape';

const inv = (qc: ReturnType<typeof useQueryClient>, key: string) =>
  qc.invalidateQueries({ queryKey: [key] });

/**
 * Live process segments via Electric (migrated from REST `listProcessSegments`).
 * Filters are applied client-side over the synced row set.
 *
 * Fidelity note: the `process_segments` shape does NOT include
 * `standard_instruction`, `parameters`, `template_steps_count`, or
 * `templateSteps_count` columns, and the `workstationType` / `createdBy`
 * relation objects are absent. Use `useProcessSegment(id)` (REST) when those
 * are required.
 */
export function useProcessSegments(opts: segments.ProcessSegmentFilters = {}) {
  const {
    segment_type,
    workstation_type_id,
    is_active,
    include_inactive,
    search,
  } = opts;
  const q = search?.trim().toLowerCase() ?? '';

  // Preserve the REST `ApiPaginated<ProcessSegment>` envelope so screens that
  // read `query.data?.data` are unchanged (meta undefined — full set streamed).
  return useElectricShape<Row, ApiPaginated<segments.ProcessSegment>>('process_segments', {
    select: (rows): ApiPaginated<segments.ProcessSegment> => {
      let out = rows as unknown as segments.ProcessSegment[];

      // `include_inactive` false (default) → active-only; true → all rows.
      // `is_active` explicit flag takes precedence if set.
      if (is_active !== undefined) {
        out = out.filter((r) => r.is_active === is_active);
      } else if (!include_inactive) {
        out = out.filter((r) => r.is_active !== false);
      }

      if (segment_type !== undefined) {
        out = out.filter((r) => r.segment_type === segment_type);
      }
      if (workstation_type_id !== undefined) {
        out = out.filter((r) => r.workstation_type_id === workstation_type_id);
      }
      if (q) {
        out = out.filter(
          (r) =>
            r.name.toLowerCase().includes(q) ||
            r.code.toLowerCase().includes(q),
        );
      }

      return { data: [...out].sort((a, b) => a.name.localeCompare(b.name)), meta: undefined };
    },
  });
}

/** REST: detail-by-id — includes standard_instruction, parameters, relation
 *  fields, and template_steps_count not present in the Electric shape. */
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
