import apiClient from './client';

export interface AuditLog {
  id: number;
  user_id?: number;
  entity_type: string;
  entity_id: number;
  entity_name: string;
  action: 'created' | 'updated' | 'deleted';
  before_state?: Record<string, any>;
  after_state?: Record<string, any>;
  ip_address?: string;
  user_agent?: string;
  created_at: string;
  user?: { id: number; username: string };
}

export interface EventLog {
  id: number;
  event_type: string;
  entity_type?: string;
  entity_id?: number;
  user_id?: number;
  payload: Record<string, any>;
  created_at: string;
  user?: { id: number; username: string };
}

export const auditLogApi = {
  // Get audit logs with filters
  getAuditLogs: async (params?: {
    entity_type?: string;
    entity_id?: number;
    user_id?: number;
    action?: string;
    start_date?: string;
    end_date?: string;
    per_page?: number;
    page?: number;
  }) => {
    const response = await apiClient.get('/v1/audit-logs', { params });
    return response.data;
  },

  // Get audit logs for specific entity
  getEntityAuditLogs: async (entityType: string, entityId: number): Promise<AuditLog[]> => {
    const response = await apiClient.get('/v1/audit-logs/entity', {
      params: { entity_type: entityType, entity_id: entityId },
    });
    return response.data.data;
  },

  // Export audit logs to CSV
  exportAuditLogs: async (params?: {
    entity_type?: string;
    user_id?: number;
    start_date?: string;
    end_date?: string;
  }) => {
    const response = await apiClient.get('/v1/audit-logs/export', {
      params,
      responseType: 'blob',
    });
    return response.data;
  },

  // Get event logs with filters
  getEventLogs: async (params?: {
    event_type?: string;
    entity_type?: string;
    entity_id?: number;
    user_id?: number;
    start_date?: string;
    end_date?: string;
    per_page?: number;
  }) => {
    const response = await apiClient.get('/v1/event-logs', { params });
    return response.data;
  },

  // Get event logs for specific entity
  getEntityEventLogs: async (entityType: string, entityId: number): Promise<EventLog[]> => {
    const response = await apiClient.get('/v1/event-logs/entity', {
      params: { entity_type: entityType, entity_id: entityId },
    });
    return response.data.data;
  },
};
