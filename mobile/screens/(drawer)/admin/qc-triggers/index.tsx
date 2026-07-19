/**
 * Quality-control triggers (#105) — 1:1 with the web admin page
 * (Pages/admin/quality-control-triggers/Index.jsx): the shared DataTable with
 * the web's column set (Name / Type / N / Control / Scope / Blocking / Status)
 * and per-row actions (edit / toggle-active / delete). Full CRUD — "New
 * trigger" + row actions. Data via REST useQcTriggers.
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
import { useDeleteQcTrigger, useQcTriggers, useToggleQcTrigger } from '@/hooks/queries/useAdminConfig';
import type { QcTrigger } from '@/api/adminConfig';

const humanize = (s: string) => s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

export function QcTriggersScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const q = useQcTriggers();
  const toggle = useToggleQcTrigger();
  const del = useDeleteQcTrigger();
  const rows = q.data ?? [];

  const onDelete = (tr: QcTrigger) =>
    Alert.alert(t('Delete QC trigger?'), tr.name, [
      { text: t('Cancel'), style: 'cancel' },
      { text: t('Delete'), style: 'destructive', onPress: () => del.mutate(tr.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }) },
    ]);

  if (q.isLoading && !q.data) return <LoadingState />;
  if (q.isError && !q.data) return <ErrorState error={q.error} onRetry={q.refetch} />;

  return (
    <ScrollView
      style={styles.screen}
      contentContainerStyle={styles.content}
      refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} tintColor={colors.accent} />}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('QC Triggers')}</Text>
        <View style={{ flex: 1 }} />
        <Button title={t('+ New Trigger')} size="sm" onPress={() => router.push('/(drawer)/admin/qc-triggers/new' as never)} />
      </View>

      <DataTable<QcTrigger>
        data={rows as QcTrigger[]}
        searchPlaceholder={t('Search…')}
        columnsLabel={t('Columns')}
        columnsMenuLabel={t('Toggle columns')}
        searchKeys={['name', 'trigger_type', 'scope']}
        emptyText={t('No quality control triggers yet.')}
        onRowPress={(tr) => router.push(`/(drawer)/admin/qc-triggers/${tr.id}/edit` as never)}
        columns={[
          {
            key: 'name',
            label: t('Name'),
            flex: 1.3,
            render: (tr) => <Text numberOfLines={1} style={styles.name}>{tr.name}</Text>,
          },
          {
            key: 'trigger_type',
            label: t('Type'),
            width: 110,
            render: (tr) => <Mono size={9} color={colors.muted}>{humanize(tr.trigger_type).toUpperCase()}</Mono>,
          },
          { key: 'threshold_n', label: t('N'), width: 60, align: 'right', render: (tr) => (tr.threshold_n ?? '—') },
          { key: 'template', label: t('Control'), flex: 1, render: (tr) => tr.template_name ?? '—' },
          { key: 'scope', label: t('Scope'), flex: 1, render: (tr) => tr.scope ?? t('Any') },
          { key: 'is_blocking', label: t('Blocking'), width: 78, render: (tr) => (tr.is_blocking ? t('Yes') : t('No')) },
          {
            key: 'is_active',
            label: t('Status'),
            width: 80,
            render: (tr) => (
              <StatusPill status={tr.is_active ? 'running' : 'cancelled'} label={tr.is_active ? t('Active') : t('Inactive')} />
            ),
          },
        ]}
        actions={(tr) => [
          { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/(drawer)/admin/qc-triggers/${tr.id}/edit` as never) },
          {
            label: tr.is_active ? t('Deactivate') : t('Activate'),
            icon: tr.is_active ? 'deactivate' : 'activate',
            onPress: () => toggle.mutate(tr.id, { onError: (e: Error) => Alert.alert(t('Could not update'), e.message) }),
          },
          { label: t('Delete'), icon: 'delete', onPress: () => onDelete(tr) },
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
