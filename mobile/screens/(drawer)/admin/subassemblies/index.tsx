/**
 * Subassemblies — 1:1 with the web admin/subassemblies table
 * (Pages/admin/subassemblies/Index.jsx): the shared DataTable with the web's
 * column set (Code / Name / Product Type / Status) and per-row actions (Edit /
 * Delete). The web toggle-active has no mobile REST counterpart, so it's
 * omitted. Data via REST useSubassemblies (inactive included).
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
import { useSubassemblies, useDeleteSubassembly } from '@/hooks/queries/useOps';
import type { Subassembly } from '@/api/ops';

export function SubassembliesList() {
  const { t } = useTranslation();
  const router = useRouter();

  const query = useSubassemblies({ include_inactive: true });
  const del = useDeleteSubassembly();
  const rows = query.data ?? [];

  const onDelete = (s: Subassembly) =>
    Alert.alert(t('Delete subassembly'), t('Delete "{{name}}"?', { name: s.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(s.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
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
        <Text style={styles.h1}>{t('Subassemblies')}</Text>
        <View style={{ flex: 1 }} />
        <Button title={t('+ New Subassembly')} size="sm" onPress={() => router.push('/admin/subassemblies/new' as never)} />
      </View>

      <DataTable<Subassembly>
        data={rows as Subassembly[]}
        searchPlaceholder={t('Search…')}
        columnsLabel={t('Columns')}
        columnsMenuLabel={t('Toggle columns')}
        searchKeys={['code', 'name']}
        emptyText={t('No subassemblies yet.')}
        onRowPress={(s) => router.push(`/admin/subassemblies/${s.id}` as never)}
        columns={[
          { key: 'code', label: t('Code'), width: 96, render: (s) => <Mono size={11} color={colors.muted}>{s.code}</Mono> },
          { key: 'name', label: t('Name'), flex: 1.4, render: (s) => <Text numberOfLines={1} style={styles.name}>{s.name}</Text> },
          { key: 'product_type', label: t('Product Type'), flex: 1.2, render: (s) => s.product_type?.name ?? '—' },
          {
            key: 'is_active',
            label: t('Status'),
            width: 90,
            render: (s) => (
              <StatusPill status={s.is_active === false ? 'CANCELLED' : 'IN_PROGRESS'} label={s.is_active === false ? t('Inactive') : t('Active')} />
            ),
          },
        ]}
        actions={(s) => [
          { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/admin/subassemblies/${s.id}` as never) },
          { label: t('Delete'), icon: 'delete', onPress: () => onDelete(s) },
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
