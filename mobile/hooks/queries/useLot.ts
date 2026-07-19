import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  createLotSequence,
  deleteLotSequence,
  getLotSequence,
  listLotSequences,
  previewNextLot,
  updateLotSequence,
  type LotSequence,
  type LotSequenceInput,
} from '@/api/lot';

/**
 * Lot sequences via REST `listLotSequences`.
 * Returns the product_type relation and the REST-shaped next_value field.
 */
export function useLotSequences() {
  return useQuery({
    queryKey: ['lot-sequences'],
    queryFn: () => listLotSequences(),
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
