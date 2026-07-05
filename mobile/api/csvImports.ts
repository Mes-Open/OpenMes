import { api } from './client';
import type { ApiEnvelope } from '@/types/api';

export interface CsvImport {
  id: number;
  filename: string;
  status: 'pending' | 'processing' | 'completed' | 'failed' | string;
  total_rows?: number | null;
  successful_rows?: number | null;
  failed_rows?: number | null;
  error_log?: string[] | string | null;
  started_at?: string | null;
  completed_at?: string | null;
  created_at?: string;
  user_id?: number;
}

export const listCsvImports = (): Promise<CsvImport[]> =>
  api.get<ApiEnvelope<CsvImport[]>>('/api/v1/csv-imports').then((r) => r.data.data);

export const getCsvImportStatus = (id: number): Promise<CsvImport> =>
  api.get<ApiEnvelope<CsvImport>>(`/api/v1/csv-imports/${id}`).then((r) => r.data.data);

// ── Upload (multipart) ───────────────────────────────────────────────────

export interface CsvUploadResult {
  upload_id: string;
  filename: string;
  headers: string[];
  /** Each preview row is an array of values aligned to `headers` (by index). */
  preview: string[][];
  total_rows: number;
}

export const uploadCsv = (opts: {
  uri: string;
  name: string;
  mimeType?: string;
}): Promise<CsvUploadResult> => {
  const form = new FormData();
  form.append('file', {
    uri: opts.uri,
    name: opts.name,
    type: opts.mimeType ?? 'text/csv',
  } as unknown as Blob);

  return api
    .post<ApiEnvelope<CsvUploadResult>>('/api/v1/csv-imports/upload', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    .then((r) => r.data.data);
};

// ── Execute import with mapping ───────────────────────────────────────────

export type CsvImportStrategy = 'update_or_create' | 'skip_existing' | 'error_on_duplicate';

/** Maps a system field key (order_no, planned_qty, …) to its CSV column header. */
export type CsvColumnMap = Record<string, { csv_column: string }>;

export interface CsvMapping {
  import_strategy: CsvImportStrategy;
  columns: CsvColumnMap;
  /** "Assign all rows to this line" — overrides any per-row line_code column. */
  target_line_id?: number | null;
}

export interface CsvExecuteInput {
  upload_id: string;
  mapping: CsvMapping;
  save_mapping_template?: boolean;
  mapping_template_name?: string;
}

export const executeCsvImport = (input: CsvExecuteInput): Promise<CsvImport> =>
  api.post<ApiEnvelope<CsvImport>>('/api/v1/csv-imports/execute', input).then((r) => r.data.data);

// ── Mapping templates ─────────────────────────────────────────────────────

export interface CsvImportMapping {
  id: number;
  name: string;
  user_id?: number | null;
  is_default?: boolean;
  mapping_config: CsvMapping;
}

export const listCsvImportMappings = (): Promise<CsvImportMapping[]> =>
  api.get<ApiEnvelope<CsvImportMapping[]>>('/api/v1/csv-import-mappings').then((r) => r.data.data);

export interface SaveCsvMappingInput {
  name: string;
  mapping_config: CsvMapping;
  is_default?: boolean;
}

export const saveCsvImportMapping = (
  input: SaveCsvMappingInput,
): Promise<CsvImportMapping> =>
  api
    .post<ApiEnvelope<CsvImportMapping>>('/api/v1/csv-import-mappings', input)
    .then((r) => r.data.data);
