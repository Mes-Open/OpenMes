import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  createViewTemplate,
  deleteViewTemplate,
  listViewTemplates,
  updateViewTemplate,
  type ViewTemplateInput,
} from '@/api/viewTemplates';

export function useViewTemplates() {
  return useQuery({ queryKey: ['view-templates'], queryFn: listViewTemplates });
}

export function useCreateViewTemplate() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: ViewTemplateInput) => createViewTemplate(input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['view-templates'] }),
  });
}

export function useUpdateViewTemplate() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; input: ViewTemplateInput }) => updateViewTemplate(vars.id, vars.input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['view-templates'] }),
  });
}

export function useDeleteViewTemplate() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: deleteViewTemplate,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['view-templates'] }),
  });
}
