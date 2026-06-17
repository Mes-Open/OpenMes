import { useQuery } from '@tanstack/react-query';

import { listAuditLogs, listAuditLogsForEntity, type AuditLogFilters } from '@/api/auditLogs';

export function useAuditLogs(filters: AuditLogFilters = {}) {
  return useQuery({
    queryKey: ['audit-logs', filters],
    queryFn: () => listAuditLogs(filters),
  });
}

export function useAuditLogsForEntity(entity_type: string | undefined, entity_id: number | undefined) {
  return useQuery({
    queryKey: ['audit-logs-entity', entity_type, entity_id],
    queryFn: () => listAuditLogsForEntity(entity_type as string, entity_id as number),
    enabled: !!entity_type && typeof entity_id === 'number' && Number.isFinite(entity_id),
  });
}
