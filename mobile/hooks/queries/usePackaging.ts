import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  createEan,
  deleteEan,
  getPackagingStats,
  listEans,
  listPackagingItems,
  listScanLogs,
  scanEan,
  type EanFilters,
  type ScanLogFilters,
} from '@/api/packaging';

export function usePackagingItems() {
  return useQuery({
    queryKey: ['packaging', 'items'],
    queryFn: listPackagingItems,
    refetchInterval: 10_000,
  });
}

export function usePackagingStats() {
  return useQuery({
    queryKey: ['packaging', 'stats'],
    queryFn: getPackagingStats,
    refetchInterval: 30_000,
  });
}

export function useEans(filters: EanFilters = {}) {
  return useQuery({
    queryKey: ['packaging', 'eans', filters],
    queryFn: () => listEans(filters),
  });
}

export function useScanLogs(filters: ScanLogFilters = {}) {
  return useQuery({
    queryKey: ['packaging', 'scan-logs', filters],
    queryFn: () => listScanLogs(filters),
  });
}

export function useScanEan() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (ean: string) => scanEan(ean),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['packaging'] });
    },
  });
}

export function useCreateEan() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: { work_order_id: number; ean: string }) => createEan(payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['packaging', 'eans'] }),
  });
}

export function useDeleteEan() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteEan(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['packaging', 'eans'] }),
  });
}
