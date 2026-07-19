import { useQuery } from '@tanstack/react-query';

import { getAdminDashboard } from '@/api/adminDashboard';

export function useAdminDashboard(lineId?: string) {
  return useQuery({
    queryKey: ['admin-dashboard', lineId ?? ''],
    queryFn: () => getAdminDashboard(lineId || undefined),
    refetchInterval: 30000,
  });
}
