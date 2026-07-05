import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import * as lots from '@/api/materialLots';

/**
 * Material lots via REST `listMaterialLots`.
 *
 * The server applies the material_id / status / supplier_lot_no /
 * available_only / expiring_within_days filters and returns the
 * `ApiPaginated<MaterialLot>` envelope ({ data, meta }) that consuming screens
 * read via `.data.data` / `.data.meta`.
 *
 * Fidelity note: the list endpoint returns raw table columns only — relation
 * fields (material, source, inspection, sublots, createdBy) are NOT present;
 * consumers that need those must call `useMaterialLot(id)` (REST).
 */
export function useMaterialLots(opts: lots.MaterialLotFilters = {}) {
  return useQuery({
    queryKey: ['material-lots', opts],
    queryFn: () => lots.listMaterialLots(opts),
  });
}

// ── REST-only hooks ──────────────────────────────────────────────────────────

/** REST: detail-by-id — includes relations (material, source, inspection,
 *  sublots, createdBy) not in the list response (use the detail endpoint). */
export function useMaterialLot(id: number | undefined) {
  return useQuery({
    queryKey: ['material-lot', id],
    queryFn: () => lots.getMaterialLot(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

/**
 * REST: forward genealogy — computed/aggregate endpoint with no matching
 * shape. Lists every BatchStep that consumed quantity from this lot.
 */
export function useLotForwardGenealogy(id: number | undefined) {
  return useQuery({
    queryKey: ['material-lot', id, 'forward-genealogy'],
    queryFn: () => lots.getLotForwardGenealogy(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

/**
 * REST: backward genealogy — computed/aggregate endpoint with no matching
 * shape. Returns inspection, supplier refs, and upstream consumptions.
 */
export function useLotBackwardGenealogy(id: number | undefined) {
  return useQuery({
    queryKey: ['material-lot', id, 'backward-genealogy'],
    queryFn: () => lots.getLotBackwardGenealogy(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

// ── Mutations (always REST) ──────────────────────────────────────────────────

const inv = (qc: ReturnType<typeof useQueryClient>, key: string) =>
  qc.invalidateQueries({ queryKey: [key] });

export function useConsumeMaterialLot() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; payload: lots.ConsumeLotPayload }) =>
      lots.consumeMaterialLot(vars.id, vars.payload),
    onSuccess: (_data, vars) => {
      inv(qc, 'material-lots');
      qc.invalidateQueries({ queryKey: ['material-lot', vars.id] });
    },
  });
}
