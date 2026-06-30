import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  createLotSequence,
  deleteLotSequence,
  getLotSequence,
  previewNextLot,
  updateLotSequence,
  type LotSequence,
  type LotSequenceInput,
} from '@/api/lot';
import { useElectricShape, type Row } from '@/hooks/useElectricShape';

/**
 * Live lot sequences via Electric (migrated from REST `listLotSequences`).
 *
 * Fidelity note: the `lot_sequences` shape carries raw table columns only —
 * the `product_type` relation object is NOT present. Use `useLotSequence(id)`
 * (REST) when the nested product type is required.
 *
 * Column name difference: the DB / shape exposes `next_number`; the REST
 * endpoint surfaces it as `next_value` on the `LotSequence` type. The
 * Electric rows use `next_number`, so consumers reading `r.next_value` will
 * see `undefined` — they should switch to `r.next_number`. The cast keeps the
 * return type as `LotSequence[]` for interface compatibility; screens that
 * only render name / prefix are unaffected.
 */
export function useLotSequences() {
  return useElectricShape<Row, LotSequence[]>('lot_sequences', {
    select: (rows) => {
      const out = rows as unknown as LotSequence[];
      return [...out].sort((a, b) => a.name.localeCompare(b.name));
    },
  });
}

/** REST: detail-by-id — includes the product_type relation and the
 *  REST-shaped next_value field. */
export function useLotSequence(id: number | undefined) {
  return useQuery({
    queryKey: ['lot-sequence', id],
    queryFn: () => getLotSequence(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

/** REST: computed/generated lot preview — no shape for this endpoint. */
export function useLotPreview(productTypeId: number | undefined, enabled = true) {
  return useQuery({
    queryKey: ['lot-preview', productTypeId ?? 'default'],
    queryFn: () => previewNextLot(productTypeId),
    enabled,
  });
}

// ── Mutations (always REST) ──────────────────────────────────────────────────

export function useCreateLotSequence() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: LotSequenceInput) => createLotSequence(input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['lot-sequences'] }),
  });
}

export function useUpdateLotSequence(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: Partial<LotSequenceInput>) => updateLotSequence(id, input),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['lot-sequences'] });
      qc.invalidateQueries({ queryKey: ['lot-sequence', id] });
    },
  });
}

export function useDeleteLotSequence() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteLotSequence(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['lot-sequences'] }),
  });
}
