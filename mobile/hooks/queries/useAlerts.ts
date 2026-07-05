import { useQuery } from '@tanstack/react-query';

import { getAlerts } from '@/api/alerts';

export function useAlerts() {
  return useQuery({ queryKey: ['alerts'], queryFn: getAlerts, refetchInterval: 20000 });
}
