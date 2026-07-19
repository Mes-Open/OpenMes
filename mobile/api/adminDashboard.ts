import { api } from './client';
import type { ApiEnvelope } from '@/types/api';

export interface DashStats {
  total_work_orders: number;
  pending: number;
  in_progress: number;
  blocked: number;
  active_today: number;
  open_issues: number;
  blocking_issues: number;
  active_lines: number;
}

export interface DashOee {
  line_id: number;
  line_name: string;
  oee_pct: number | null;
  availability_pct: number | null;
  performance_pct: number | null;
  quality_pct: number | null;
}

export interface DashInboundQc {
  pending: number;
  completed_30d: number;
  failed_30d: number;
  conditional_30d: number;
  pass_rate_30d: number | null;
}

export interface DashMaterials {
  low_stock_count: number;
  expiring_count: number;
  reserved_total: number;
  lots_total: number;
  quarantined_count: number;
}

export interface DashScrap {
  total_qty_30d: number | string;
  entries_30d: number;
  top_reason: string | null;
  top_reason_qty: number | null;
}

export interface DashNcType {
  name: string;
  count: number;
}

export interface DashNonConformance {
  open_total: number;
  open_by_type: DashNcType[];
  disposition_summary: Record<string, number>;
  overdue_actions: number;
}

export interface DashRecentWo {
  id: number;
  order_no: string;
  line_name: string | null;
  status: string;
  produced_qty: number;
  planned_qty: number;
}

export interface DashIssue {
  id: number;
  title: string;
  type_name: string | null;
  is_blocking: boolean;
  work_order_id: number | null;
  status: string;
}

export interface DashLine {
  id: number;
  name: string;
}

export interface AdminDashboard {
  stats: DashStats;
  oee: DashOee[];
  inbound_qc: DashInboundQc;
  materials: DashMaterials;
  scrap: DashScrap;
  non_conformance: DashNonConformance;
  recent_work_orders: DashRecentWo[];
  open_issues: DashIssue[];
  lines: DashLine[];
}

export const getAdminDashboard = (lineId?: string): Promise<AdminDashboard> =>
  api
    .get<ApiEnvelope<AdminDashboard>>('/api/v1/admin/dashboard', {
      params: lineId ? { line_id: lineId } : {},
    })
    .then((r) => r.data.data);
