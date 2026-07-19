/**
 * Webhooks — 1:1 with the web admin webhooks page (Pages/admin/webhooks/
 * Index.jsx): the shared DataTable with the web's column set (Name / URL /
 * Events / Status / Last triggered) and per-row actions (edit / toggle-active /
 * delete). Web's Deliveries + Send-test actions are omitted (no mobile API yet).
 * Full CRUD — "New webhook" + row actions. Data via REST useWebhooks.
 */
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useDeleteWebhook, useToggleWebhook, useWebhooks } from '@/hooks/queries/useWebhooks';
import type { Webhook } from '@/api/webhooks';

export function AdminWebhooksScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const q = useWebhooks();
  const toggle = useToggleWebhook();
  const del = useDeleteWebhook();
  const rows = q.data ?? [];

  const onDelete = (w: Webhook) =>
    Alert.alert(t('Delete webhook?'), t('Remove "{{name}}"?', { name: w.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(w.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
      },
    ]);

  if (q.isLoading && !q.data) return <LoadingState />;
  if (q.isError && !q.data) return <ErrorState error={q.error} onRetry={q.refetch} />;

  return (
    <ScrollView
      style={styles.screen}
      contentContainerStyle={styles.content}
      refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} tintColor={colors.accent} />}>
      <View style={styles.head}>
        <View style={{ flex: 1 }}>
          <Text style={styles.h1}>{t('Webhooks')}</Text>
          <Text style={styles.sub}>{t('Outgoing endpoints that POST on subscribed events.')}</Text>
        </View>
        <Button title={t('+ New Webhook')} size="sm" onPress={() => router.push('/(drawer)/admin/webhooks/new' as never)} />
      </View>

      <DataTable<Webhook>
        data={rows as Webhook[]}
        searchPlaceholder={t('Search…')}
        columnsLabel={t('Columns')}
        columnsMenuLabel={t('Toggle columns')}
        searchKeys={['name', 'url']}
        emptyText={t('No webhooks yet.')}
        onRowPress={(w) => router.push(`/(drawer)/admin/webhooks/${w.id}/edit` as never)}
        columns={[
          {
            key: 'name',
            label: t('Name'),
            flex: 1.3,
            render: (w) => <Text numberOfLines={1} style={styles.name}>{w.name}</Text>,
          },
          {
            key: 'url',
            label: t('URL'),
            flex: 1.6,
            render: (w) => <Mono size={11} color={colors.muted}>{w.url}</Mono>,
          },
          { key: 'events', label: t('Events'), width: 80, align: 'right', render: (w) => String(w.events_count ?? 0) },
          {
            key: 'is_active',
            label: t('Status'),
            width: 90,
            render: (w) => (
              <StatusPill status={w.is_active ? 'IN_PROGRESS' : 'CANCELLED'} label={w.is_active ? t('Active') : t('Inactive')} />
            ),
          },
          {
            key: 'last_triggered_at',
            label: t('Last triggered'),
            width: 150,
            render: (w) => (w.last_triggered_at ? new Date(w.last_triggered_at).toLocaleString() : '—'),
          },
        ]}
        actions={(w) => [
          { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/(drawer)/admin/webhooks/${w.id}/edit` as never) },
          {
            label: w.is_active ? t('Deactivate') : t('Activate'),
            icon: w.is_active ? 'deactivate' : 'activate',
            onPress: () => toggle.mutate(w.id, { onError: (e: Error) => Alert.alert(t('Could not update'), e.message) }),
          },
          { label: t('Delete'), icon: 'delete', onPress: () => onDelete(w) },
        ]}
      />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, paddingBottom: 32 },
  head: { flexDirection: 'row', alignItems: 'flex-start', gap: 12, marginBottom: 14 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  sub: { fontSize: 12.5, color: colors.muted, fontFamily: fonts.sans.native.regular, marginTop: 2 },
  name: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
});
