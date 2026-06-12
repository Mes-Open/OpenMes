import { api } from './client';
import { useSettingsStore } from '@/stores/settingsStore';
import type { ApiEnvelope, ApiPaginated } from '@/types/api';

export type ProductionAnomalyStatus = 'draft' | 'processed';

export interface ProductionAnomaly {
  id: number;
  work_order_id: number;
  batch_id?: number | null;
  batch_step_id?: number | null;
  anomaly_reason_id: number;
  anomaly_reason?: { id: number; code: string; name: string };
  created_by_id?: number | null;
  created_by?: { id: number; username: string; name?: string | null } | null;
  product_name: string;
  planned_qty: string | number;
  actual_qty: string | number;
  deviation_pct?: string | number | null;
  status: ProductionAnomalyStatus;
  comment?: string | null;
  created_at?: string;
}

export interface AdditionalCost {
  id: number;
  work_order_id: number;
  cost_source_id?: number | null;
  cost_source?: { id: number; name: string; code: string } | null;
  created_by_id?: number | null;
  created_by?: { id: number; username: string } | null;
  description: string;
  amount: string | number;
  currency?: string | null;
  created_at?: string;
}

export interface Attachment {
  id: number;
  entity_type: string;
  entity_id: number;
  original_name: string;
  storage_path: string;
  mime_type?: string | null;
  file_size?: number | null;
  uploaded_by_id?: number | null;
  uploaded_by?: { id: number; username: string } | null;
  created_at?: string;
}

// ── Production Anomalies ──────────────────────────────────────────────────

export interface AnomalyFilters {
  work_order_id?: number;
  line_id?: number;
  status?: ProductionAnomalyStatus;
  from?: string;
  to?: string;
  page?: number;
  per_page?: number;
}

export const listProductionAnomalies = (
  filters: AnomalyFilters = {},
): Promise<{ data: ProductionAnomaly[]; meta?: ApiPaginated<ProductionAnomaly>['meta'] }> =>
  api
    .get<ApiPaginated<ProductionAnomaly>>('/api/v1/production-anomalies', { params: filters })
    .then((r) => ({ data: r.data.data, meta: r.data.meta }));

export const getProductionAnomaly = (id: number): Promise<ProductionAnomaly> =>
  api
    .get<ApiEnvelope<ProductionAnomaly>>(`/api/v1/production-anomalies/${id}`)
    .then((r) => r.data.data);

export interface CreateProductionAnomalyPayload {
  anomaly_reason_id: number;
  planned_qty: number;
  actual_qty: number;
  batch_id?: number;
  batch_step_id?: number;
  product_name?: string;
  comment?: string;
}

export const createProductionAnomaly = (
  workOrderId: number,
  payload: CreateProductionAnomalyPayload,
): Promise<ProductionAnomaly> =>
  api
    .post<ApiEnvelope<ProductionAnomaly>>(
      `/api/v1/work-orders/${workOrderId}/production-anomalies`,
      payload,
    )
    .then((r) => r.data.data);

export const updateProductionAnomaly = (
  id: number,
  payload: Partial<CreateProductionAnomalyPayload>,
): Promise<ProductionAnomaly> =>
  api
    .patch<ApiEnvelope<ProductionAnomaly>>(`/api/v1/production-anomalies/${id}`, payload)
    .then((r) => r.data.data);

export const deleteProductionAnomaly = (id: number): Promise<void> =>
  api.delete(`/api/v1/production-anomalies/${id}`).then(() => undefined);

export const processProductionAnomaly = (id: number): Promise<ProductionAnomaly> =>
  api
    .post<ApiEnvelope<ProductionAnomaly>>(`/api/v1/production-anomalies/${id}/process`)
    .then((r) => r.data.data);

// ── Additional Costs ──────────────────────────────────────────────────────

export const listAdditionalCosts = (workOrderId: number): Promise<AdditionalCost[]> =>
  api
    .get<ApiEnvelope<AdditionalCost[]>>(`/api/v1/work-orders/${workOrderId}/additional-costs`)
    .then((r) => r.data.data);

export interface CreateAdditionalCostPayload {
  description: string;
  amount: number;
  cost_source_id?: number;
  currency?: string;
}

export const createAdditionalCost = (
  workOrderId: number,
  payload: CreateAdditionalCostPayload,
): Promise<AdditionalCost> =>
  api
    .post<ApiEnvelope<AdditionalCost>>(
      `/api/v1/work-orders/${workOrderId}/additional-costs`,
      payload,
    )
    .then((r) => r.data.data);

export const updateAdditionalCost = (
  id: number,
  payload: Partial<CreateAdditionalCostPayload>,
): Promise<AdditionalCost> =>
  api
    .patch<ApiEnvelope<AdditionalCost>>(`/api/v1/additional-costs/${id}`, payload)
    .then((r) => r.data.data);

export const deleteAdditionalCost = (id: number): Promise<void> =>
  api.delete(`/api/v1/additional-costs/${id}`).then(() => undefined);

// ── Attachments ───────────────────────────────────────────────────────────

export const listAttachments = (entityType: string, entityId: number): Promise<Attachment[]> =>
  api
    .get<ApiEnvelope<Attachment[]>>('/api/v1/attachments', {
      params: { entity_type: entityType, entity_id: entityId },
    })
    .then((r) => r.data.data);

export const uploadAttachment = (opts: {
  entityType: string;
  entityId: number;
  uri: string;
  name: string;
  mimeType?: string;
}): Promise<Attachment> => {
  const form = new FormData();
  form.append('entity_type', opts.entityType);
  form.append('entity_id', String(opts.entityId));
  form.append('file', {
    uri: opts.uri,
    name: opts.name,
    type: opts.mimeType ?? 'application/octet-stream',
  } as unknown as Blob);

  return api
    .post<ApiEnvelope<Attachment>>('/api/v1/attachments', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    .then((r) => r.data.data);
};

export const deleteAttachment = (id: number): Promise<void> =>
  api.delete(`/api/v1/attachments/${id}`).then(() => undefined);

export function attachmentDownloadUrl(id: number): string {
  const baseUrl = useSettingsStore.getState().serverUrl;
  return `${baseUrl}/api/v1/attachments/${id}/download`;
}
