import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  executeCsvImport,
  getCsvImportStatus,
  listCsvImportMappings,
  listCsvImports,
  saveCsvImportMapping,
  uploadCsv,
  type CsvExecuteInput,
  type SaveCsvMappingInput,
} from '@/api/csvImports';

export function useCsvImports() {
  return useQuery({
    queryKey: ['csv-imports'],
    queryFn: listCsvImports,
  });
}

export function useCsvImportStatus(id: number | undefined, opts: { refetchInterval?: number } = {}) {
  return useQuery({
    queryKey: ['csv-import', id],
    queryFn: () => getCsvImportStatus(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
    refetchInterval: opts.refetchInterval,
  });
}

export function useUploadCsv() {
  return useMutation({
    mutationFn: (vars: { uri: string; name: string; mimeType?: string }) => uploadCsv(vars),
  });
}

export function useExecuteCsvImport() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: CsvExecuteInput) => executeCsvImport(input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['csv-imports'] }),
  });
}

export function useCsvImportMappings() {
  return useQuery({
    queryKey: ['csv-import-mappings'],
    queryFn: listCsvImportMappings,
    staleTime: 5 * 60 * 1000,
  });
}

export function useSaveCsvImportMapping() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: SaveCsvMappingInput) => saveCsvImportMapping(input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['csv-import-mappings'] }),
  });
}
