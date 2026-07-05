/**
 * Anomaly Reasons — 1:1 with the web admin/anomaly-reasons table
 * (Pages/admin/anomaly-reasons/Index.jsx): the shared DataTable with the web's
 * column set (Code / Name / Category / Status) and per-row actions (Edit /
 * Delete). The web toggle-active and "Used" count have no mobile REST
 * counterpart, so they're omitted. Data via REST useAnomalyReasons.
 */
import { useRouter } from 'expo-router';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useAnomalyReasons, useDeleteAnomalyReason } from '@/hooks/queries/useOps';
import type { AnomalyReason } from '@/api/ops';

export function AnomalyReasonsList() {
  const { t } = useTranslation();
  const router = useRouter();

  const query = useAnomalyReasons({ include_inactive: true });
  const del = useDeleteAnomalyReason();
  const rows = query.data ?? [];

  const onDelete = (r: AnomalyReason) =>
    Alert.alert(t('Delete anomaly reason'), t('Delete "{{name}}"?', { name: r.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(r.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
      },
    ]);

  if (query.isLoading && !query.data) return <LoadingState />;
  if (query.isError && !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;

  return (
    <ScrollView
      style={styles.screen}
      contentContainerStyle={styles.content}
      refreshControl={<RefreshControl refreshing={query.isFetching} onRefresh={query.refetch} tintColor={colors.accent} />}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Anomaly Reasons')}</Text>
        <View style={{ flex: 1 }} />
        <Button title={t('+ New Reason')} size="sm" onPress={() => router.push('/admin/anomaly-reasons/new' as never)} />
      </View>

      <DataTable<AnomalyReason>
        data={rows as AnomalyReason[]}
        searchPlaceholder={t('Search…')}
        columnsLabel={t('Columns')}
        columnsMenuLabel={t('Toggle columns')}
        searchKeys={['code', 'name']}
        emptyText={t('No anomaly reasons yet.')}
        onRowPress={(r) => router.push(`/admin/anomaly-reasons/${r.id}` as never)}
        columns={[
          { key: 'code', label: t('Code'), width: 96, render: (r) => <Mono size={11} color={colors.muted}>{r.code}</Mono> },
          { key: 'name', label: t('Name'), flex: 1.4, render: (r) => <Text numberOfLines={1} style={styles.name}>{r.name}</Text> },
          { key: 'category', label: t('Category'), flex: 1, render: (r) => r.category ?? '—' },
          {
            key: 'is_active',
            label: t('Status'),
            width: 90,
            render: (r) => (
              <StatusPill status={r.is_active === false ? 'CANCELLED' : 'IN_PROGRESS'} label={r.is_active === false ? t('Inactive') : t('Active')} />
            ),
          },
        ]}
        actions={(r) => [
          { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/admin/anomaly-reasons/${r.id}` as never) },
          { label: t('Delete'), icon: 'delete', onPress: () => onDelete(r) },
        ]}
      />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, paddingBottom: 32 },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, marginBottom: 14 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  name: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
});
