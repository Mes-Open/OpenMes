import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  connectSubiekt,
  getSubiektStatus,
  listSubiektContractors,
  listSubiektProducts,
  listSubiektStock,
  listSubiektWarehouses,
  syncSubiekt,
} from '@/api/subiekt';

export function useSubiektStatus() {
  return useQuery({
    queryKey: ['subiekt', 'status'],
    queryFn: getSubiektStatus,
    refetchInterval: 30_000,
    // 404 means the module is disabled — we suppress retries to render the
    // disabled banner without spinner thrash.
    retry: false,
  });
}

export function useSubiektProducts(enabled = true) {
  return useQuery({
    queryKey: ['subiekt', 'products'],
    queryFn: () => listSubiektProducts(50),
    enabled,
    retry: false,
  });
}

export function useSubiektContractors(enabled = true) {
  return useQuery({
    queryKey: ['subiekt', 'contractors'],
    queryFn: listSubiektContractors,
    enabled,
    retry: false,
  });
}

export function useSubiektWarehouses(enabled = true) {
  return useQuery({
    queryKey: ['subiekt', 'warehouses'],
    queryFn: listSubiektWarehouses,
    enabled,
    retry: false,
  });
}

export function useSubiektStock(enabled = true) {
  return useQuery({
    queryKey: ['subiekt', 'stock'],
    queryFn: listSubiektStock,
    enabled,
    retry: false,
  });
}

export function useConnectSubiekt() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => connectSubiekt(),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['subiekt'] }),
  });
}

export function useSyncSubiekt() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => syncSubiekt(),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['subiekt'] }),
  });
}
