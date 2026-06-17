import { useQuery } from '@tanstack/react-query';

import {
  analyticsOverview,
  cycleTime,
  issueStats,
  productionByLine,
  stepPerformance,
  throughput,
} from '@/api/analytics';

export function useAnalyticsOverview(lineId?: number | null) {
  return useQuery({
    queryKey: ['analytics', 'overview', lineId ?? null],
    queryFn: () => analyticsOverview(lineId ?? undefined),
    refetchInterval: 30_000,
  });
}

export function useProductionByLine() {
  return useQuery({
    queryKey: ['analytics', 'production-by-line'],
    queryFn: productionByLine,
    refetchInterval: 60_000,
  });
}

export function useCycleTime(days?: number) {
  return useQuery({
    queryKey: ['analytics', 'cycle-time', days ?? null],
    queryFn: () => cycleTime(days),
  });
}

export function useThroughput(days?: number) {
  return useQuery({
    queryKey: ['analytics', 'throughput', days ?? null],
    queryFn: () => throughput(days),
  });
}

export function useIssueStats(days?: number) {
  return useQuery({
    queryKey: ['analytics', 'issue-stats', days ?? null],
    queryFn: () => issueStats(days),
  });
}

export function useStepPerformance(opts: { line_id?: number; days?: number } = {}) {
  return useQuery({
    queryKey: ['analytics', 'step-performance', opts],
    queryFn: () => stepPerformance(opts),
  });
}
