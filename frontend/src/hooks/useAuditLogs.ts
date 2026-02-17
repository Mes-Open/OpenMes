import { useQuery } from '@tanstack/react-query';
import { auditLogApi } from '../api/auditLogs';

// Get audit logs with filters
export const useAuditLogs = (params?: {
  entity_type?: string;
  entity_id?: number;
  user_id?: number;
  action?: string;
  start_date?: string;
  end_date?: string;
  per_page?: number;
}) => {
  return useQuery({
    queryKey: ['auditLogs', params],
    queryFn: () => auditLogApi.getAuditLogs(params),
  });
};

// Get audit logs for a specific entity
export const useEntityAuditLogs = (entityType: string, entityId: number) => {
  return useQuery({
    queryKey: ['auditLogs', 'entity', entityType, entityId],
    queryFn: () => auditLogApi.getEntityAuditLogs(entityType, entityId),
    enabled: !!entityType && !!entityId,
  });
};

// Get event logs with filters
export const useEventLogs = (params?: {
  event_type?: string;
  entity_type?: string;
  entity_id?: number;
  user_id?: number;
  start_date?: string;
  end_date?: string;
}) => {
  return useQuery({
    queryKey: ['eventLogs', params],
    queryFn: () => auditLogApi.getEventLogs(params),
  });
};

// Get event logs for a specific entity (timeline)
export const useEntityEventLogs = (entityType: string, entityId: number) => {
  return useQuery({
    queryKey: ['eventLogs', 'entity', entityType, entityId],
    queryFn: () => auditLogApi.getEntityEventLogs(entityType, entityId),
    enabled: !!entityType && !!entityId,
    refetchInterval: 30000, // Refetch every 30 seconds
  });
};

// Export audit logs
export const useExportAuditLogs = () => {
  return async (params?: {
    entity_type?: string;
    user_id?: number;
    start_date?: string;
    end_date?: string;
  }) => {
    const blob = await auditLogApi.exportAuditLogs(params);
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `audit_log_${new Date().toISOString()}.csv`;
    document.body.appendChild(a);
    a.click();
    window.URL.revokeObjectURL(url);
    document.body.removeChild(a);
  };
};
