/**
 * Process Segments — 1:1 with the web admin process-segments table
 * (Pages/admin/process-segments/Index.jsx): the shared DataTable (search +
 * Columns menu) with the web's column set (Code / Name / Type / Workstation
 * Type / Operators / Status) and per-row actions (Edit / Delete). Data via REST.
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
import { useDeleteProcessSegment, useProcessSegments } from '@/hooks/queries/useProcessSegments';
import type { ProcessSegment } from '@/api/processSegments';

const humanize = (s: string) => s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

export function ProcessSegmentsList() {
  const { t } = useTranslation();
  const router = useRouter();
  const q = useProcessSegments({ per_page: 100 });
  const del = useDeleteProcessSegment();
  const rows = q.data?.data ?? [];

  const onDelete = (s: ProcessSegment) =>
    Alert.alert(t('Delete process segment?'), s.name, [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(s.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
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
        <Text style={styles.h1}>{t('Process Segments')}</Text>
        <View style={{ flex: 1 }} />
        <Button title={t('+ New Segment')} size="sm" onPress={() => router.push('/production/process-segments/new' as never)} />
      </View>

      <DataTable<ProcessSegment>
        data={rows as unknown as ProcessSegment[]}
        searchPlaceholder={t('Search…')}
        columnsLabel={t('Columns')}
        columnsMenuLabel={t('Toggle columns')}
        searchKeys={['code', 'name']}
        emptyText={t('No process segments yet.')}
        onRowPress={(s) => router.push(`/production/process-segments/${s.id}/edit` as never)}
        columns={[
          {
            key: 'code',
            label: t('Code'),
            width: 96,
            render: (s) => <Mono size={11} color={colors.muted}>{s.code}</Mono>,
          },
          {
            key: 'name',
            label: t('Name'),
            flex: 1.4,
            render: (s) => <Text numberOfLines={1} style={styles.name}>{s.name}</Text>,
          },
          {
            key: 'segment_type',
            label: t('Type'),
            width: 96,
            render: (s) => (s.segment_type ? humanize(String(s.segment_type)) : '—'),
          },
          {
            key: 'workstation_type',
            label: t('Workstation Type'),
            flex: 1,
            render: (s) => s.workstationType?.name ?? '—',
          },
          {
            key: 'required_operators',
            label: t('Operators'),
            width: 72,
            render: (s) => <Mono size={11} color={colors.muted}>{s.required_operators != null ? String(s.required_operators) : '—'}</Mono>,
          },
          {
            key: 'status',
            label: t('Status'),
            width: 90,
            render: (s) => <StatusPill status={s.is_active ? 'IN_PROGRESS' : 'CANCELLED'} label={s.is_active ? t('Active') : t('Inactive')} />,
          },
        ]}
        actions={(s) => [
          { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/production/process-segments/${s.id}/edit` as never) },
          { label: t('Delete'), icon: 'delete', variant: 'danger', onPress: () => onDelete(s) },
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
