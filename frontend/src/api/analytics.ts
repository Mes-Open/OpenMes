import { apiClient } from './client';

export interface OverviewStats {
  total_work_orders: number;
  active_work_orders: number;
  completed_work_orders: number;
  blocked_work_orders: number;
  total_batches: number;
  active_batches: number;
  open_issues: number;
  critical_issues: number;
}

export interface LineProductionMetric {
  line_id: number;
  line_name: string;
  line_code: string;
  total_work_orders: number;
  completed: number;
  in_progress: number;
  pending: number;
  blocked: number;
  total_planned_qty: number;
  total_produced_qty: number;
}

export interface CycleTimeBatch {
  batch_id: number;
  work_order_no: string;
  product_type: string;
  target_qty: number;
  produced_qty: number;
  cycle_time_minutes: number;
  cycle_time_hours: number;
  completed_at: string;
}

export interface CycleTimeData {
  batches: CycleTimeBatch[];
  average_cycle_time_minutes: number;
  average_cycle_time_hours: number;
  total_batches: number;
}

export interface DailyProduction {
  date: string;
  total_produced: number;
}

export interface ThroughputData {
  daily_production: DailyProduction[];
  average_daily_throughput: number;
  period_start: string;
  period_end: string;
}

export interface IssueTypeCount {
  type_name: string;
  count: number;
}

export interface IssueStatusCount {
  status: string;
  count: number;
}

export interface IssueStats {
  by_type: IssueTypeCount[];
  by_status: IssueStatusCount[];
  average_resolution_time_minutes: number;
  average_resolution_time_hours: number;
}

export interface StepPerformance {
  step_name: string;
  average_duration_minutes: number;
  total_completions: number;
}

export const analyticsApi = {
  getOverview: async (params?: { line_id?: number }) => {
    const response = await apiClient.get<{ data: OverviewStats }>('/v1/analytics/overview', {
      params,
    });
    return response.data.data;
  },

  getProductionByLine: async (params?: { start_date?: string; end_date?: string }) => {
    const response = await apiClient.get<{ data: LineProductionMetric[] }>(
      '/v1/analytics/production-by-line',
      { params }
    );
    return response.data.data;
  },

  getCycleTime: async (params?: { line_id?: number; days?: number }) => {
    const response = await apiClient.get<{ data: CycleTimeData }>('/v1/analytics/cycle-time', {
      params,
    });
    return response.data.data;
  },

  getThroughput: async (params?: { line_id?: number; days?: number }) => {
    const response = await apiClient.get<{ data: ThroughputData }>('/v1/analytics/throughput', {
      params,
    });
    return response.data.data;
  },

  getIssueStats: async (params?: { line_id?: number; days?: number }) => {
    const response = await apiClient.get<{ data: IssueStats }>('/v1/analytics/issue-stats', {
      params,
    });
    return response.data.data;
  },

  getStepPerformance: async (params?: { line_id?: number; days?: number }) => {
    const response = await apiClient.get<{ data: StepPerformance[] }>(
      '/v1/analytics/step-performance',
      { params }
    );
    return response.data.data;
  },
};
