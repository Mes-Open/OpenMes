import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  createWebhook,
  deleteWebhook,
  listWebhookEventTypes,
  listWebhooks,
  toggleWebhookActive,
  updateWebhook,
  type WebhookInput,
} from '@/api/webhooks';

export function useWebhooks() {
  return useQuery({ queryKey: ['webhooks'], queryFn: listWebhooks });
}

export function useWebhookEventTypes() {
  return useQuery({
    queryKey: ['webhook-event-types'],
    queryFn: listWebhookEventTypes,
    staleTime: 10 * 60 * 1000,
  });
}

export function useCreateWebhook() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: WebhookInput) => createWebhook(input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['webhooks'] }),
  });
}

export function useUpdateWebhook() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; input: WebhookInput }) => updateWebhook(vars.id, vars.input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['webhooks'] }),
  });
}

export function useToggleWebhook() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: toggleWebhookActive,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['webhooks'] }),
  });
}

export function useDeleteWebhook() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: deleteWebhook,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['webhooks'] }),
  });
}
