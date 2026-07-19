import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  createCrewBreakWindow,
  deleteCrewBreakWindow,
  listCrewBreakWindows,
} from '@/api/crewBreakWindows';

export function useCrewBreakWindows() {
  return useQuery({ queryKey: ['crew-break-windows'], queryFn: listCrewBreakWindows });
}

export function useCreateCrewBreakWindow() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: createCrewBreakWindow,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['crew-break-windows'] }),
  });
}

export function useDeleteCrewBreakWindow() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: deleteCrewBreakWindow,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['crew-break-windows'] }),
  });
}
