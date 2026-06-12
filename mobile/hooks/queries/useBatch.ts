import { useQuery } from '@tanstack/react-query';

import { getBatch, getBatchAllocationPreview, listBatchesForWorkOrder } from '@/api/batches';

export function useBatch(id: number | undefined) {
  return useQuery({
    queryKey: ['batch', id],
    queryFn: () => getBatch(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
    refetchInterval: 5000,
  });
}

export function useBatches(workOrderId: number | undefined) {
  return useQuery({
    queryKey: ['batches', workOrderId],
    queryFn: () => listBatchesForWorkOrder(workOrderId as number),
    enabled: typeof workOrderId === 'number' && Number.isFinite(workOrderId),
  });
}

// Fetched lazily when the operator is about to start the first step on a
// PENDING batch — see MaterialAllocationModal.
export function useBatchAllocationPreview(id: number | undefined, enabled: boolean) {
  return useQuery({
    queryKey: ['batch-allocation-preview', id],
    queryFn: () => getBatchAllocationPreview(id as number),
    enabled: enabled && typeof id === 'number' && Number.isFinite(id),
    staleTime: 0,
  });
}
