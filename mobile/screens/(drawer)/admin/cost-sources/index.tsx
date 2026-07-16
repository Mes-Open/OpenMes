/**
 * Cost Sources — 1:1 with the web admin/cost-sources table
 * (Pages/admin/cost-sources/Index.jsx): the shared DataTable with the web's
 * column set (Code / Name / Unit Cost / Unit / Status) and per-row actions
 * (Edit / toggle-active / Delete). Data via REST useCostSources (inactive
 * included). The web "Used" count comes from a server prop the mobile REST
 * list doesn't return, so it's omitted.
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
import { useCostSources, useDeleteCostSource, useToggleCostSourceActive } from '@/hooks/queries/useOps';
import type { CostSource } from '@/api/ops';

export function CostSourcesList() {
  const { t } = useTranslation();
  const router = useRouter();

  const query = useCostSources(true);
  const toggle = useToggleCostSourceActive();
  const del = useDeleteCostSource();
  const rows = query.data ?? [];

  const onDelete = (c: CostSource) =>
    Alert.alert(t('Delete cost source'), t('Delete "{{name}}"?', { name: c.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(c.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
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
        <Text style={styles.h1}>{t('Cost Sources')}</Text>
        <View style={{ flex: 1 }} />
        <Button title={t('+ New Cost Source')} size="sm" onPress={() => router.push('/admin/cost-sources/new' as never)} />
      </View>

      <DataTable<CostSource>
        data={rows as CostSource[]}
        searchPlaceholder={t('Search…')}
        columnsLabel={t('Columns')}
        columnsMenuLabel={t('Toggle columns')}
        searchKeys={['code', 'name']}
        emptyText={t('No cost sources yet.')}
        onRowPress={(c) => router.push(`/admin/cost-sources/${c.id}` as never)}
        columns={[
          { key: 'code', label: t('Code'), width: 96, render: (c) => <Mono size={11} color={colors.muted}>{c.code}</Mono> },
          { key: 'name', label: t('Name'), flex: 1.4, render: (c) => <Text numberOfLines={1} style={styles.name}>{c.name}</Text> },
          {
            key: 'unit_cost',
            label: t('Unit Cost'),
            width: 110,
            render: (c) => (
              <Mono size={11} color={colors.muted}>
                {c.unit_cost != null ? `${c.unit_cost} ${c.currency ?? ''}`.trim() : '—'}
              </Mono>
            ),
          },
          { key: 'unit', label: t('Unit'), width: 72, render: (c) => c.unit ?? '—' },
          {
            key: 'is_active',
            label: t('Status'),
            width: 90,
            render: (c) => (
              <StatusPill status={c.is_active === false ? 'CANCELLED' : 'IN_PROGRESS'} label={c.is_active === false ? t('Inactive') : t('Active')} />
            ),
          },
        ]}
        actions={(c) => [
          { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/admin/cost-sources/${c.id}` as never) },
          {
            label: c.is_active === false ? t('Activate') : t('Deactivate'),
            icon: c.is_active === false ? 'activate' : 'deactivate',
            onPress: () => toggle.mutate(c.id, { onError: (e: Error) => Alert.alert(t('Could not update'), e.message) }),
          },
          { label: t('Delete'), icon: 'delete', onPress: () => onDelete(c) },
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
