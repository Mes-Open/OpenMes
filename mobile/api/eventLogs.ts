import { api } from './client';
import type { ApiPaginated } from '@/types/api';

export interface EventLog {
  id: number;
  event_type: string;
  entity_type: string;
  entity_id: number;
  user_id?: number | null;
  user?: { id: number; username: string; name?: string | null } | null;
  payload?: Record<string, unknown> | null;
  metadata?: Record<string, unknown> | null;
  description?: string | null;
  created_at: string;
}

export interface EventLogFilters {
  event_type?: string;
  entity_type?: string;
  entity_id?: number;
  user_id?: number;
  start_date?: string;
  end_date?: string;
  per_page?: number;
  page?: number;
}

export const listEventLogs = (
  filters: EventLogFilters = {},
): Promise<ApiPaginated<EventLog>> =>
  api.get<ApiPaginated<EventLog>>('/api/v1/event-logs', { params: filters }).then((r) => r.data);

export const listEventLogsForEntity = (
  entity_type: string,
  entity_id: number,
): Promise<EventLog[]> =>
  api
    .get<{ data: EventLog[] }>('/api/v1/event-logs/entity', {
      params: { entity_type, entity_id },
    })
    .then((r) => r.data.data);
