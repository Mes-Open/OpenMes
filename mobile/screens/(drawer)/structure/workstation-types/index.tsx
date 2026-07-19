/**
 * Workstation Types — 1:1 with the web admin workstation-types page (Pages/
 * admin/workstation-types/Index.jsx): the shared DataTable (search + Columns
 * menu + pagination) with the web's column set (Code / Name / Workstations /
 * Status) and per-row actions (Edit / toggle-active / Delete). Data via REST
 * useWorkstationTypes.
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
import { useWorkstationTypes } from '@/hooks/queries/useWorkstationTypes';
import { useDeleteWorkstationType, useToggleWorkstationTypeActive } from '@/hooks/mutations/workstationTypes';
import type { WorkstationType } from '@/api/workstationTypes';

export function WorkstationTypesList() {
  const { t } = useTranslation();
  const router = useRouter();

  const query = useWorkstationTypes({ include_inactive: true });
  const toggle = useToggleWorkstationTypeActive();
  const del = useDeleteWorkstationType();
  const rows = query.data ?? [];

  const onDelete = (wt: WorkstationType) =>
    Alert.alert(t('Delete workstation type'), t('Delete "{{name}}"?', { name: wt.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(wt.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
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
        <Text style={styles.h1}>{t('Workstation Types')}</Text>
        <View style={{ flex: 1 }} />
        <Button title={t('+ New Type')} size="sm" onPress={() => router.push('/structure/workstation-types/new' as never)} />
      </View>

      <DataTable<WorkstationType>
        data={rows as unknown as WorkstationType[]}
        searchPlaceholder={t('Search…')}
        columnsLabel={t('Columns')}
        columnsMenuLabel={t('Toggle columns')}
        searchKeys={['code', 'name']}
        emptyText={t('No workstation types yet.')}
        onRowPress={(wt) => router.push(`/structure/workstation-types/${wt.id}` as never)}
        columns={[
          {
            key: 'code',
            label: t('Code'),
            width: 110,
            render: (wt) => <Mono size={11} color={colors.muted}>{wt.code ?? '—'}</Mono>,
          },
          {
            key: 'name',
            label: t('Name'),
            flex: 1.4,
            render: (wt) => <Text numberOfLines={1} style={styles.name}>{wt.name}</Text>,
          },
          { key: 'workstations_count', label: t('Workstations'), width: 110, render: (wt) => String(wt.workstations_count ?? 0) },
          {
            key: 'status',
            label: t('Status'),
            width: 90,
            render: (wt) => (
              <StatusPill status={wt.is_active === false ? 'CANCELLED' : 'IN_PROGRESS'} label={wt.is_active === false ? t('Inactive') : t('Active')} />
            ),
          },
        ]}
        actions={(wt) => [
          { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/structure/workstation-types/${wt.id}` as never) },
          {
            label: wt.is_active === false ? t('Activate') : t('Deactivate'),
            icon: wt.is_active === false ? 'activate' : 'deactivate',
            onPress: () => toggle.mutate(wt.id, { onError: (e: Error) => Alert.alert(t('Could not update'), e.message) }),
          },
          { label: t('Delete'), icon: 'delete', onPress: () => onDelete(wt) },
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
