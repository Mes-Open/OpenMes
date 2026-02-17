import apiClient from './client';

export interface CsvUploadResponse {
  upload_id: string;
  filename: string;
  headers: string[];
  preview: string[][];
  total_rows: number;
}

export interface ColumnMapping {
  [field: string]: {
    csv_column: string;
    transform?: string;
    default?: any;
  };
}

export interface ImportMapping {
  import_strategy: 'update_or_create' | 'skip_existing' | 'error_on_duplicate';
  columns: ColumnMapping;
}

export interface CsvImport {
  id: number;
  filename: string;
  status: 'PENDING' | 'PROCESSING' | 'COMPLETED' | 'FAILED';
  total_rows: number;
  successful_rows: number;
  failed_rows: number;
  error_log: Array<{ row: number; error: string }>;
  started_at?: string;
  completed_at?: string;
}

export interface MappingTemplate {
  id: number;
  name: string;
  mapping_config: ImportMapping;
  is_default: boolean;
}

export const csvImportApi = {
  // Upload CSV file
  upload: async (file: File): Promise<CsvUploadResponse> => {
    const formData = new FormData();
    formData.append('file', file);

    const response = await apiClient.post('/v1/csv-imports/upload', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data.data;
  },

  // Execute import with mapping
  execute: async (data: {
    upload_id: string;
    mapping: ImportMapping;
    save_mapping_template?: boolean;
    mapping_template_name?: string;
  }) => {
    const response = await apiClient.post('/v1/csv-imports/execute', data);
    return response.data.data;
  },

  // Get import status
  getImportStatus: async (importId: number): Promise<CsvImport> => {
    const response = await apiClient.get(`/v1/csv-imports/${importId}`);
    return response.data.data;
  },

  // List all imports
  getImports: async () => {
    const response = await apiClient.get('/v1/csv-imports');
    return response.data;
  },

  // Get saved mapping templates
  getMappings: async (): Promise<MappingTemplate[]> => {
    const response = await apiClient.get('/v1/csv-import-mappings');
    return response.data.data;
  },

  // Save mapping template
  saveMapping: async (data: {
    name: string;
    mapping_config: ImportMapping;
    is_default?: boolean;
  }): Promise<MappingTemplate> => {
    const response = await apiClient.post('/v1/csv-import-mappings', data);
    return response.data.data;
  },
};
