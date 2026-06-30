import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import * as woe from '@/api/woExtras';

const inv = (qc: ReturnType<typeof useQueryClient>, key: string) => qc.invalidateQueries({ queryKey: [key] });

// Production anomalies
export function useProductionAnomalies(filters: woe.AnomalyFilters = {}) {
  return useQuery({ queryKey: ['production-anomalies', filters], queryFn: () => woe.listProductionAnomalies(filters) });
}

export function useProductionAnomaly(id: number | undefined) {
  return useQuery({
    queryKey: ['production-anomaly', id],
    queryFn: () => woe.getProductionAnomaly(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}

export function useCreateProductionAnomaly() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { workOrderId: number; payload: woe.CreateProductionAnomalyPayload }) =>
      woe.createProductionAnomaly(vars.workOrderId, vars.payload),
    onSuccess: () => inv(qc, 'production-anomalies'),
  });
}

export function useDeleteProductionAnomaly() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: woe.deleteProductionAnomaly,
    onSuccess: () => inv(qc, 'production-anomalies'),
  });
}

export function useProcessProductionAnomaly() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: woe.processProductionAnomaly,
    onSuccess: () => inv(qc, 'production-anomalies'),
  });
}

// Additional costs
export function useAdditionalCosts(workOrderId: number | undefined) {
  return useQuery({
    queryKey: ['work-order', workOrderId, 'additional-costs'],
    queryFn: () => woe.listAdditionalCosts(workOrderId as number),
    enabled: typeof workOrderId === 'number' && Number.isFinite(workOrderId),
  });
}

export function useCreateAdditionalCost() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { workOrderId: number; payload: woe.CreateAdditionalCostPayload }) =>
      woe.createAdditionalCost(vars.workOrderId, vars.payload),
    onSuccess: (_d, vars) => qc.invalidateQueries({ queryKey: ['work-order', vars.workOrderId, 'additional-costs'] }),
  });
}

export function useDeleteAdditionalCost() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: woe.deleteAdditionalCost,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['work-order'] }),
  });
}

// Attachments
export function useAttachments(entityType: string, entityId: number | undefined) {
  return useQuery({
    queryKey: ['attachments', entityType, entityId],
    queryFn: () => woe.listAttachments(entityType, entityId as number),
    enabled: typeof entityId === 'number' && Number.isFinite(entityId),
  });
}

export function useUploadAttachment() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: woe.uploadAttachment,
    onSuccess: (_d, vars) => qc.invalidateQueries({ queryKey: ['attachments', vars.entityType, vars.entityId] }),
  });
}

export function useDeleteAttachment() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: woe.deleteAttachment,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['attachments'] }),
  });
}
