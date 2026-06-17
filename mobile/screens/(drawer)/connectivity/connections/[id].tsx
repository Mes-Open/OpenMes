import { FontAwesome } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import {
  useConnection,
  useConnectionMqtt,
  useDeleteConnection,
  useMessages,
  useToggleConnectionActive,
  useTopics,
} from '@/hooks/queries/useConnectivity';

const DARK = Colors.dark;

/**
 * Connection detail — dark surface (matches design ScreenConnectionDetail).
 * Mobile is read-only: status + KPIs + top-subscribed topics, plus a banner
 * pointing admins to web for edits. Operator-friendly buttons (Toggle / Delete)
 * remain available because the existing screen had them, but the headline of
 * the screen is now status-at-a-glance, not config.
 */
export function ConnectionDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();
  const { t } = useTranslation();

  const query = useConnection(numericId);
  const mqttQuery = useConnectionMqtt(numericId);
  const topicsQuery = useTopics({ machine_connection_id: numericId });
  const messagesQuery = useMessages({
    machine_connection_id: numericId,
    per_page: 60,
  });

  const deleteMutation = useDeleteConnection();
  const toggleMutation = useToggleConnectionActive();

  // Compute msg/min from the most recent 60 messages
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

  const topTopics = (topicsQuery.data ?? []).slice(0, 4);

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data)
    return <ErrorState error={query.error} onRetry={query.refetch} />;
  const c = query.data;

  const connected = c.status === 'connected';
  const statusColor = connected
    ? DARK.success
    : c.status === 'error' || c.status === 'disconnected'
      ? DARK.danger
      : DARK.textFaint;

  return (
    <View style={{ flex: 1, backgroundColor: DARK.background }}>
      <ScreenHeader
        back
        variant="dark"
        title={c.name}
        subtitle={`${t('Connectivity').toUpperCase()} · ${c.protocol.toUpperCase()}`}
      />
      <ScrollView
        style={{ backgroundColor: DARK.background, flex: 1 }}
        contentContainerStyle={styles.container}>
        {/* Status hero */}
        <View
          style={[
            styles.hero,
            { backgroundColor: DARK.surface, borderColor: statusColor },
          ]}>
          <View style={styles.heroStatusRow}>
            <View style={[styles.dot, { backgroundColor: statusColor }]} />
            <Mono size={11} color={statusColor} weight="700" letterSpacing={0.6}>
              {c.status.toUpperCase()}
              {c.last_connected_at ? ` · ${ago(c.last_connected_at)}` : ''}
            </Mono>
          </View>
          <Text style={[styles.heroTitle, { color: DARK.text }]}>{c.name}</Text>
          <Mono size={11} color={DARK.textFaint} style={{ marginTop: 4 }}>
            {mqttQuery.data?.broker_host
              ? `mqtt://${mqttQuery.data.broker_host}:${mqttQuery.data.broker_port}${
                  mqttQuery.data.use_tls ? ' · TLS' : ''
                }`
              : c.protocol.toUpperCase()}
          </Mono>
        </View>

        {/* KPI tiles */}
        <View style={styles.kpiRow}>
          <Kpi
            label={t('TOPICS')}
            value={String(c.topics_count ?? topicsQuery.data?.length ?? 0)}
            color={DARK.text}
          />
          <Kpi label={t('MSG/MIN')} value={String(msgPerMin)} color={BRAND.amber} />
          <Kpi
            label={t('MESSAGES')}
            value={String(c.messages_received ?? 0)}
            color={connected ? DARK.success : DARK.danger}
          />
        </View>

        {/* Top subscribed */}
        <View style={{ gap: 8 }}>
          <Mono size={10.5} color={DARK.textFaint} letterSpacing={0.8}>
            {t('TOP SUBSCRIBED').toUpperCase()} · {topTopics.length} {t('OF').toUpperCase()}{' '}
            {topicsQuery.data?.length ?? 0}
          </Mono>
          <View style={[styles.topicCard, { backgroundColor: DARK.surface, borderColor: DARK.border }]}>
            {topTopics.length === 0 ? (
              <View style={{ padding: 14 }}>
                <Mono size={11} color={DARK.textFaint}>
                  {t('No topics').toUpperCase()}
                </Mono>
              </View>
            ) : (
              topTopics.map((tp, i) => (
                <Pressable
                  key={tp.id}
                  onPress={() =>
                    router.push(`/connectivity/topics/${tp.id}` as never)
                  }
                  style={({ pressed }) => [
                    styles.topicRow,
                    {
                      borderBottomColor: DARK.border,
                      borderBottomWidth:
                        i < topTopics.length - 1 ? StyleSheet.hairlineWidth : 0,
                      opacity: pressed ? 0.85 : 1,
                    },
                  ]}>
                  <FontAwesome name="rss" size={14} color={BRAND.amber} />
                  <Mono
                    size={11}
                    color={DARK.text}
                    style={{ flex: 1, minWidth: 0 }}
                    numberOfLines={1}>
                    {tp.topic_pattern}
                  </Mono>
                  <View style={[styles.qosPill, { backgroundColor: DARK.border }]}>
                    <Mono size={9.5} color={DARK.textMuted} weight="700" letterSpacing={0.4}>
                      Q{tp.mappings_count ?? 0}
                    </Mono>
                  </View>
                </Pressable>
              ))
            )}
          </View>
        </View>

        {/* Edit button — routes to the in-app form (admin-only). */}
        <Pressable
          onPress={() => router.push(`/connectivity/connections/${c.id}/edit` as never)}
          style={[styles.banner, { borderColor: BRAND.amber }]}>
          <FontAwesome name="pencil" size={16} color={BRAND.amber} />
          <Mono size={10.5} color={BRAND.amber} style={{ flex: 1, lineHeight: 16 }}>
            {t('Edit connection settings')}
          </Mono>
          <Mono size={10} color={BRAND.amber} weight="700" letterSpacing={0.5}>
            {t('OPEN ON WEB').toUpperCase()} →
          </Mono>
        </Pressable>

        {/* Operator actions */}
        <Button
          title={t('Open connection topics')}
          variant="secondary"
          onPress={() =>
            router.push(
              `/(drawer)/connectivity/topics?machine_connection_id=${c.id}` as never,
            )
          }
        />
        <Button
          title={c.is_active ? t('Deactivate') : t('Activate')}
          variant="secondary"
          loading={toggleMutation.isPending}
          onPress={() =>
            toggleMutation.mutate(c.id, {
              onError: (e: Error) => Alert.alert(t('Failed'), e.message),
            })
          }
        />
        <Button
          title={t('Delete connection')}
          variant="danger"
          loading={deleteMutation.isPending}
          onPress={() =>
            Alert.alert(
              t('Delete connection'),
              t('Delete "{{name}}"?', { name: c.name }),
              [
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
              ],
            )
          }
        />
      </ScrollView>
    </View>
  );
}

