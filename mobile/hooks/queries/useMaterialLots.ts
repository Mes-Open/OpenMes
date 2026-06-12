import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import * as lots from '@/api/materialLots';
import type { ApiPaginated } from '@/types/api';
import { useElectricShape, type Row } from '@/hooks/useElectricShape';

/**
 * Live material lots via Electric (migrated from REST `listMaterialLots`).
 * Filters are applied client-side over the synced row set.
 *
 * Fidelity note: the `material_lots` shape carries raw table columns only —
 * relation fields (material, source, inspection, sublots, createdBy) are NOT
 * present; consumers that need those must call `useMaterialLot(id)` (REST).
 * `inspection_id`, `created_by_id`, and `tenant_id` are also not in the
 * shape columns so they are absent from Electric rows.
 *
 * `expiring_within_days` is evaluated client-side by comparing `expiry_date`
 * to today's date (same logic the server uses). `_count` absent (no relation
 * counts in this shape).
 */
export function useMaterialLots(opts: lots.MaterialLotFilters = {}) {
  const {
    material_id,
    status,
    supplier_lot_no,
    available_only,
    expiring_within_days,
  } = opts;

  // Preserve the REST `ApiPaginated<MaterialLot>` envelope ({ data, meta }) so
  // consuming screens that read `.data.data` / `.data.meta` are unchanged.
  // `meta` is undefined — Electric streams the full set, not server pages.
  return useElectricShape<Row, ApiPaginated<lots.MaterialLot>>('material_lots', {
    select: (rows): ApiPaginated<lots.MaterialLot> => {
      let out = rows as unknown as lots.MaterialLot[];

      if (material_id !== undefined) {
        out = out.filter((r) => r.material_id === material_id);
      }
      if (status !== undefined) {
        out = out.filter((r) => r.status === status);
      }
      if (available_only) {
        out = out.filter((r) => r.status === 'available');
      }
      if (supplier_lot_no !== undefined) {
        const q = supplier_lot_no.toLowerCase();
        out = out.filter((r) => (r.supplier_lot_no ?? '').toLowerCase().includes(q));
      }
      if (expiring_within_days !== undefined) {
        const cutoff = new Date();
        cutoff.setDate(cutoff.getDate() + expiring_within_days);
        const cutoffMs = cutoff.getTime();
        out = out.filter((r) => {
          if (!r.expiry_date) return false;
          return new Date(r.expiry_date as string).getTime() <= cutoffMs;
        });
      }

      // Sort: most recently received first (mirrors default server ordering).
      const sorted = [...out].sort(
        (a, b) =>
          new Date(b.received_at as string).getTime() -
          new Date(a.received_at as string).getTime(),
      );
      return { data: sorted, meta: undefined };
    },
  });
}

// ── REST-only hooks ──────────────────────────────────────────────────────────

/** REST: detail-by-id — includes relations (material, source, inspection,
 *  sublots, createdBy) not available in the Electric shape. */
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
