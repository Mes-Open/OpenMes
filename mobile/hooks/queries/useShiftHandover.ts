import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { closeShift, getShiftHandover } from '@/api/shiftHandover';

export function useShiftHandover(lineId?: number | null) {
  return useQuery({
    queryKey: ['shift-handover', lineId ?? null],
    queryFn: () => getShiftHandover(lineId),
    refetchInterval: 30_000,
  });
}

export function useCloseShift() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: closeShift,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['shift-handover'] }),
  });
}
