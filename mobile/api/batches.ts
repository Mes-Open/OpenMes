import { api } from './client';
import type { ApiEnvelope, Batch } from '@/types/api';

export const listBatchesForWorkOrder = (workOrderId: number): Promise<Batch[]> =>
  api
    .get<ApiEnvelope<Batch[]>>(`/api/v1/work-orders/${workOrderId}/batches`)
    .then((r) => r.data.data);

export const getBatch = (id: number): Promise<Batch> =>
  api.get<ApiEnvelope<Batch>>(`/api/v1/batches/${id}`).then((r) => r.data.data);

export interface CreateBatchInput {
  target_qty: number;
  workstation_id?: number | null;
  lot_number?: string | null;
}

export const createBatch = (
  workOrderId: number,
  input: CreateBatchInput | number,
): Promise<Batch> => {
  const body = typeof input === 'number' ? { target_qty: input } : input;
  return api
    .post<ApiEnvelope<Batch>>(`/api/v1/work-orders/${workOrderId}/batches`, body)
    .then((r) => r.data.data);
};

export const updateBatch = (id: number, input: { target_qty: number }): Promise<Batch> =>
  api.patch<ApiEnvelope<Batch>>(`/api/v1/batches/${id}`, input).then((r) => r.data.data);

export const cancelBatch = (id: number): Promise<Batch> =>
  api.post<ApiEnvelope<Batch>>(`/api/v1/batches/${id}/cancel`).then((r) => r.data.data);

export type BatchReleaseType = 'for_production' | 'for_sale';

export const releaseBatch = (id: number, release_type: BatchReleaseType): Promise<Batch> =>
  api
    .post<ApiEnvelope<Batch>>(`/api/v1/batches/${id}/release`, { release_type })
    .then((r) => r.data.data);

export const deleteBatch = (id: number): Promise<void> =>
  api.delete(`/api/v1/batches/${id}`).then(() => undefined);

// ── Material allocation preview (before starting first step) ─────────────

export interface MaterialAllocationLine {
  material_name: string;
  material_code: string;
  unit_of_measure: string;
  required_qty: number;
  available_qty: number;
  sufficient: boolean;
  material_exists: boolean;
}

export interface BatchAllocationPreview {
  data: MaterialAllocationLine[];
  all_sufficient: boolean;
  batch_status: string;
}

export const getBatchAllocationPreview = (
  batchId: number,
): Promise<BatchAllocationPreview> =>
  api
    .get<BatchAllocationPreview>(`/api/v1/batches/${batchId}/allocation-preview`)
    .then((r) => r.data);
