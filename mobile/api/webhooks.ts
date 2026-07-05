import { api } from './client';
import type { ApiEnvelope } from '@/types/api';

export interface Webhook {
  id: number;
  name: string;
  url: string;
  events: string[];
  events_count: number;
  headers?: Record<string, string>;
  is_active: boolean;
  last_triggered_at: string | null;
}

export interface WebhookEventType {
  key: string;
  label: string;
}

export interface WebhookInput {
  name: string;
  url: string;
  events: string[];
  is_active?: boolean;
  /** Optional new signing secret; omit to keep the existing one. */
  secret?: string;
}

export const listWebhooks = (): Promise<Webhook[]> =>
  api.get<ApiEnvelope<Webhook[]>>('/api/v1/webhooks').then((r) => r.data.data);

export const listWebhookEventTypes = (): Promise<WebhookEventType[]> =>
  api.get<ApiEnvelope<WebhookEventType[]>>('/api/v1/webhook-event-types').then((r) => r.data.data);

export const createWebhook = (input: WebhookInput): Promise<Webhook> =>
  api.post<ApiEnvelope<Webhook>>('/api/v1/webhooks', input).then((r) => r.data.data);

export const updateWebhook = (id: number, input: WebhookInput): Promise<Webhook> =>
  api.patch<ApiEnvelope<Webhook>>(`/api/v1/webhooks/${id}`, input).then((r) => r.data.data);

export const toggleWebhookActive = (id: number): Promise<{ id: number; is_active: boolean }> =>
  api
    .post<ApiEnvelope<{ id: number; is_active: boolean }>>(`/api/v1/webhooks/${id}/toggle-active`)
    .then((r) => r.data.data);

export const deleteWebhook = (id: number): Promise<void> =>
  api.delete(`/api/v1/webhooks/${id}`).then(() => undefined);
