import { api } from './client';
import type { ApiEnvelope, ApiPaginated, WorkOrderStatus } from '@/types/api';

export interface WorkOrderEan {
  id: number;
  work_order_id: number;
  ean: string;
  work_order?: {
    id: number;
    order_no: string;
    product_type?: { id: number; name: string };
  };
}

export interface PackagingScanResponse {
  work_order: {
    id: number;
    order_no: string;
    product: string;
    planned_qty: number;
    packed_qty: number;
    status: WorkOrderStatus;
  };
  scan: {
    id: number;
    ean: string;
    scanned_at: string;
  };
}

export interface PackagingItem {
  id: number;
  order_no: string;
  product: string;
  line?: string | null;
  planned_qty: number;
  packed_qty: number;
  progress: number;
  done: boolean;
  eans: string[];
  status: WorkOrderStatus;
}

export interface PackagingStats {
  today_packed: number;
  plan: number;
  total_packed: number;
  backlog: number;
  shift_start: string;
}

export interface PackagingScanLog {
  id: number;
  user_id?: number | null;
  work_order_id: number;
  ean: string;
  product_name?: string | null;
  scanned_at: string;
  user?: { id: number; username: string } | null;
  work_order?: { id: number; order_no: string } | null;
}

// ── EANs ────────────────────────────────────────────────────────────────────

export interface EanFilters {
  work_order_id?: number;
  q?: string;
  page?: number;
  per_page?: number;
}

export const listEans = (
  filters: EanFilters = {},
): Promise<{ data: WorkOrderEan[]; meta?: ApiPaginated<WorkOrderEan>['meta'] }> =>
  api
    .get<ApiPaginated<WorkOrderEan>>('/api/v1/packaging/eans', { params: filters })
    .then((r) => ({ data: r.data.data, meta: r.data.meta }));

export const createEan = (payload: {
  work_order_id: number;
  ean: string;
}): Promise<WorkOrderEan> =>
  api
    .post<ApiEnvelope<WorkOrderEan>>('/api/v1/packaging/eans', payload)
    .then((r) => r.data.data);

export const deleteEan = (id: number): Promise<void> =>
  api.delete(`/api/v1/packaging/eans/${id}`).then(() => undefined);

// ── Scan ────────────────────────────────────────────────────────────────────

export const scanEan = (ean: string): Promise<PackagingScanResponse> =>
  api
    .post<ApiEnvelope<PackagingScanResponse>>('/api/v1/packaging/scan', { ean })
    .then((r) => r.data.data);

// ── Items / Stats / Logs ────────────────────────────────────────────────────

export const listPackagingItems = (): Promise<PackagingItem[]> =>
  api.get<ApiEnvelope<PackagingItem[]>>('/api/v1/packaging/items').then((r) => r.data.data);

export const getPackagingStats = (): Promise<PackagingStats> =>
  api.get<ApiEnvelope<PackagingStats>>('/api/v1/packaging/stats').then((r) => r.data.data);

export interface ScanLogFilters {
  work_order_id?: number;
  user_id?: number;
  from?: string;
  to?: string;
  per_page?: number;
}

export const listScanLogs = (
  filters: ScanLogFilters = {},
): Promise<{ data: PackagingScanLog[]; meta?: ApiPaginated<PackagingScanLog>['meta'] }> =>
  api
    .get<ApiPaginated<PackagingScanLog>>('/api/v1/packaging/scan-logs', { params: filters })
    .then((r) => ({ data: r.data.data, meta: r.data.meta }));

// ── Pallets (full CRUD) ────────────────────────────────────────────────────

export type PalletStatus = 'open' | 'closed' | 'shipped';
export type PalletQuality = 'pending' | 'pass' | 'fail';

export interface Pallet {
  id: number;
  pallet_no: string;
  work_order_id: number | null;
  order_no: string | null;
  batch_id?: number | null;
  qty: number;
  status: PalletStatus;
  quality_status: PalletQuality;
  location: string | null;
  erp_reference: string | null;
  shipped_at: string | null;
}

export interface PalletInput {
  work_order_id: number;
  batch_id?: number | null;
  qty?: number | null;
  status: PalletStatus;
  location?: string | null;
  erp_reference?: string | null;
}

export interface PalletMeta {
  statuses: { value: string; label: string }[];
  work_orders: { id: number; order_no: string }[];
}

export const listPallets = (status?: PalletStatus): Promise<Pallet[]> =>
  api
    .get<ApiEnvelope<Pallet[]>>('/api/v1/pallets', { params: status ? { status } : {} })
    .then((r) => r.data.data);

export const getPalletMeta = (): Promise<PalletMeta> =>
  api.get<ApiEnvelope<PalletMeta>>('/api/v1/pallets/meta').then((r) => r.data.data);

export const createPallet = (input: PalletInput): Promise<Pallet> =>
  api.post<ApiEnvelope<Pallet>>('/api/v1/pallets', input).then((r) => r.data.data);

export const updatePallet = (id: number, input: PalletInput): Promise<Pallet> =>
  api.patch<ApiEnvelope<Pallet>>(`/api/v1/pallets/${id}`, input).then((r) => r.data.data);

export const deletePallet = (id: number): Promise<void> =>
  api.delete(`/api/v1/pallets/${id}`).then(() => undefined);

// ── Label templates (read-only; the layout builder stays on web admin) ─────

export interface LabelTemplate {
  id: number;
  name: string;
  type: string;
  type_label: string;
  size: string;
  barcode_format: string;
  fields_config?: Record<string, boolean>;
  fields_count: number;
  is_default: boolean;
  is_active: boolean;
}

export interface LabelTemplateInput {
  name: string;
  type: string;
  size: string;
  barcode_format: string;
  fields_config?: Record<string, boolean>;
  is_default?: boolean;
  is_active?: boolean;
}

export interface LabelTemplateMeta {
  types: { value: string; label: string }[];
  sizes: { value: string; label: string }[];
  barcode_formats: { value: string; label: string }[];
  fields: { value: string; label: string }[];
}

export const listLabelTemplates = (): Promise<LabelTemplate[]> =>
  api.get<ApiEnvelope<LabelTemplate[]>>('/api/v1/label-templates').then((r) => r.data.data);

export const getLabelTemplateMeta = (): Promise<LabelTemplateMeta> =>
  api.get<ApiEnvelope<LabelTemplateMeta>>('/api/v1/label-templates/meta').then((r) => r.data.data);

export const createLabelTemplate = (input: LabelTemplateInput): Promise<LabelTemplate> =>
  api.post<ApiEnvelope<LabelTemplate>>('/api/v1/label-templates', input).then((r) => r.data.data);

export const updateLabelTemplate = (id: number, input: LabelTemplateInput): Promise<LabelTemplate> =>
  api.patch<ApiEnvelope<LabelTemplate>>(`/api/v1/label-templates/${id}`, input).then((r) => r.data.data);

export const setLabelTemplateDefault = (id: number): Promise<LabelTemplate> =>
  api.post<ApiEnvelope<LabelTemplate>>(`/api/v1/label-templates/${id}/set-default`).then((r) => r.data.data);

export const deleteLabelTemplate = (id: number): Promise<void> =>
  api.delete(`/api/v1/label-templates/${id}`).then(() => undefined);
