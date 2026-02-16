import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { workOrdersApi, batchesApi } from '../api/workOrders';

export const useWorkOrders = (filters?: { status?: string; line_id?: number }) => {
  return useQuery({
    queryKey: ['workOrders', filters],
    queryFn: () => workOrdersApi.getAll(filters),
    refetchInterval: 30000, // Refetch every 30 seconds for real-time updates
  });
};

export const useWorkOrder = (id: number) => {
  return useQuery({
    queryKey: ['workOrders', id],
    queryFn: () => workOrdersApi.getById(id),
    enabled: !!id,
  });
};

export const useBatches = (workOrderId: number) => {
  return useQuery({
    queryKey: ['workOrders', workOrderId, 'batches'],
    queryFn: () => workOrdersApi.getBatches(workOrderId),
    enabled: !!workOrderId,
  });
};

export const useCreateBatch = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ workOrderId, targetQty }: { workOrderId: number; targetQty: number }) =>
      workOrdersApi.createBatch(workOrderId, targetQty),
    onSuccess: (_data, variables) => {
      // Invalidate work order and batches queries
      queryClient.invalidateQueries({ queryKey: ['workOrders', variables.workOrderId] });
      queryClient.invalidateQueries({ queryKey: ['workOrders', variables.workOrderId, 'batches'] });
    },
  });
};

export const useBatchSteps = (batchId: number) => {
  return useQuery({
    queryKey: ['batches', batchId, 'steps'],
    queryFn: () => batchesApi.getSteps(batchId),
    enabled: !!batchId,
    refetchInterval: 10000, // Refetch every 10 seconds
  });
};
