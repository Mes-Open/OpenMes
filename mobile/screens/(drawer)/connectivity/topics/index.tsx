/**
 * Topics list — the subscribed-topic table behind every connection
 * (Topic / Format / Rate). The web renders topics as cards inside the connection
 * Show page; this is the mobile table view, on the shared DataTable, with the
 * same per-topic actions (edit, delete). Rate + the "silent" flag are derived
 * client-side from the recent message tail (no per-topic rate endpoint). Rows
 * open the topic detail; a search box filters by pattern or connection.
 */
import { useMemo, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import { SearchField } from '@openmes/ui/native';

import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useDeleteTopic, useMessages, useTopics } from '@/hooks/queries/useConnectivity';
import type { MachineTopic } from '@/api/connectivity';

export function TopicsList() {
  const router = useRouter();
  const { t } = useTranslation();
  const params = useLocalSearchParams<{ machine_connection_id?: string }>();
  const connId = params.machine_connection_id ? Number(params.machine_connection_id) : undefined;
  const [search, setSearch] = useState('');

  const topicsQ = useTopics({ machine_connection_id: connId });
  const messagesQ = useMessages({ machine_connection_id: connId, per_page: 200 });
  const del = useDeleteTopic();

  // Per-topic stats from the recent message tail (rate + silent flag).
  const stats = useMemo(() => {
    const out: Record<string, { count: number; newest: number }> = {};
    for (const m of messagesQ.data?.data ?? []) {
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
      `${tp.topic_pattern} ${tp.machine_connection?.name ?? ''}`.toLowerCase().includes(q),
    );
  }, [topicsQ.data, search]);

  const onDelete = (tp: MachineTopic) =>
    Alert.alert(t('Delete topic'), t('Delete "{{name}}"?', { name: tp.topic_pattern }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(tp.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
      },
    ]);

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Topics')}</Text>
      </View>

      <View style={styles.filters}>
        <SearchField value={search} onChange={setSearch} placeholder={t('Search topics')} />
      </View>

      {topicsQ.isLoading && !topicsQ.data ? (
        <LoadingState />
      ) : topicsQ.isError && !topicsQ.data ? (
        <ErrorState error={topicsQ.error} onRetry={topicsQ.refetch} />
      ) : (
        <ScrollView
          style={styles.screen}
          contentContainerStyle={styles.tableWrap}
          refreshControl={<RefreshControl refreshing={topicsQ.isFetching} onRefresh={topicsQ.refetch} tintColor={colors.accent} />}>
          <DataTable<MachineTopic>
            data={items}
            searchable={false}
            columnsLabel={t('Columns')}
            columnsMenuLabel={t('Toggle columns')}
            emptyText={t('No topics.')}
            onRowPress={(tp) => router.push(`/connectivity/topics/${tp.id}` as never)}
            columns={[
              {
                key: 'topic',
                label: t('Topic'),
                flex: 1.6,
                render: (tp) => {
                  const connName = tp.machine_connection?.name ?? `#${tp.machine_connection_id}`;
                  return (
                    <View>
                      <Mono size={11.5} color={colors.ink} numberOfLines={1}>{tp.topic_pattern}</Mono>
                      <Mono size={9} color={colors.faint} letterSpacing={0.3}>{connName.toUpperCase()}</Mono>
                    </View>
                  );
                },
              },
              {
                key: 'format',
                label: t('Format'),
                width: 80,
                render: (tp) => <Mono size={10} color={colors.muted}>{(tp.payload_format ?? '—').toUpperCase()}</Mono>,
              },
              {
                key: 'rate',
                label: t('Rate'),
                width: 72,
                render: (tp) => {
                  const stat = stats[tp.topic_pattern];
                  const silent = !stat || stat.newest < Date.now() - 5 * 60_000;
                  const rate = stat ? Math.round(stat.count / 5) : 0;
                  return (
                    <View>
                      <Mono size={12} color={silent ? colors.blocked : colors.running}>{silent ? '0' : String(rate)}</Mono>
                      <Mono size={8} color={colors.faint} letterSpacing={0.4}>{silent ? t('Silent').toUpperCase() : t('Msg/min').toUpperCase()}</Mono>
                    </View>
                  );
                },
              },
            ]}
            actions={(tp) => [
              { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/connectivity/topics/${tp.id}/edit` as never) },
              { label: t('Delete'), icon: 'delete', variant: 'danger', onPress: () => onDelete(tp) },
            ]}
          />
        </ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingTop: 16, paddingBottom: 8 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  filters: { paddingHorizontal: 18, paddingBottom: 10 },
  tableWrap: { paddingHorizontal: 14, paddingBottom: 24 },
});
