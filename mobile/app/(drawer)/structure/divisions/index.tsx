/**
 * Divisions — global list across all factories, 1:1 with the web admin
 * divisions page (Pages/admin/divisions/Index.jsx): shared DataTable with the
 * web's columns (Code / Name / Factory / Crews / Status) and row actions
 * (Edit / toggle-active / Delete). Data via GET /api/v1/divisions.
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
import {
  useAllDivisions,
  useDeleteDivision,
  useToggleDivisionActive,
} from '@/hooks/queries/useOrgStructure';
import type { Division } from '@/api/orgStructure';

export default function DivisionsPage() {
  const { t } = useTranslation();
  const router = useRouter();
  const q = useAllDivisions(true);
  const toggle = useToggleDivisionActive();
  const del = useDeleteDivision();
  const rows = q.data ?? [];

  const onDelete = (d: Division) =>
    Alert.alert(t('Delete division'), t('Delete "{{name}}"?', { name: d.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(d.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
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
        <Text style={styles.h1}>{t('Divisions')}</Text>
        <View style={{ flex: 1 }} />
        <Button title={t('+ New Division')} size="sm" onPress={() => router.push('/structure/divisions/new' as never)} />
      </View>

      <DataTable<Division>
        data={rows as Division[]}
        searchPlaceholder={t('Search…')}
        columnsLabel={t('Columns')}
        columnsMenuLabel={t('Toggle columns')}
        searchKeys={['code', 'name']}
        emptyText={t('No divisions yet.')}
        onRowPress={(d) => router.push(`/structure/divisions/${d.id}` as never)}
        columns={[
          { key: 'code', label: t('Code'), width: 110, render: (d) => <Mono size={11} color={colors.muted}>{d.code}</Mono> },
          {
            key: 'name',
            label: t('Name'),
            flex: 1.3,
            render: (d) => <Text numberOfLines={1} style={styles.name}>{d.name}</Text>,
          },
          { key: 'factory', label: t('Factory'), flex: 1, render: (d) => d.factory?.name ?? '—' },
          { key: 'crews_count', label: t('Crews'), width: 80, render: (d) => String(d.crews_count ?? 0) },
          {
            key: 'is_active',
            label: t('Status'),
            width: 90,
            render: (d) => (
              <StatusPill status={d.is_active ? 'IN_PROGRESS' : 'CANCELLED'} label={d.is_active ? t('Active') : t('Inactive')} />
            ),
          },
        ]}
        actions={(d) => [
          { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/structure/divisions/${d.id}` as never) },
          {
            label: d.is_active ? t('Deactivate') : t('Activate'),
            icon: d.is_active ? 'deactivate' : 'activate',
            onPress: () => toggle.mutate(d.id, { onError: (e: Error) => Alert.alert(t('Could not update'), e.message) }),
          },
          { label: t('Delete'), icon: 'delete', onPress: () => onDelete(d) },
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