function Kpi({ label, value, color }: { label: string; value: string; color: string }) {
  return (
    <View style={[styles.kpiTile, { backgroundColor: DARK.surface, borderColor: DARK.border }]}>
      <Mono size={9.5} color={DARK.textFaint} letterSpacing={0.5}>
        {label.toUpperCase()}
      </Mono>
      <Text style={[styles.kpiValue, { color, fontFamily: MONO }]}>{value}</Text>
    </View>
  );
}

function ago(iso: string) {
  try {
    const ms = Date.now() - new Date(iso).getTime();
    const min = Math.max(0, Math.floor(ms / 60000));
    if (min < 60) return `${min}m`;
    const h = Math.floor(min / 60);
    if (h < 24) return `${h}h`;
    const d = Math.floor(h / 24);
    return `${d}d`;
  } catch {
    return '';
  }
}

const styles = StyleSheet.create({
  container: { padding: 16, gap: 14 },
  hero: { padding: 16, borderRadius: 14, borderWidth: 1 },
  heroStatusRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  dot: { width: 10, height: 10, borderRadius: 5 },
  heroTitle: { fontSize: 18, fontWeight: '700', marginTop: 8 },
  kpiRow: { flexDirection: 'row', gap: 8 },
  kpiTile: { flex: 1, padding: 12, borderRadius: 10, borderWidth: 1 },
  kpiValue: { fontSize: 22, fontWeight: '700', letterSpacing: -0.4, marginTop: 4 },
  topicCard: { borderRadius: 14, borderWidth: 1, overflow: 'hidden' },
  topicRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 12,
    paddingHorizontal: 14,
  },
  qosPill: { paddingVertical: 1, paddingHorizontal: 5, borderRadius: 3 },
  banner: {
    padding: 12,
    borderRadius: 10,
    borderWidth: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    backgroundColor: '#241a08',
  },
});
