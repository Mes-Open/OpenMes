/**
 * Line Statuses — the global status catalog (line_id = null) that backs every
 * production line's status options. Mirrors the web admin line-statuses inline
 * table (Color / Name / Order / Default). The web edits inline; the mobile twin
 * stays read-only with a per-row Delete action. Read via REST
 * useGlobalLineStatuses; delete via useDeleteLineStatus.
 */
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useDeleteLineStatus, useGlobalLineStatuses } from '@/hooks/queries/useOrgStructure';
import type { LineStatus } from '@/api/orgStructure';

export function LineStatusesScreen() {
  const { t } = useTranslation();
  const q = useGlobalLineStatuses();
  const del = useDeleteLineStatus();
  const rows = q.data ?? [];

  const onDelete = (s: LineStatus) =>
    Alert.alert(t('Delete status'), t('Delete "{{name}}"?', { name: s.name }), [
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
        <Text style={styles.h1}>{t('Line Statuses')}</Text>
      </View>

      <DataTable<LineStatus>
        data={rows as LineStatus[]}
        searchable={false}
        columnToggle={false}
        paginated={false}
        emptyText={t('No line statuses.')}
        columns={[
          { key: 'color', label: t('Color'), width: 56, render: (s) => <View style={[styles.swatch, { backgroundColor: s.color ?? colors.line }]} /> },
          { key: 'name', label: t('Name'), flex: 1, render: (s) => <Text numberOfLines={1} style={styles.name}>{s.name}</Text> },
          { key: 'sort_order', label: t('Order'), width: 72, render: (s) => <Mono size={11} color={colors.muted}>{String(s.sort_order)}</Mono> },
          { key: 'is_default', label: t('Default'), width: 88, render: (s) => (s.is_default ? <Text style={styles.badge}>{t('Default')}</Text> : <Text style={styles.dash}>—</Text>) },
        ]}
        actions={(s) => [{ label: t('Delete'), icon: 'delete', onPress: () => onDelete(s) }]}
      />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, paddingBottom: 32 },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, marginBottom: 14 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  swatch: { width: 22, height: 22, borderRadius: 6, borderWidth: StyleSheet.hairlineWidth, borderColor: colors.line },
  name: { fontSize: 13.5, fontFamily: fonts.sans.native.medium, color: colors.ink },
  badge: { fontSize: 11, fontFamily: fonts.sans.native.medium, color: colors.accent },
  dash: { fontSize: 13, color: colors.faint },
});
