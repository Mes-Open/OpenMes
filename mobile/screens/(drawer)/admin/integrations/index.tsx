/**
 * Integrations — 1:1 with the web admin integrations page
 * (Pages/admin/integrations/Index.jsx): the shared DataTable with the web's
 * column set (Type / Name / Materials / Status) and per-row actions (edit /
 * delete). Full CRUD — "New integration" + Edit/Delete row actions. Data via
 * REST useIntegrations.
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
import { useDeleteIntegration, useIntegrations } from '@/hooks/queries/useAdminConfig';
import type { Integration } from '@/api/adminConfig';

export function IntegrationsScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const q = useIntegrations();
  const del = useDeleteIntegration();
  const rows = q.data ?? [];

  const onDelete = (c: Integration) =>
    Alert.alert(t('Delete integration?'), c.system_name, [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(c.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
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
        <Text style={styles.h1}>{t('Integrations')}</Text>
        <View style={{ flex: 1 }} />
        <Button title={t('+ New Integration')} size="sm" onPress={() => router.push('/(drawer)/admin/integrations/new' as never)} />
      </View>

      <DataTable<Integration>
        data={rows as Integration[]}
        searchPlaceholder={t('Search…')}
        columnsLabel={t('Columns')}
        columnsMenuLabel={t('Toggle columns')}
        searchKeys={['system_name', 'system_type']}
        emptyText={t('No integrations configured.')}
        onRowPress={(c) => router.push(`/(drawer)/admin/integrations/${c.id}/edit` as never)}
        columns={[
          {
            key: 'system_type',
            label: t('Type'),
            width: 120,
            render: (c) => <Mono size={10} color={colors.muted}>{c.system_type.toUpperCase()}</Mono>,
          },
          {
            key: 'system_name',
            label: t('Name'),
            flex: 1.4,
            render: (c) => <Text numberOfLines={1} style={styles.name}>{c.system_name}</Text>,
          },
          { key: 'materials', label: t('Materials'), width: 100, render: (c) => String(c.material_sources_count ?? 0) },
          {
            key: 'is_active',
            label: t('Status'),
            width: 90,
            render: (c) => (
              <StatusPill status={c.is_active ? 'running' : 'cancelled'} label={c.is_active ? t('Active') : t('Inactive')} />
            ),
          },
        ]}
        actions={(c) => [
          { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/(drawer)/admin/integrations/${c.id}/edit` as never) },
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
