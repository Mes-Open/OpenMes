import { useQuery } from '@tanstack/react-query';

import {
  getBatchCompletion,
  getDowntimeReport,
  getProductionSummary,
  type ReportFilters,
} from '@/api/reports';

export function useProductionSummary(filters: ReportFilters | null) {
  return useQuery({
    queryKey: ['report', 'production-summary', filters],
    queryFn: () => getProductionSummary(filters as ReportFilters),
    enabled: !!filters,
  });
}

export function useBatchCompletion(filters: ReportFilters | null) {
  return useQuery({
    queryKey: ['report', 'batch-completion', filters],
    queryFn: () => getBatchCompletion(filters as ReportFilters),
    enabled: !!filters,
  });
}

export function useDowntimeReport(filters: ReportFilters | null) {
  return useQuery({
    queryKey: ['report', 'downtime', filters],
    queryFn: () => getDowntimeReport(filters as ReportFilters),
    enabled: !!filters,
  });
}
