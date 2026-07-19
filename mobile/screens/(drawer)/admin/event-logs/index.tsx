/**
 * Event logs — the system event feed as a table (When / Event / Description /
 * User) via the shared DataTable, with a category filter beside the built-in
 * search. There is no dedicated web page; this follows the admin log-table
 * style. Read via useEventLogs.
 */
import { format, parseISO } from 'date-fns';
import { useMemo, useState } from 'react';
import { RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useEventLogs } from '@/hooks/queries/useEventLogs';
import type { EventLog } from '@/api/eventLogs';

type FilterId = 'all' | 'work_orders' | 'mqtt' | 'auth' | 'system';

function categorize(eventType: string): FilterId | 'unknown' {
  const t = eventType.toUpperCase();
  if (t.startsWith('WO_') || t.startsWith('BATCH_') || t.startsWith('WORK_ORDER')) return 'work_orders';
  if (t.startsWith('MQTT_') || t.startsWith('CONNECTION_') || t === 'NEXO_SYNC') return 'mqtt';
  if (t.startsWith('USER_') || t.startsWith('AUTH_') || t === 'LOGIN' || t === 'LOGOUT') return 'auth';
  if (t.includes('DOWNTIME') || t.includes('ISSUE') || t.includes('SYSTEM')) return 'system';
  return 'unknown';
}

const CATEGORY_COLOR: Record<string, string> = {
  work_orders: colors.accent,
  mqtt: colors.running,
  auth: colors.done,
  system: colors.downtime,
  unknown: colors.muted,
};

export function EventLogsScreen() {
  const { t } = useTranslation();
  const [filter, setFilter] = useState<FilterId>('all');

  const query = useEventLogs({ per_page: 50 });
  const all = query.data?.data ?? [];

  const rows = useMemo(
    () => (filter === 'all' ? all : all.filter((e) => categorize(e.event_type) === filter)),
    [all, filter],
  );

  const options = useMemo(
    () => [
      { value: 'all', label: t('All') },
      { value: 'work_orders', label: t('Work orders') },
      { value: 'mqtt', label: 'MQTT' },
      { value: 'auth', label: t('Auth') },
      { value: 'system', label: t('System') },
    ],
    [t],
  );

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Event Logs')}</Text>
        <View style={{ flex: 1 }} />
        <View style={{ width: 160 }}>
          <Dropdown value={filter} onChange={(v) => setFilter(v as FilterId)} placeholder={t('All')} options={options} />
        </View>
      </View>

      {query.isLoading && !query.data ? (
        <LoadingState />
      ) : query.isError && !query.data ? (
        <ErrorState error={query.error} onRetry={query.refetch} />
      ) : (
        <ScrollView
          style={styles.screen}
          contentContainerStyle={styles.tableWrap}
          refreshControl={<RefreshControl refreshing={query.isFetching} onRefresh={query.refetch} tintColor={colors.accent} />}>
          <DataTable<EventLog>
            data={rows as EventLog[]}
            searchPlaceholder={t('Filter by event type or entity')}
            columnsLabel={t('Columns')}
            columnsMenuLabel={t('Toggle columns')}
            searchKeys={['event_type', 'entity_type', 'description']}
            emptyText={t('No events match these filters')}
            columns={[
              {
                key: 'when',
                label: t('When'),
                width: 84,
                render: (item) => {
                  try {
                    return <Mono size={10} color={colors.faint}>{format(parseISO(item.created_at), 'HH:mm:ss')}</Mono>;
                  } catch {
                    return '—';
                  }
                },
              },
              {
                key: 'event',
                label: t('Event'),
                flex: 1.2,
                render: (item) => (
                  <Mono size={10} color={CATEGORY_COLOR[categorize(item.event_type)] ?? colors.muted} letterSpacing={0.4} numberOfLines={1}>
                    {item.event_type.toUpperCase()}
                  </Mono>
                ),
              },
              {
                key: 'description',
                label: t('Description'),
                flex: 1.4,
                render: (item) => (
                  <Text numberOfLines={2} style={styles.cellText}>{item.description || `${item.entity_type} #${item.entity_id}`}</Text>
                ),
              },
              { key: 'user', label: t('User'), width: 92, render: (item) => item.user?.username ?? 'system' },
            ]}
          />
        </ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  tableWrap: { paddingHorizontal: 14, paddingBottom: 24 },
  cellText: { fontSize: 12.5, color: colors.muted, fontFamily: fonts.sans.native.regular },
});
