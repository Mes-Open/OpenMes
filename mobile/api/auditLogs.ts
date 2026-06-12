import { api } from './client';
import { useSettingsStore } from '@/stores/settingsStore';
import type { ApiPaginated } from '@/types/api';

export type AuditAction = 'created' | 'updated' | 'deleted';

export interface AuditLog {
  id: number;
  user_id?: number | null;
  user?: { id: number; username: string; name?: string | null } | null;
  entity_type: string;
  entity_id: number;
  entity_name?: string | null;
  action: AuditAction;
  before_state?: Record<string, unknown> | null;
  after_state?: Record<string, unknown> | null;
  ip_address?: string | null;
  user_agent?: string | null;
  created_at: string;
}

export interface AuditLogFilters {
  entity_type?: string;
  entity_id?: number;
  user_id?: number;
  action?: AuditAction;
  start_date?: string;
  end_date?: string;
  per_page?: number;
  page?: number;
}

export const listAuditLogs = (
  filters: AuditLogFilters = {},
): Promise<ApiPaginated<AuditLog>> =>
  api.get<ApiPaginated<AuditLog>>('/api/v1/audit-logs', { params: filters }).then((r) => r.data);

export const listAuditLogsForEntity = (
  entity_type: string,
  entity_id: number,
): Promise<AuditLog[]> =>
  api
    .get<{ data: AuditLog[] }>('/api/v1/audit-logs/entity', {
      params: { entity_type, entity_id },
    })
    .then((r) => r.data.data);

/** URL builder for the export endpoint (browser-opens directly). */
export function auditLogsExportUrl(
  filters: Omit<AuditLogFilters, 'page' | 'per_page'> = {},
): string {
  const baseUrl = useSettingsStore.getState().serverUrl;
  const qs = Object.entries(filters)
    .filter(([, v]) => v !== undefined && v !== null && v !== '')
    .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(String(v))}`)
    .join('&');
  return `${baseUrl}/api/v1/audit-logs/export${qs ? `?${qs}` : ''}`;
}
