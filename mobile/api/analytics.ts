import { api } from './client';
import type { AnalyticsOverview, ApiEnvelope } from '@/types/api';

export const analyticsOverview = (lineId?: number): Promise<AnalyticsOverview> =>
  api
    .get<ApiEnvelope<AnalyticsOverview>>('/api/v1/analytics/overview', {
      params: { line_id: lineId },
    })
    .then((r) => r.data.data);

export const productionByLine = (): Promise<Array<Record<string, unknown>>> =>
  api
    .get<ApiEnvelope<Array<Record<string, unknown>>>>('/api/v1/analytics/production-by-line')
    .then((r) => r.data.data);

export const cycleTime = (days?: number): Promise<Record<string, unknown>> =>
  api
    .get<ApiEnvelope<Record<string, unknown>>>('/api/v1/analytics/cycle-time', { params: { days } })
    .then((r) => r.data.data);

export const throughput = (days?: number): Promise<Record<string, unknown>> =>
  api
    .get<ApiEnvelope<Record<string, unknown>>>('/api/v1/analytics/throughput', { params: { days } })
    .then((r) => r.data.data);

export const issueStats = (days?: number): Promise<Record<string, unknown>> =>
  api
    .get<ApiEnvelope<Record<string, unknown>>>('/api/v1/analytics/issue-stats', {
      params: { days },
    })
    .then((r) => r.data.data);

export const stepPerformance = (
  opts: { line_id?: number; days?: number } = {},
): Promise<Array<Record<string, unknown>>> =>
  api
    .get<ApiEnvelope<Array<Record<string, unknown>>>>('/api/v1/analytics/step-performance', {
      params: opts,
    })
    .then((r) => r.data.data);
