import { api } from './client';
import type { ApiEnvelope } from '@/types/api';

/** 5M defect taxonomy (Ishikawa) — values must match ScrapReason::CATEGORIES on the backend. */
export const SCRAP_CATEGORIES = ['material', 'machine', 'method', 'man', 'environment'] as const;
export type ScrapCategory = (typeof SCRAP_CATEGORIES)[number];

export interface ScrapReason {
  id: number;
  code: string;
  name: string;
  category?: ScrapCategory | string | null;
  description?: string | null;
  is_active?: boolean;
  sort_order?: number | null;
}

export interface ScrapReasonInput {
  code: string;
  name: string;
  category: ScrapCategory | string;
  description?: string | null;
  sort_order?: number | null;
  is_active?: boolean;
}

/** GET /api/v1/scrap-reasons — defect/scrap category dictionary. */
export const listScrapReasons = (includeInactive = false): Promise<ScrapReason[]> =>
  api
    .get<ApiEnvelope<ScrapReason[]>>('/api/v1/scrap-reasons', {
      params: { include_inactive: includeInactive ? 1 : undefined },
    })
    .then((r) => r.data.data);

export const getScrapReason = (id: number): Promise<ScrapReason> =>
  api.get<ApiEnvelope<ScrapReason>>(`/api/v1/scrap-reasons/${id}`).then((r) => r.data.data);

export const createScrapReason = (input: ScrapReasonInput): Promise<ScrapReason> =>
  api.post<ApiEnvelope<ScrapReason>>('/api/v1/scrap-reasons', input).then((r) => r.data.data);

export const updateScrapReason = (id: number, input: Partial<ScrapReasonInput>): Promise<ScrapReason> =>
  api.patch<ApiEnvelope<ScrapReason>>(`/api/v1/scrap-reasons/${id}`, input).then((r) => r.data.data);

export const deleteScrapReason = (id: number): Promise<void> =>
  api.delete(`/api/v1/scrap-reasons/${id}`).then(() => undefined);
