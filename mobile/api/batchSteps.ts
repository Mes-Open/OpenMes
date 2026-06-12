import { api } from './client';
import type { ApiEnvelope, BatchStep } from '@/types/api';

export const startStep = (id: number): Promise<BatchStep> =>
  api.post<ApiEnvelope<BatchStep>>(`/api/v1/batch-steps/${id}/start`).then((r) => r.data.data);

export const completeStep = (
  id: number,
  payload: { produced_qty?: number; notes?: string } = {},
): Promise<BatchStep> =>
  api
    .post<ApiEnvelope<BatchStep>>(`/api/v1/batch-steps/${id}/complete`, payload)
    .then((r) => r.data.data);

export const reportStepProblem = (
  id: number,
  payload: { issue_type_id: number; description?: string },
): Promise<BatchStep> =>
  api
    .post<ApiEnvelope<BatchStep>>(`/api/v1/batch-steps/${id}/problem`, payload)
    .then((r) => r.data.data);
