import { api } from './client';
import type { ApiEnvelope, ApiPaginated } from '@/types/api';

export type MachineConnectionStatus = 'disconnected' | 'connected' | 'connecting' | 'error';
export type MachineConnectionProtocol = 'mqtt' | 'opcua' | 'modbus' | 'rest';

export interface MachineConnection {
  id: number;
  name: string;
  description?: string | null;
  protocol: MachineConnectionProtocol;
  is_active: boolean;
  status: MachineConnectionStatus;
  status_message?: string | null;
  last_connected_at?: string | null;
  messages_received?: number;
  topics_count?: number;
  messages_count?: number;
  mqtt_connection?: MqttConnection | null;
}

export interface MqttConnection {
  id: number;
  machine_connection_id: number;
  broker_host: string;
  broker_port: number;
  client_id?: string | null;
  username?: string | null;
  use_tls?: boolean;
  qos_default?: number;
  clean_session?: boolean;
}

export interface MachineTopic {
  id: number;
  machine_connection_id: number;
  topic_pattern: string;
  payload_format?: string | null;
  description?: string | null;
  is_active: boolean;
  mappings_count?: number;
  machine_connection?: MachineConnection;
  mappings?: TopicMapping[];
}

export interface TopicMapping {
  id: number;
  machine_topic_id: number;
  description?: string | null;
  field_path?: string | null;
  action_type: string;
  action_params?: unknown;
  condition_expr?: string | null;
  priority: number;
  is_active: boolean;
  topic?: MachineTopic;
}

export interface MachineMessage {
  id: number;
  machine_connection_id: number;
  topic: string;
  raw_payload: string;
  parsed_data?: unknown;
  actions_triggered?: unknown;
  processing_status: 'ok' | 'error' | 'skipped';
  processing_error?: string | null;
  received_at: string;
  connection?: MachineConnection;
}

// ── Connections ────────────────────────────────────────────────────────────

export const listConnections = (includeInactive = false): Promise<MachineConnection[]> =>
  api
    .get<ApiEnvelope<MachineConnection[]>>('/api/v1/connectivity/connections', {
      params: { include_inactive: includeInactive },
    })
    .then((r) => r.data.data);

export const getConnection = (id: number): Promise<MachineConnection> =>
  api
    .get<ApiEnvelope<MachineConnection>>(`/api/v1/connectivity/connections/${id}`)
    .then((r) => r.data.data);

export const getConnectionMqttSettings = (id: number): Promise<MqttConnection | null> =>
  api
    .get<ApiEnvelope<MqttConnection | null>>(`/api/v1/connectivity/connections/${id}/mqtt`)
    .then((r) => r.data.data);

/** Form payload for creating / updating a MachineConnection + its MqttConnection. */
export interface ConnectionInput {
  name: string;
  description?: string | null;
  is_active?: boolean;
  broker_host: string;
  broker_port: number;
  client_id?: string | null;
  username?: string | null;
  /** Only sent when the user enters a new password — omit / empty to keep existing. */
  password?: string | null;
  use_tls?: boolean;
  ca_cert?: string | null;
  keep_alive_seconds: number;
  qos_default: 0 | 1 | 2;
  clean_session?: boolean;
  connect_timeout: number;
  reconnect_delay_seconds: number;
}

export const createConnection = (input: ConnectionInput): Promise<MachineConnection> =>
  api
    .post<ApiEnvelope<MachineConnection>>('/api/v1/connectivity/connections', input)
    .then((r) => r.data.data);

export const updateConnection = (
  id: number,
  input: ConnectionInput,
): Promise<MachineConnection> =>
  api
    .patch<ApiEnvelope<MachineConnection>>(`/api/v1/connectivity/connections/${id}`, input)
    .then((r) => r.data.data);

export const deleteConnection = (id: number): Promise<void> =>
  api.delete(`/api/v1/connectivity/connections/${id}`).then(() => undefined);

export const toggleConnectionActive = (id: number): Promise<MachineConnection> =>
  api
    .post<ApiEnvelope<MachineConnection>>(
      `/api/v1/connectivity/connections/${id}/toggle-active`,
    )
    .then((r) => r.data.data);

// ── Topics ─────────────────────────────────────────────────────────────────

