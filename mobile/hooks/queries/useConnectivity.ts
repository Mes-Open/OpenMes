import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import * as c from '@/api/connectivity';

const inv = (qc: ReturnType<typeof useQueryClient>, key: string) => qc.invalidateQueries({ queryKey: [key] });

export function useConnections(includeInactive = false) {
  return useQuery({ queryKey: ['mc-connections', includeInactive], queryFn: () => c.listConnections(includeInactive) });
}
export function useConnection(id: number | undefined) {
  return useQuery({
    queryKey: ['mc-connection', id],
    queryFn: () => c.getConnection(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}
export function useConnectionMqtt(id: number | undefined) {
  return useQuery({
    queryKey: ['mc-connection', id, 'mqtt'],
    queryFn: () => c.getConnectionMqttSettings(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}
export function useCreateConnection() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: c.createConnection,
    onSuccess: () => inv(qc, 'mc-connections'),
  });
}
export function useUpdateConnection() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, input }: { id: number; input: c.ConnectionInput }) =>
      c.updateConnection(id, input),
    onSuccess: (_, vars) => {
      inv(qc, 'mc-connections');
      qc.invalidateQueries({ queryKey: ['mc-connection', vars.id] });
    },
  });
}
export function useDeleteConnection() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: c.deleteConnection, onSuccess: () => inv(qc, 'mc-connections') });
}
export function useToggleConnectionActive() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: c.toggleConnectionActive, onSuccess: () => inv(qc, 'mc-connections') });
}

export function useTopics(opts: Parameters<typeof c.listTopics>[0] = {}) {
  return useQuery({ queryKey: ['mc-topics', opts], queryFn: () => c.listTopics(opts) });
}
export function useTopic(id: number | undefined) {
  return useQuery({
    queryKey: ['mc-topic', id],
    queryFn: () => c.getTopic(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}
export function useCreateTopic() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: c.createTopic,
    onSuccess: () => inv(qc, 'mc-topics'),
  });
}
export function useUpdateTopic() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, input }: { id: number; input: c.TopicInput }) => c.updateTopic(id, input),
    onSuccess: (_, vars) => {
      inv(qc, 'mc-topics');
      qc.invalidateQueries({ queryKey: ['mc-topic', vars.id] });
    },
  });
}
export function useDeleteTopic() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: c.deleteTopic, onSuccess: () => inv(qc, 'mc-topics') });
}
export function useToggleTopicActive() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: c.toggleTopicActive, onSuccess: () => inv(qc, 'mc-topics') });
}

export function useMappings(opts: Parameters<typeof c.listMappings>[0] = {}) {
  return useQuery({ queryKey: ['mc-mappings', opts], queryFn: () => c.listMappings(opts) });
}
export function useMapping(id: number | undefined) {
  return useQuery({
    queryKey: ['mc-mapping', id],
    queryFn: () => c.getMapping(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}
export function useCreateMapping() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: c.createMapping,
    onSuccess: () => inv(qc, 'mc-mappings'),
  });
}
export function useUpdateMapping() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, input }: { id: number; input: c.MappingInput }) =>
      c.updateMapping(id, input),
    onSuccess: (_, vars) => {
      inv(qc, 'mc-mappings');
      qc.invalidateQueries({ queryKey: ['mc-mapping', vars.id] });
    },
  });
}
export function useDeleteMapping() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: c.deleteMapping, onSuccess: () => inv(qc, 'mc-mappings') });
}

export function useMessages(filters: c.MessageFilters = {}) {
  return useQuery({
    queryKey: ['mc-messages', filters],
    queryFn: () => c.listMessages(filters),
    refetchInterval: 15_000,
  });
}
export function useMessage(id: number | undefined) {
  return useQuery({
    queryKey: ['mc-message', id],
    queryFn: () => c.getMessage(id as number),
    enabled: typeof id === 'number' && Number.isFinite(id),
  });
}
