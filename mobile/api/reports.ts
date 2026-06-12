import { api } from './client';
import { useSettingsStore } from '@/stores/settingsStore';
import type { ApiEnvelope } from '@/types/api';

export type ReportType = 'production_summary' | 'batch_completion' | 'downtime';

export interface ReportFilters {
  start_date: string;
  end_date: string;
  line_id?: number;
}

// ── Production summary ────────────────────────────────────────────────────

export interface ProductionSummary {
  period: { start: string; end: string };
  line: string;
  work_orders: {
    total: number;
    completed: number;
    in_progress: number;
    pending: number;
    blocked: number;
    cancelled: number;
  };
  production: {
    total_planned: number;
    total_produced: number;
    completion_rate: number;
  };
  by_product_type: Array<{
    product_type: string;
    total_orders: number;
    planned_qty: number;
    produced_qty: number;
  }>;
  generated_at: string;
}

export const getProductionSummary = (filters: ReportFilters): Promise<ProductionSummary> =>
  api
    .get<ApiEnvelope<ProductionSummary>>('/api/v1/reports/production-summary', { params: filters })
    .then((r) => r.data.data);

// ── Batch completion ──────────────────────────────────────────────────────

export interface BatchCompletionReport {
  period: { start: string; end: string };
  summary: {
    total_batches: number;
    total_produced: number;
    average_batch_size: number;
  };
  batches: Array<{
    batch_id: number;
    batch_number?: string | null;
    work_order_no: string;
    product_type: string;
    line: string;
    target_qty: number;
    produced_qty: number;
    started_at?: string | null;
    completed_at?: string | null;
    cycle_time_minutes?: number | null;
    cycle_time_hours?: number | null;
  }>;
  generated_at: string;
}

export const getBatchCompletion = (filters: ReportFilters): Promise<BatchCompletionReport> =>
  api
    .get<ApiEnvelope<BatchCompletionReport>>('/api/v1/reports/batch-completion', {
      params: filters,
    })
    .then((r) => r.data.data);

// ── Downtime ──────────────────────────────────────────────────────────────

export interface DowntimeReport {
  period: { start: string; end: string };
  summary: {
    total_issues: number;
    open_issues: number;
    resolved_issues: number;
    closed_issues: number;
    total_downtime_minutes: number;
    total_downtime_hours: number;
    average_resolution_time_minutes: number;
  };
  by_type: Array<{
    type: string;
    count: number;
    downtime_minutes: number;
    downtime_hours: number;
  }>;
  issues: Array<{
    id: number;
    title?: string | null;
    type: string;
    status: string;
    work_order_no: string;
    reported_at?: string | null;
    resolved_at?: string | null;
    downtime_minutes?: number | null;
  }>;
  generated_at: string;
}

export const getDowntimeReport = (filters: ReportFilters): Promise<DowntimeReport> =>
  api
    .get<ApiEnvelope<DowntimeReport>>('/api/v1/reports/downtime', { params: filters })
    .then((r) => r.data.data);

// ── Export CSV (URL builder) ──────────────────────────────────────────────

export function reportExportCsvUrl(report_type: ReportType, filters: ReportFilters): string {
  const baseUrl = useSettingsStore.getState().serverUrl;
  const params: Record<string, string | number | undefined> = {
    report_type,
    start_date: filters.start_date,
    end_date: filters.end_date,
    line_id: filters.line_id,
  };
  const qs = Object.entries(params)
    .filter(([, v]) => v !== undefined && v !== null && v !== '')
    .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(String(v))}`)
    .join('&');
  return `${baseUrl}/api/v1/reports/export-csv?${qs}`;
}
