/**
 * Connectivity hub — the web has no hub (the sidebar links straight to each
 * protocol), so this is restyled to a simple link table: one row per
 * destination with a Mono hint and a live count. Keeps every navigation target
 * and the "connection down" shortcut from the old design.
 */
import { useRouter } from 'expo-router';
import { Feather } from '@expo/vector-icons';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';

import { Mono } from '@/components/ui/Mono';
import { useConnections, useMessages, useTopics } from '@/hooks/queries/useConnectivity';

export function ConnectivityHub() {
  const router = useRouter();
  const { t } = useTranslation();

  const connectionsQ = useConnections(true);
  const topicsQ = useTopics({ include_inactive: true });
  const messagesQ = useMessages();

  const connections = connectionsQ.data ?? [];
  const liveCount = connections.filter((c) => c.status === 'connected').length;
  const offCount = connections.length - liveCount;
  const topicCount = topicsQ.data?.length ?? 0;
  const messageCount = messagesQ.data?.data.length ?? 0;

  const downConnection = connections.find((c) => c.status !== 'connected');

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Connectivity')}</Text>
      </View>

      <ScrollView contentContainerStyle={styles.tableWrap}>
        {downConnection ? (
          <Pressable
            onPress={() => router.push(`/connectivity/connections/${downConnection.id}` as never)}
            style={({ pressed }) => [styles.alert, { opacity: pressed ? 0.7 : 1 }]}>
            <View style={[styles.dot, { backgroundColor: colors.blocked }]} />
            <Text numberOfLines={1} style={styles.alertText}>
              {t('{{name}} disconnected', { name: downConnection.name ?? t('Connection') })}
            </Text>
            <Feather name="chevron-right" size={16} color={colors.blocked} />
          </Pressable>
        ) : null}

        <LinkRow
          icon="link"
          title={t('Connections')}
          hint={`${liveCount} ${t('live')}${offCount > 0 ? ` · ${offCount} ${t('off')}` : ''}`}
          count={connections.length}
          onPress={() => router.push('/(drawer)/connectivity/connections' as never)}
        />
        <LinkRow
          icon="rss"
          title={t('Topics')}
          hint={t('Subscriptions')}
          count={topicCount}
          onPress={() => router.push('/(drawer)/connectivity/topics' as never)}
        />
        <LinkRow
          icon="activity"
          title={t('Messages')}
          hint={t('Live message log')}
          count={messageCount}
          onPress={() => router.push('/(drawer)/connectivity/messages' as never)}
        />
        <LinkRow
          icon="filter"
          title={t('Mappings')}
          hint={t('Payload rules')}
          onPress={() => router.push('/(drawer)/admin/connectivity-mappings' as never)}
        />
      </ScrollView>
    </View>
  );
}

function LinkRow({
  icon,
  title,
  hint,
  count,
  onPress,
}: {
  icon: keyof typeof Feather.glyphMap;
  title: string;
  hint: string;
  count?: number;
  onPress: () => void;
}) {
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.row, { opacity: pressed ? 0.6 : 1 }]}>
      <Feather name={icon} size={16} color={colors.muted} />
      <View style={{ flex: 1 }}>
        <Text numberOfLines={1} style={styles.title}>{title}</Text>
        <Mono size={9} color={colors.faint} letterSpacing={0.4}>{hint.toUpperCase()}</Mono>
      </View>
      {count != null ? <Mono size={12} color={colors.muted}>{String(count)}</Mono> : null}
      <Feather name="chevron-right" size={16} color={colors.faint} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  tableWrap: { paddingHorizontal: 14, paddingBottom: 24 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 14,
    paddingHorizontal: 6,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.line2,
  },
  title: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  alert: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 12,
    marginBottom: 8,
    borderWidth: 1,
    borderColor: colors.blocked,
    borderRadius: 10,
    backgroundColor: colors.blockedBg,
  },
  alertText: { flex: 1, fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.blocked },
  dot: { width: 8, height: 8, borderRadius: 4 },
});
