/**
 * Connections list — 1:1 with the web MQTT connections table
 * (Pages/admin/connectivity/mqtt/Index.jsx): the shared DataTable with the web's
 * column set (Status / Name / Protocol / Topics / Messages / Last) and per-row
 * actions (edit, delete), generalised to every protocol. Read via the unified
 * connections API; rows open the shared connection detail (= View). A filter
 * switches between active-only and all connections.
 */
import { useMemo, useState } from 'react';
import { useRouter } from 'expo-router';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useConnections, useDeleteConnection } from '@/hooks/queries/useConnectivity';
import type { MachineConnection } from '@/api/connectivity';

/** Connection status → one of the design system's status colours. */
function statusColor(status: string): string {
  if (status === 'connected') return colors.running;
  if (status === 'error' || status === 'disconnected') return colors.blocked;
  return colors.downtime;
}

export function ConnectionsList() {
  const router = useRouter();
  const { t } = useTranslation();
  const [scope, setScope] = useState('active');

  const query = useConnections(scope === 'all');
  const del = useDeleteConnection();
  const rows = query.data ?? [];

  const options = useMemo(
    () => [
      { value: 'active', label: t('Active only') },
      { value: 'all', label: t('All connections') },
    ],
    [t],
  );

  const onDelete = (c: MachineConnection) =>
    Alert.alert(t('Delete connection'), t('Delete "{{name}}"?', { name: c.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(c.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
      },
    ]);

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Connections')}</Text>
        <View style={{ flex: 1 }} />
        <View style={{ width: 170 }}>
          <Dropdown value={scope} onChange={(v) => setScope(v as string)} options={options} />
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
          <DataTable<MachineConnection>
            data={rows}
            searchPlaceholder={t('Search connections…')}
            columnsLabel={t('Columns')}
            columnsMenuLabel={t('Toggle columns')}
            searchKeys={['name', 'protocol', 'status']}
            emptyText={t('No connections.')}
            onRowPress={(c) => router.push(`/connectivity/connections/${c.id}` as never)}
            columns={[
              {
                key: 'status',
                label: t('Status'),
                width: 92,
                render: (c) => (
                  <View style={styles.statusCell}>
                    <View style={[styles.dot, { backgroundColor: statusColor(c.status) }]} />
                    <Mono size={9.5} color={colors.muted} numberOfLines={1}>{c.status.toUpperCase()}</Mono>
                  </View>
                ),
              },
              {
                key: 'name',
                label: t('Name'),
                flex: 1.4,
                render: (c) => (
                  <View>
                    <Text numberOfLines={1} style={styles.name}>{c.name}</Text>
                    {c.is_active === false ? <Mono size={9} color={colors.faint}>{t('Inactive').toUpperCase()}</Mono> : null}
                  </View>
                ),
              },
              {
                key: 'protocol',
                label: t('Protocol'),
                width: 80,
                render: (c) => <Mono size={10} color={colors.muted} numberOfLines={1}>{c.protocol.toUpperCase()}</Mono>,
              },
              {
                key: 'topics_count',
                label: t('Topics'),
                width: 64,
                render: (c) => <Mono size={11} color={colors.muted}>{String(c.topics_count ?? 0)}</Mono>,
              },
              {
                key: 'messages_received',
                label: t('Messages'),
                width: 72,
                render: (c) => <Mono size={11} color={colors.muted}>{String(c.messages_received ?? 0)}</Mono>,
              },
              {
                key: 'last_connected_at',
                label: t('Last'),
                width: 64,
                render: (c) => <Mono size={10} color={colors.faint}>{c.last_connected_at ? c.last_connected_at.slice(11, 16) : '—'}</Mono>,
              },
            ]}
            actions={(c) => [
              { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/connectivity/connections/${c.id}/edit` as never) },
              { label: t('Delete'), icon: 'delete', variant: 'danger', onPress: () => onDelete(c) },
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
  statusCell: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  name: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  dot: { width: 8, height: 8, borderRadius: 4 },
});
