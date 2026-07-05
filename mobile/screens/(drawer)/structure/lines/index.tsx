/**
 * Production lines — 1:1 with the web admin lines page (Pages/admin/lines/
 * Index.jsx): the shared DataTable (search + Columns menu + pagination) with
 * the web's column set (Code / Name / Area / Stations / Work Orders /
 * Operators / Status) and per-row actions (Configure + edit / toggle-active /
 * delete). Data via REST useAdminLines (counts + area come from the API).
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
import { useAdminLines, useDeleteLine, useToggleLineActive } from '@/hooks/queries/useLines';
import type { Line } from '@/types/api';

export function LinesList() {
  const { t } = useTranslation();
  const router = useRouter();

  const q = useAdminLines({ include_inactive: true });
  const toggle = useToggleLineActive();
  const del = useDeleteLine();
  const rows = q.data ?? [];

  const onDelete = (line: Line) =>
    Alert.alert(t('Delete line'), t('Delete "{{name}}"?', { name: line.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(line.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
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
        <Text style={styles.h1}>{t('Production Lines')}</Text>
        <View style={{ flex: 1 }} />
        <Button title={t('+ New Line')} size="sm" onPress={() => router.push('/structure/lines/new' as never)} />
      </View>

      <DataTable<Line>
        data={rows as unknown as Line[]}
        searchPlaceholder={t('Search…')}
        columnsLabel={t('Columns')}
        columnsMenuLabel={t('Toggle columns')}
        searchKeys={['code', 'name']}
        emptyText={t('No production lines yet.')}
        onRowPress={(line) => router.push(`/structure/lines/${line.id}` as never)}
        columns={[
          {
            key: 'code',
            label: t('Code'),
            width: 110,
            render: (l) => <Mono size={11} color={colors.muted}>{l.code ?? '—'}</Mono>,
          },
          {
            key: 'name',
            label: t('Name'),
            flex: 1.4,
            render: (l) => <Text numberOfLines={1} style={styles.name}>{l.name}</Text>,
          },
          { key: 'area', label: t('Area'), flex: 1, render: (l) => l.area?.name ?? '—' },
          { key: 'workstations_count', label: t('Stations'), width: 80, render: (l) => String(l.workstations_count ?? 0) },
          { key: 'work_orders_count', label: t('Work Orders'), width: 110, render: (l) => String(l.work_orders_count ?? 0) },
          { key: 'users_count', label: t('Operators'), width: 90, render: (l) => String(l.users_count ?? 0) },
          {
            key: 'status',
            label: t('Status'),
            width: 90,
            render: (l) => (
              <StatusPill status={l.is_active === false ? 'CANCELLED' : 'IN_PROGRESS'} label={l.is_active === false ? t('Inactive') : t('Active')} />
            ),
          },
        ]}
        actions={(l) => [
          { label: t('Configure'), onPress: () => router.push(`/structure/lines/${l.id}` as never) },
          { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/structure/lines/${l.id}` as never) },
          {
            label: l.is_active === false ? t('Activate') : t('Deactivate'),
            icon: l.is_active === false ? 'activate' : 'deactivate',
            onPress: () => toggle.mutate(l.id, { onError: (e: Error) => Alert.alert(t('Could not update'), e.message) }),
          },
          { label: t('Delete'), icon: 'delete', onPress: () => onDelete(l) },
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
