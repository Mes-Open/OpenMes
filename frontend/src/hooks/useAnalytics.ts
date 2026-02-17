import { useQuery } from '@tanstack/react-query';
import { analyticsApi } from '../api/analytics';

export const useOverviewStats = (lineId?: number) => {
  return useQuery({
    queryKey: ['analytics', 'overview', lineId],
    queryFn: () => analyticsApi.getOverview(lineId ? { line_id: lineId } : undefined),
    refetchInterval: 30000, // Refetch every 30 seconds
  });
};

export const useProductionByLine = (startDate?: string, endDate?: string) => {
  return useQuery({
    queryKey: ['analytics', 'production-by-line', startDate, endDate],
    queryFn: () => analyticsApi.getProductionByLine({ start_date: startDate, end_date: endDate }),
    refetchInterval: 60000, // Refetch every minute
  });
};

export const useCycleTime = (lineId?: number, days: number = 30) => {
  return useQuery({
    queryKey: ['analytics', 'cycle-time', lineId, days],
    queryFn: () => analyticsApi.getCycleTime({ line_id: lineId, days }),
    refetchInterval: 60000,
  });
};

export const useThroughput = (lineId?: number, days: number = 30) => {
  return useQuery({
    queryKey: ['analytics', 'throughput', lineId, days],
    queryFn: () => analyticsApi.getThroughput({ line_id: lineId, days }),
    refetchInterval: 60000,
  });
};

export const useIssueStats = (lineId?: number, days: number = 30) => {
  return useQuery({
    queryKey: ['analytics', 'issue-stats', lineId, days],
    queryFn: () => analyticsApi.getIssueStats({ line_id: lineId, days }),
    refetchInterval: 60000,
  });
};

export const useStepPerformance = (lineId?: number, days: number = 30) => {
  return useQuery({
    queryKey: ['analytics', 'step-performance', lineId, days],
    queryFn: () => analyticsApi.getStepPerformance({ line_id: lineId, days }),
    refetchInterval: 60000,
  });
};
