import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { csvImportApi, ImportMapping } from '../api/csvImport';
import { notifications } from '@mantine/notifications';

// Upload CSV file
export const useUploadCsv = () => {
  return useMutation({
    mutationFn: (file: File) => csvImportApi.upload(file),
    onError: (error: any) => {
      notifications.show({
        title: 'Upload Failed',
        message: error.response?.data?.message || 'Failed to upload CSV file',
        color: 'red',
      });
    },
  });
};

// Execute CSV import
export const useExecuteImport = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: {
      upload_id: string;
      mapping: ImportMapping;
      save_mapping_template?: boolean;
      mapping_template_name?: string;
    }) => csvImportApi.execute(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['csvImports'] });
      queryClient.invalidateQueries({ queryKey: ['workOrders'] });

      notifications.show({
        title: 'Import Started',
        message: 'CSV import is processing. You can check the status below.',
        color: 'blue',
      });
    },
    onError: (error: any) => {
      notifications.show({
        title: 'Import Failed',
        message: error.response?.data?.message || 'Failed to start CSV import',
        color: 'red',
      });
    },
  });
};

// Get import status (with polling)
export const useImportStatus = (importId: number | null, enabled = true) => {
  return useQuery({
    queryKey: ['csvImport', importId],
    queryFn: () => csvImportApi.getImportStatus(importId!),
    enabled: enabled && importId !== null,
    refetchInterval: (data) => {
      // Poll every 2 seconds if still processing
      if (data?.status === 'PENDING' || data?.status === 'PROCESSING') {
        return 2000;
      }
      return false;
    },
  });
};

// List all imports
export const useCsvImports = () => {
  return useQuery({
    queryKey: ['csvImports'],
    queryFn: () => csvImportApi.getImports(),
  });
};

// Get mapping templates
export const useMappingTemplates = () => {
  return useQuery({
    queryKey: ['csvMappingTemplates'],
    queryFn: () => csvImportApi.getMappings(),
    staleTime: 5 * 60 * 1000, // 5 minutes
  });
};

// Save mapping template
export const useSaveMappingTemplate = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: {
      name: string;
      mapping_config: ImportMapping;
      is_default?: boolean;
    }) => csvImportApi.saveMapping(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['csvMappingTemplates'] });

      notifications.show({
        title: 'Template Saved',
        message: 'Mapping template has been saved successfully',
        color: 'green',
      });
    },
    onError: (error: any) => {
      notifications.show({
        title: 'Save Failed',
        message: error.response?.data?.message || 'Failed to save mapping template',
        color: 'red',
      });
    },
  });
};
