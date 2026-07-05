/**
 * Tools — 1:1 with the web admin tools table (Pages/admin/tools/Index.jsx): the
 * shared DataTable with the web's column set (Code / Name / Workstation Type /
 * Status / Next Service) and per-row actions (edit → detail, delete). Keeps the
 * status filter; admins get "+ New Tool"; rows open the tool detail. Data via
 * REST useTools.
 */
import { useMemo, useState } from 'react';
import { useRouter } from 'expo-router';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useDeleteTool, useTools } from '@/hooks/queries/useMaintenance';
import { useAuthStore } from '@/stores/authStore';
import type { Tool, ToolStatus } from '@/api/maintenance';

const STATUSES: ToolStatus[] = ['available', 'in_use', 'maintenance', 'retired'];
const humanize = (s: string) => s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

export function ToolsList() {
  const { t } = useTranslation();
  const router = useRouter();
  const [status, setStatus] = useState('');
  const canCreate = useAuthStore((s) => s.user)?.roles?.some((r) => r.name === 'Admin') ?? false;

  const q = useTools({ status: (status || undefined) as ToolStatus | undefined });
  const del = useDeleteTool();
  const rows = q.data ?? [];

  const options = useMemo(
    () => [{ value: '', label: t('All statuses') }, ...STATUSES.map((s) => ({ value: s, label: humanize(s) }))],
    [t],
  );

  const onDelete = (tool: Tool) =>
    Alert.alert(t('Delete tool'), t('Delete "{{name}}"?', { name: tool.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(tool.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
      },
    ]);

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Tools')}</Text>
        <View style={{ flex: 1 }} />
        <View style={{ width: 170 }}>
          <Dropdown value={status} onChange={(v) => setStatus(v as string)} placeholder={t('All statuses')} options={options} />
        </View>
        {canCreate ? (
          <Button title={t('+ New Tool')} size="sm" onPress={() => router.push('/maintenance/tools/new' as never)} />
        ) : null}
      </View>

      {q.isLoading && !q.data ? (
        <LoadingState />
      ) : q.isError && !q.data ? (
        <ErrorState error={q.error} onRetry={q.refetch} />
      ) : (
        <ScrollView
          style={styles.screen}
          contentContainerStyle={styles.tableWrap}
          refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} tintColor={colors.accent} />}>
          <DataTable<Tool>
            data={rows}
            searchPlaceholder={t('Search…')}
            columnsLabel={t('Columns')}
            columnsMenuLabel={t('Toggle columns')}
            searchKeys={['code', 'name']}
            emptyText={t('No tools yet.')}
            onRowPress={(tool) => router.push(`/maintenance/tools/${tool.id}` as never)}
            columns={[
              {
                key: 'code',
                label: t('Code'),
                width: 110,
                render: (tool) => <Mono size={11} color={colors.muted}>{tool.code}</Mono>,
              },
              {
                key: 'name',
                label: t('Name'),
                flex: 1.4,
                render: (tool) => <Text numberOfLines={1} style={styles.name}>{tool.name}</Text>,
              },
              {
                key: 'type',
                label: t('Workstation Type'),
                flex: 1,
                render: (tool) => tool.workstation_type?.name ?? '—',
              },
              {
                key: 'status',
                label: t('Status'),
                width: 104,
                render: (tool) => <StatusPill status={tool.status} label={humanize(tool.status)} />,
              },
              {
                key: 'next_service_at',
                label: t('Next Service'),
                width: 110,
                render: (tool) => (
                  <Mono size={10} color={colors.muted}>
                    {tool.next_service_at ? String(tool.next_service_at).slice(0, 10) : '—'}
                  </Mono>
                ),
              },
            ]}
            actions={(tool) => [
              { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/maintenance/tools/${tool.id}` as never) },
              { label: t('Delete'), icon: 'delete', variant: 'danger', onPress: () => onDelete(tool) },
            ]}
          />
        </ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  tableWrap: { paddingHorizontal: 14, paddingBottom: 24 },
  name: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
});
