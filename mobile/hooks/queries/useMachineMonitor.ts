import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { getMachineMonitor, setWorkstationState } from '@/api/machineMonitor';

export function useMachineMonitor() {
  return useQuery({
    queryKey: ['machine-monitor'],
    queryFn: getMachineMonitor,
    refetchInterval: 15_000,
  });
}

export function useSetWorkstationState() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, state }: { id: number; state: string }) => setWorkstationState(id, state),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['machine-monitor'] }),
  });
}
