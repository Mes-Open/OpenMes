import { useQuery } from '@tanstack/react-query';

import {
  getBatchCompletion,
  getDowntimeReport,
  getNetRequirements,
  getNonConformance,
  getProductionCost,
  getProductionSummary,
  getScrap,
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

export function useScrapReport(filters: ReportFilters | null) {
  return useQuery({
    queryKey: ['report', 'scrap', filters],
    queryFn: () => getScrap(filters as ReportFilters),
    enabled: !!filters,
  });
}

export function useNonConformanceReport(filters: ReportFilters | null) {
  return useQuery({
    queryKey: ['report', 'non-conformance', filters],
    queryFn: () => getNonConformance(filters as ReportFilters),
    enabled: !!filters,
  });
}

export function useNetRequirementsReport(filters: ReportFilters | null) {
  return useQuery({
    queryKey: ['report', 'net-requirements', filters],
    queryFn: () => getNetRequirements(filters as ReportFilters),
    enabled: !!filters,
  });
}

export function useProductionCostReport(filters: ReportFilters | null) {
  return useQuery({
    queryKey: ['report', 'production-cost', filters],
    queryFn: () => getProductionCost(filters as ReportFilters),
    enabled: !!filters,
  });
}
