import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import * as insp from '@/api/inspections';

const inv = (qc: ReturnType<typeof useQueryClient>, key: string) =>
  qc.invalidateQueries({ queryKey: [key] });

// ── Inspection plans ────────────────────────────────────────────────────────

/**
 * Inspection plans via REST `listInspectionPlans`.
 * The server applies material_id / material_type_id / active filters and
 * returns the criteria JSON plus material/materialType relations.
 */
export function useInspectionPlans(opts: insp.InspectionPlanFilters = {}) {
  return useQuery({
    queryKey: ['inspection-plans', opts],
    queryFn: () => insp.listInspectionPlans(opts),
  });
}

/** REST: detail-by-id — includes criteria JSON and relation fields not
 *  present in the list response. */
export function useInspectionPlan(id: number | undefined) {
  return useQuery({
    queryKey: ['inspection-plan', id],
    queryFn: () => insp.getInspectionPlan(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

// ── Inspection plan mutations (always REST) ──────────────────────────────────

export function useCreateInspectionPlan() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: insp.createInspectionPlan,
    onSuccess: () => inv(qc, 'inspection-plans'),
  });
}

export function useUpdateInspectionPlan() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: {
      id: number;
      payload: Partial<insp.CreateInspectionPlanPayload>;
    }) => insp.updateInspectionPlan(vars.id, vars.payload),
    onSuccess: () => inv(qc, 'inspection-plans'),
  });
}

export function useDeleteInspectionPlan() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: insp.deleteInspectionPlan,
    onSuccess: () => inv(qc, 'inspection-plans'),
  });
}

// ── Inspections ─────────────────────────────────────────────────────────────
// There is no `inspections` shape — inspection records are runtime/transactional.
// All inspection hooks remain on REST.

/** REST: list inspections — the list endpoint returns runtime inspection records. */
export function useInspections(opts: insp.InspectionFilters = {}) {
  return useQuery({
    queryKey: ['inspections', opts],
    queryFn: () => insp.listInspections(opts),
  });
}

/** REST: detail-by-id inspection — includes full results array. */
export function useInspection(id: number | undefined) {
  return useQuery({
    queryKey: ['inspection', id],
    queryFn: () => insp.getInspection(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

/** REST: aggregate/computed stats endpoint — no matching shape. */
export function useInspectionStats(
  opts: { days?: number; material_id?: number } = {},
) {
  return useQuery({
    queryKey: ['inspections', 'stats', opts],
    queryFn: () => insp.getInspectionStats(opts),
    refetchInterval: 60_000,
  });
}

// ── Inspection mutations (always REST) ──────────────────────────────────────

export function useStartInspection() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: insp.startInspection,
    onSuccess: () => inv(qc, 'inspections'),
  });
}

export function useRecordInspectionResult() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: {
      inspectionId: number;
      resultId: number;
      payload: insp.RecordResultPayload;
    }) =>
      insp.recordInspectionResult(vars.inspectionId, vars.resultId, vars.payload),
    onSuccess: (_data, vars) => {
      qc.invalidateQueries({ queryKey: ['inspection', vars.inspectionId] });
    },
  });
}

export function useCompleteInspection() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; payload?: insp.CompleteInspectionPayload }) =>
      insp.completeInspection(vars.id, vars.payload ?? {}),
    onSuccess: (_data, vars) => {
      inv(qc, 'inspections');
      qc.invalidateQueries({ queryKey: ['inspection', vars.id] });
    },
  });
}

export function useApplyDisposition() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; payload: insp.DispositionPayload }) =>
      insp.applyInspectionDisposition(vars.id, vars.payload),
    onSuccess: (_data, vars) => {
      inv(qc, 'inspections');
      qc.invalidateQueries({ queryKey: ['inspection', vars.id] });
      // Lot status changes after disposition — also bust the lots cache so
      // a downstream lot list refreshes its "available" badge.
      inv(qc, 'material-lots');
    },
  });
}
