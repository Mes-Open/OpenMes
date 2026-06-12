import { FontAwesome } from '@expo/vector-icons';
import { LegendList } from '@legendapp/list';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { SearchBar } from '@/components/ui/SearchBar';
import {
  EmptyState,
  ErrorState,
  LoadingState,
} from '@/components/ui/StateViews';
import Colors, { BRAND } from '@/constants/Colors';
import { useMessages, useTopics } from '@/hooks/queries/useConnectivity';
import type { MachineTopic } from '@/api/connectivity';

const DARK = Colors.dark;

/**
 * Topics list — dark surface matching ScreenTopicsList from gaps.jsx. Silent
 * topics (no messages in the last 5 min) get a red rail + warning row tint;
 * healthy topics show msg/min rate in amber.
 */
export function TopicsList() {
  const router = useRouter();
  const params = useLocalSearchParams<{ machine_connection_id?: string }>();
  const connId = params.machine_connection_id
    ? Number(params.machine_connection_id)
    : undefined;
  const { t } = useTranslation();
  const [search, setSearch] = useState('');

  const topicsQ = useTopics({ machine_connection_id: connId });
  // Recent messages used to compute per-topic rate + silent flag.
  const messagesQ = useMessages({
    machine_connection_id: connId,
    per_page: 200,
  });

  // Build per-topic stats from the recent message tail. The backend doesn't
  // expose a rate-per-topic endpoint, so we compute it client-side.
  const stats = useMemo(() => {
    const out: Record<string, { count: number; newest: number }> = {};
    const msgs = messagesQ.data?.data ?? [];
    for (const m of msgs) {
      try {
        const ts = new Date(m.received_at).getTime();
        const entry = out[m.topic] ?? { count: 0, newest: 0 };
        entry.count += 1;
        entry.newest = Math.max(entry.newest, ts);
        out[m.topic] = entry;
      } catch {}
    }
    return out;
  }, [messagesQ.data]);

  const items = useMemo(() => {
    const all = topicsQ.data ?? [];
    const q = search.trim().toLowerCase();
    if (!q) return all;
    return all.filter((tp) =>
      `${tp.topic_pattern} ${tp.machine_connection?.name ?? ''}`
        .toLowerCase()
        .includes(q),
    );
  }, [topicsQ.data, search]);

  const silentCount = useMemo(() => {
    const fiveMinAgo = Date.now() - 5 * 60_000;
    return (topicsQ.data ?? []).filter((tp) => {
      const s = stats[tp.topic_pattern];
      return !s || s.newest < fiveMinAgo;
    }).length;
  }, [topicsQ.data, stats]);

  return (
    <View style={{ flex: 1, backgroundColor: DARK.background }}>
      <ScreenHeader
        back
        variant="dark"
        title={t('Topics')}
        subtitle={`MQTT · ${items.length} ${t('subscribed').toUpperCase()} · ${silentCount} ${t('silent').toUpperCase()}`}
      />

      {topicsQ.isLoading ? (
        <LoadingState />
      ) : topicsQ.isError ? (
        <ErrorState error={topicsQ.error} onRetry={topicsQ.refetch} />
      ) : (
        <LegendList
          style={{ backgroundColor: DARK.background }}
          contentContainerStyle={styles.list}
          data={items}
          keyExtractor={(tp) => String(tp.id)}
          ListHeaderComponent={
            <View style={{ marginBottom: 14 }}>
              <SearchBar
                placeholder="Search topics"
                value={search}
                onChangeText={setSearch}
              />
            </View>
          }
          ItemSeparatorComponent={() => (
            <View
              style={{
                height: StyleSheet.hairlineWidth,
                backgroundColor: DARK.border,
              }}
            />
          )}
          renderItem={({ item, index }) => (
            <TopicRow
              topic={item}
              stat={stats[item.topic_pattern]}
              first={index === 0}
              last={index === items.length - 1}
              onPress={() =>
                router.push(`/connectivity/topics/${item.id}` as never)
              }
            />
          )}
          ListEmptyComponent={
            <EmptyState title={t('No topics')} />
          }
          refreshControl={
            <RefreshControl
              tintColor={DARK.text}
              refreshing={topicsQ.isFetching}
              onRefresh={topicsQ.refetch}
            />
          }
        />
      )}
    </View>
  );
}

function TopicRow({
  topic,
  stat,
  first,
  last,
  onPress,
}: {
  topic: MachineTopic;
  stat?: { count: number; newest: number };
  first?: boolean;
  last?: boolean;
  onPress: () => void;
}) {
  const { t } = useTranslation();
  const silent = !stat || stat.newest < Date.now() - 5 * 60_000;
  // Per-minute rate over the last 5 min window
  const rate = stat ? Math.round(stat.count / 5) : 0;

  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [
        styles.row,
        {
          backgroundColor: silent ? '#1f0e0e' : DARK.surface,
          borderLeftColor: silent ? DARK.danger : 'transparent',
          borderTopLeftRadius: first ? 14 : 0,
          borderTopRightRadius: first ? 14 : 0,
          borderBottomLeftRadius: last ? 14 : 0,
          borderBottomRightRadius: last ? 14 : 0,
          opacity: pressed ? 0.85 : 1,
        },
      ]}>
      <FontAwesome name="rss" size={14} color={silent ? DARK.danger : BRAND.amber} />
      <View style={{ flex: 1, minWidth: 0 }}>
        <Mono size={11} color={DARK.text} weight="600" numberOfLines={1}>
          {topic.topic_pattern}
        </Mono>
        <Mono
          size={10}
          color={DARK.textFaint}
          letterSpacing={0.3}
          style={{ marginTop: 3 }}>
          {(topic.machine_connection?.name ?? `CONN #${topic.machine_connection_id}`).toUpperCase()}
          {topic.payload_format ? ` · ${topic.payload_format.toUpperCase()}` : ''}
        </Mono>
      </View>
      <View style={{ alignItems: 'flex-end' }}>
        <Text
          style={{
            fontFamily: 'GeistMono_600SemiBold',
            fontSize: 13,
            color: silent ? DARK.danger : BRAND.amber,
          }}>
          {silent ? '0' : rate}
        </Text>
        <Mono size={9} color={DARK.textFaint} letterSpacing={0.4} style={{ marginTop: 2 }}>
          {silent ? t('SILENT').toUpperCase() : t('MSG/MIN').toUpperCase()}
        </Mono>
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  list: { padding: 16 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 12,
    borderLeftWidth: 3,
  },
});
