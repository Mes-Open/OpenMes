import { api } from './client';
import type { ApiEnvelope } from '@/types/api';

export interface Site {
  id: number;
  name: string;
  code: string;
  company_id: number;
  description?: string | null;
  address?: string | null;
  city?: string | null;
  country?: string | null;
  timezone?: string | null;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
  company?: { id: number; name: string; code?: string } | null;
  areas?: Area[];
  areas_count?: number;
  lines_count?: number;
}

export interface Area {
  id: number;
  name: string;
  code: string;
  site_id: number;
  description?: string | null;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
  site?: { id: number; name: string; code?: string } | null;
  lines?: Array<{ id: number; name: string; code?: string }>;
  lines_count?: number;
}

export interface SiteFilters {
  include_inactive?: boolean;
  company_id?: number;
}

export interface SiteInput {
  name: string;
  code: string;
  company_id?: number | null;
  description?: string | null;
  address?: string | null;
  city?: string | null;
  country?: string | null;
  timezone?: string | null;
  is_active?: boolean;
}

export const listSites = (opts: SiteFilters = {}): Promise<Site[]> =>
  api
    .get<ApiEnvelope<Site[]>>('/api/v1/sites', { params: opts })
    .then((r) => r.data.data);

export const getSite = (id: number): Promise<Site> =>
  api.get<ApiEnvelope<Site>>(`/api/v1/sites/${id}`).then((r) => r.data.data);

export const createSite = (input: SiteInput): Promise<Site> =>
  api.post<ApiEnvelope<Site>>('/api/v1/sites', input).then((r) => r.data.data);

export const updateSite = (id: number, input: SiteInput): Promise<Site> =>
  api.patch<ApiEnvelope<Site>>(`/api/v1/sites/${id}`, input).then((r) => r.data.data);

export const deleteSite = (id: number): Promise<void> =>
  api.delete(`/api/v1/sites/${id}`).then(() => undefined);
