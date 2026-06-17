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