export const listTopics = (
  opts: { machine_connection_id?: number; include_inactive?: boolean } = {},
): Promise<MachineTopic[]> =>
  api
    .get<ApiEnvelope<MachineTopic[]>>('/api/v1/connectivity/topics', { params: opts })
    .then((r) => r.data.data);

export const getTopic = (id: number): Promise<MachineTopic> =>
  api
    .get<ApiEnvelope<MachineTopic>>(`/api/v1/connectivity/topics/${id}`)
    .then((r) => r.data.data);

/** Form payload for MachineTopic. machine_connection_id is required on create only. */
export interface TopicInput {
  machine_connection_id?: number;
  topic_pattern: string;
  payload_format: 'json' | 'plain' | 'csv' | 'hex';
  description?: string | null;
  is_active?: boolean;
}

export const createTopic = (input: TopicInput): Promise<MachineTopic> =>
  api
    .post<ApiEnvelope<MachineTopic>>('/api/v1/connectivity/topics', input)
    .then((r) => r.data.data);

export const updateTopic = (id: number, input: TopicInput): Promise<MachineTopic> =>
  api
    .patch<ApiEnvelope<MachineTopic>>(`/api/v1/connectivity/topics/${id}`, input)
    .then((r) => r.data.data);

export const deleteTopic = (id: number): Promise<void> =>
  api.delete(`/api/v1/connectivity/topics/${id}`).then(() => undefined);

export const toggleTopicActive = (id: number): Promise<MachineTopic> =>
  api
    .post<ApiEnvelope<MachineTopic>>(`/api/v1/connectivity/topics/${id}/toggle-active`)
    .then((r) => r.data.data);

// ── Mappings ───────────────────────────────────────────────────────────────

export const listMappings = (
  opts: { machine_topic_id?: number; include_inactive?: boolean } = {},
): Promise<TopicMapping[]> =>
  api
    .get<ApiEnvelope<TopicMapping[]>>('/api/v1/connectivity/mappings', { params: opts })
    .then((r) => r.data.data);

export type MappingActionType =
  | 'update_batch_step'
  | 'update_work_order_qty'
  | 'create_issue'
  | 'update_line_status'
  | 'set_work_order_status'
  | 'log_event'
  | 'webhook_forward';

/** Form payload for TopicMapping. action_params can be a JSON object or null. */
export interface MappingInput {
  machine_topic_id?: number;
  description?: string | null;
  field_path?: string | null;
  action_type: MappingActionType;
  action_params?: Record<string, unknown> | null;
  condition_expr?: string | null;
  priority: number;
  is_active?: boolean;
}

export const createMapping = (input: MappingInput): Promise<TopicMapping> =>
  api
    .post<ApiEnvelope<TopicMapping>>('/api/v1/connectivity/mappings', input)
    .then((r) => r.data.data);

export const updateMapping = (id: number, input: MappingInput): Promise<TopicMapping> =>
  api
    .patch<ApiEnvelope<TopicMapping>>(`/api/v1/connectivity/mappings/${id}`, input)
    .then((r) => r.data.data);

export const getMapping = (id: number): Promise<TopicMapping> =>
  api
    .get<ApiEnvelope<TopicMapping>>(`/api/v1/connectivity/mappings/${id}`)
    .then((r) => r.data.data);

export const deleteMapping = (id: number): Promise<void> =>
  api.delete(`/api/v1/connectivity/mappings/${id}`).then(() => undefined);

// ── Messages ───────────────────────────────────────────────────────────────

export interface MessageFilters {
  machine_connection_id?: number;
  processing_status?: 'ok' | 'error' | 'skipped';
  from?: string;
  to?: string;
  page?: number;
  per_page?: number;
}

export const listMessages = (
  filters: MessageFilters = {},
): Promise<{ data: MachineMessage[]; meta?: ApiPaginated<MachineMessage>['meta'] }> =>
  api
    .get<ApiPaginated<MachineMessage>>('/api/v1/connectivity/messages', { params: filters })
    .then((r) => ({ data: r.data.data, meta: r.data.meta }));

export const getMessage = (id: number): Promise<MachineMessage> =>
  api
    .get<ApiEnvelope<MachineMessage>>(`/api/v1/connectivity/messages/${id}`)
    .then((r) => r.data.data);
