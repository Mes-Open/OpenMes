import { useMutation, useQueryClient } from '@tanstack/react-query';

import {
  cancelBatch,
  deleteBatch,
  releaseBatch,
  updateBatch,
  type BatchReleaseType,
} from '@/api/batches';

function invalidateBatchScope(qc: ReturnType<typeof useQueryClient>, batchId?: number, workOrderId?: number) {
  if (batchId !== undefined) qc.invalidateQueries({ queryKey: ['batch', batchId] });
  if (workOrderId !== undefined) {
    qc.invalidateQueries({ queryKey: ['batches', workOrderId] });
    qc.invalidateQueries({ queryKey: ['work-order', workOrderId] });
  }
  qc.invalidateQueries({ queryKey: ['work-orders'] });
}

export function useUpdateBatch(batchId: number, workOrderId?: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (target_qty: number) => updateBatch(batchId, { target_qty }),
    onSuccess: () => invalidateBatchScope(qc, batchId, workOrderId),
  });
}

export function useCancelBatch(batchId: number, workOrderId?: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => cancelBatch(batchId),
    onSuccess: () => invalidateBatchScope(qc, batchId, workOrderId),
  });
}

export function useReleaseBatch(batchId: number, workOrderId?: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (release_type: BatchReleaseType) => releaseBatch(batchId, release_type),
    onSuccess: () => invalidateBatchScope(qc, batchId, workOrderId),
  });
}

export function useDeleteBatch(batchId: number, workOrderId?: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => deleteBatch(batchId),
    onSuccess: () => invalidateBatchScope(qc, batchId, workOrderId),
  });
}
