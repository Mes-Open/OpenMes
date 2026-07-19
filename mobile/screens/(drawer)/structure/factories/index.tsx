/**
 * Factories — 1:1 with the web admin factories page (Pages/admin/factories/
 * Index.jsx): the shared DataTable (search + Columns menu + pagination) with
 * the web's column set (Code / Name / Divisions / Status) and per-row actions
 * (Edit / toggle-active / Delete). Data via REST useFactories.
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
import { useDeleteFactory, useFactories, useToggleFactoryActive } from '@/hooks/queries/useOrgStructure';
import type { Factory } from '@/api/orgStructure';

export function FactoriesList() {
  const { t } = useTranslation();
  const router = useRouter();

  const q = useFactories(true);
  const toggle = useToggleFactoryActive();
  const del = useDeleteFactory();
  const rows = q.data ?? [];

  const onDelete = (factory: Factory) =>
    Alert.alert(t('Delete factory'), t('Delete "{{name}}"?', { name: factory.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(factory.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
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
        <Text style={styles.h1}>{t('Factories')}</Text>
        <View style={{ flex: 1 }} />
        <Button title={t('+ New Factory')} size="sm" onPress={() => router.push('/structure/factories/new' as never)} />
      </View>

      <DataTable<Factory>
        data={rows as unknown as Factory[]}
        searchPlaceholder={t('Search…')}
        columnsLabel={t('Columns')}
        columnsMenuLabel={t('Toggle columns')}
        searchKeys={['code', 'name']}
        emptyText={t('No factories yet.')}
        onRowPress={(f) => router.push(`/structure/factories/${f.id}` as never)}
        columns={[
          {
            key: 'code',
            label: t('Code'),
            width: 110,
            render: (f) => <Mono size={11} color={colors.muted}>{f.code ?? '—'}</Mono>,
          },
          {
            key: 'name',
            label: t('Name'),
            flex: 1.4,
            render: (f) => <Text numberOfLines={1} style={styles.name}>{f.name}</Text>,
          },
          { key: 'divisions_count', label: t('Divisions'), width: 100, render: (f) => String(f.divisions_count ?? 0) },
          {
            key: 'status',
            label: t('Status'),
            width: 90,
            render: (f) => (
              <StatusPill status={f.is_active === false ? 'CANCELLED' : 'IN_PROGRESS'} label={f.is_active === false ? t('Inactive') : t('Active')} />
            ),
          },
        ]}
        actions={(f) => [
          { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/structure/factories/${f.id}` as never) },
          {
            label: f.is_active === false ? t('Activate') : t('Deactivate'),
            icon: f.is_active === false ? 'activate' : 'deactivate',
            onPress: () => toggle.mutate(f.id, { onError: (e: Error) => Alert.alert(t('Could not update'), e.message) }),
          },
          { label: t('Delete'), icon: 'delete', onPress: () => onDelete(f) },
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
