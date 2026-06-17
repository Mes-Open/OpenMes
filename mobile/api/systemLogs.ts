// System logs — app log tail, failed jobs (retry), deployments. Admin-only.
// Backend at /api/v1/system/logs/*. Mirrors Web/Admin/SystemLogController.

import { api } from './client';

export type LogLevel = 'debug' | 'info' | 'notice' | 'warning' | 'error' | 'critical' | 'alert' | 'emergency';

export interface SystemLogEntry {
  timestamp: string;
  environment: string;
  level: LogLevel | string;
  message: string;
  /** Continuation lines: stack trace, JSON payload, etc. Empty when none. */
  context: string;
}

export interface FailedJob {
  id: number;
  uuid: string;
  connection: string;
  queue: string;
  payload: string;
  exception: string;
  failed_at: string;
}

export interface DeploymentRecord {
  id: number;
  from_version?: string | null;
  to_version?: string | null;
  status: string;
  started_at: string;
  finished_at?: string | null;
  error?: string | null;
}

interface TailFilters {
  level?: LogLevel;
  search?: string;
  /** ISO date (YYYY-MM-DD). Defaults to today on the server. */
  date?: string;
  /** Max entries to return — 10..500. Server clamps. */
  limit?: number;
}

interface TailResponse {
  data: SystemLogEntry[];
  meta: { date: string; count: number };
}

export const tailLogs = (filters: TailFilters = {}): Promise<TailResponse> =>
  api
    .get<TailResponse>('/api/v1/system/logs/tail', { params: filters })
    .then((r) => r.data);

interface FailedJobsResponse {
  data: FailedJob[];
  meta: {
    current_page?: number;
    per_page?: number;
    total: number;
    last_page?: number;
    /** True when the failed_jobs table isn't migrated on this install. */
    missing?: boolean;
  };
}

export const listFailedJobs = (
  filters: { per_page?: number; page?: number } = {},
): Promise<FailedJobsResponse> =>
  api
    .get<FailedJobsResponse>('/api/v1/system/logs/failed-jobs', { params: filters })
    .then((r) => r.data);

export const retryFailedJob = (uuid: string): Promise<{ message: string }> =>
  api
    .post<{ message: string }>(`/api/v1/system/logs/failed-jobs/${uuid}/retry`)
    .then((r) => r.data);

interface DeploymentsResponse {
  data: DeploymentRecord[];
  meta: {
    current_page?: number;
    per_page?: number;
    total: number;
    last_page?: number;
    missing?: boolean;
  };
}

export const listDeployments = (
  filters: { per_page?: number; page?: number } = {},
): Promise<DeploymentsResponse> =>
  api
    .get<DeploymentsResponse>('/api/v1/system/logs/deployments', { params: filters })
    .then((r) => r.data);

