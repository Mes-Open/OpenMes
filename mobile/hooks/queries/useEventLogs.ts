import { useQuery } from '@tanstack/react-query';

import { listEventLogs, listEventLogsForEntity, type EventLogFilters } from '@/api/eventLogs';

export function useEventLogs(filters: EventLogFilters = {}) {
  return useQuery({
    queryKey: ['event-logs', filters],
    queryFn: () => listEventLogs(filters),
  });
}

export function useEventLogsForEntity(entity_type: string | undefined, entity_id: number | undefined) {
  return useQuery({
    queryKey: ['event-logs-entity', entity_type, entity_id],
    queryFn: () => listEventLogsForEntity(entity_type as string, entity_id as number),
    enabled: !!entity_type && typeof entity_id === 'number' && Number.isFinite(entity_id),
  });
}
