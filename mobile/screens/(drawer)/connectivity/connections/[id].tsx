/**
 * Connection detail — mirrors the web MQTT Show page: a status header (dot +
 * name + broker line), a three-up stat grid (Topics / Messages / Msg-min) and
 * the subscribed-topics list. Edit / activate / delete stay available as the
 * page actions. Data via REST; msg/min is derived from the recent message tail.
 */
import { useMemo } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import {
  useConnection,
  useConnectionMqtt,
  useDeleteConnection,
  useMessages,
  useToggleConnectionActive,
  useTopics,
} from '@/hooks/queries/useConnectivity';

function statusColor(status: string): string {
  if (status === 'connected') return colors.running;
  if (status === 'error' || status === 'disconnected') return colors.blocked;
  return colors.downtime;
}

export function ConnectionDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();
  const { t } = useTranslation();

  const query = useConnection(numericId);
  const mqttQuery = useConnectionMqtt(numericId);
  const topicsQuery = useTopics({ machine_connection_id: numericId });
  const messagesQuery = useMessages({ machine_connection_id: numericId, per_page: 60 });

  const deleteMutation = useDeleteConnection();
  const toggleMutation = useToggleConnectionActive();

  const msgPerMin = useMemo(() => {
    const msgs = messagesQuery.data?.data ?? [];
    if (msgs.length < 2) return msgs.length;
    try {
      const newest = new Date(msgs[0].received_at).getTime();
      const oldest = new Date(msgs[msgs.length - 1].received_at).getTime();
      const spanMin = Math.max((newest - oldest) / 60_000, 1 / 60);
      return Math.round(msgs.length / spanMin);
    } catch {
      return msgs.length;
    }
  }, [messagesQuery.data]);

  const topics = topicsQuery.data ?? [];

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;
  const c = query.data;

  const broker = mqttQuery.data?.broker_host
    ? `mqtt://${mqttQuery.data.broker_host}:${mqttQuery.data.broker_port}${mqttQuery.data.use_tls ? ' · TLS' : ''}`
    : c.protocol.toUpperCase();

  return (
    <View style={styles.screen}>
      <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
        <View>
          <View style={styles.statusRow}>
            <View style={[styles.dot, { backgroundColor: statusColor(c.status) }]} />
            <Mono size={10} color={colors.muted} letterSpacing={0.5}>
              {c.status.toUpperCase()}
              {c.is_active === false ? ` · ${t('Inactive').toUpperCase()}` : ''}
            </Mono>
          </View>
          <Text style={styles.h1}>{c.name}</Text>
          <Mono size={11} color={colors.faint} style={{ marginTop: 4 }}>{broker}</Mono>
        </View>

        <View style={styles.kpiRow}>
          <Kpi label={t('Topics')} value={String(c.topics_count ?? topics.length)} />
          <Kpi label={t('Messages')} value={String(c.messages_received ?? 0)} />
          <Kpi label={t('Msg/min')} value={String(msgPerMin)} />
        </View>

        <View style={{ gap: 8 }}>
          <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Topics').toUpperCase()}</Mono>
          <View style={styles.box}>
            {topics.length === 0 ? (
              <Text style={styles.emptyBox}>{t('No topics.')}</Text>
            ) : (
              topics.map((tp, i) => (
                <Pressable
                  key={tp.id}
                  onPress={() => router.push(`/connectivity/topics/${tp.id}` as never)}
                  style={({ pressed }) => [
                    styles.topicRow,
                    { borderBottomWidth: i < topics.length - 1 ? StyleSheet.hairlineWidth : 0, opacity: pressed ? 0.6 : 1 },
                  ]}>
                  <Mono size={11} color={colors.ink} style={{ flex: 1 }} numberOfLines={1}>{tp.topic_pattern}</Mono>
                  <Mono size={10} color={colors.faint}>{t('{{count}} rules', { count: tp.mappings_count ?? 0 })}</Mono>
                </Pressable>
              ))
            )}
          </View>
        </View>

        <Button title={t('Edit connection')} onPress={() => router.push(`/connectivity/connections/${c.id}/edit` as never)} />
        <Button
          title={t('Open connection topics')}
          variant="secondary"
          onPress={() => router.push(`/(drawer)/connectivity/topics?machine_connection_id=${c.id}` as never)}
        />
        <Button
          title={c.is_active ? t('Deactivate') : t('Activate')}
          variant="secondary"
          loading={toggleMutation.isPending}
          onPress={() => toggleMutation.mutate(c.id, { onError: (e: Error) => Alert.alert(t('Failed'), e.message) })}
        />
        <Button
          title={t('Delete connection')}
          variant="danger"
          loading={deleteMutation.isPending}
          onPress={() =>
            Alert.alert(t('Delete connection'), t('Delete "{{name}}"?', { name: c.name }), [
              { text: t('Cancel'), style: 'cancel' },
              {
                text: t('Delete'),
                style: 'destructive',
                onPress: () =>
                  deleteMutation.mutate(c.id, {
                    onSuccess: () => router.back(),
                    onError: (e: Error) => Alert.alert(t('Failed'), e.message),
                  }),
              },
            ])
          }
        />
      </ScrollView>
    </View>
  );
}

function Kpi({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.kpiTile}>
      <Text style={styles.kpiValue}>{value}</Text>
      <Mono size={9} color={colors.faint} letterSpacing={0.6}>{label.toUpperCase()}</Mono>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  statusRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  dot: { width: 9, height: 9, borderRadius: 4.5 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4, marginTop: 8 },
  kpiRow: { flexDirection: 'row', gap: 8 },
  kpiTile: { flex: 1, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 12, gap: 4 },
  kpiValue: { fontSize: 22, fontFamily: fonts.mono.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  box: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, overflow: 'hidden' },
  topicRow: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 12, paddingHorizontal: 14, borderBottomColor: colors.line2 },
  emptyBox: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, padding: 14 },
});
