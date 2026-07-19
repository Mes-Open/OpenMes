import { api } from './client';
import type { ApiEnvelope } from '@/types/api';

export interface AlertIssue {
  id: number;
  title: string | null;
  description: string | null;
  status: string;
  type_name: string | null;
  is_blocking: boolean;
  order: { id: number; order_no: string } | null;
  reporter_name: string | null;
  created_at: string | null;
}

export interface AlertOverdue {
  id: number;
  order_no: string;
  line_name: string | null;
  due_date: string | null;
  status: string;
}

export interface AlertBlocked {
  id: number;
  order_no: string;
  line_name: string | null;
  updated_at: string | null;
}

export interface AlertsData {
  blocking_issues: AlertIssue[];
  non_blocking_issues: AlertIssue[];
  overdue_orders: AlertOverdue[];
  blocked_orders: AlertBlocked[];
  total: number;
}

export const getAlerts = (): Promise<AlertsData> =>
  api.get<ApiEnvelope<AlertsData>>('/api/v1/alerts').then((r) => r.data.data);
