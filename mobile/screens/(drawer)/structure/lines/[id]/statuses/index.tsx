/**
 * Line statuses — the Kanban columns available to work orders on one line.
 * There is no web *table* for this scope (the web line Show page renders
 * these as a colored chip list, and the only web table — admin/line-statuses
 * — is the separate *global* status catalog). Refit to the shared DataTable
 * anyway, mirroring the columns this screen already exposed (Color / Name /
 * Order / Default / Done) so the mobile list gets search + column toggle.
 * Tap "New status" to add a line-specific column; the row action deletes it.
 * Read/write via REST.
 */
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useDeleteLineStatus, useLineStatuses } from '@/hooks/queries/useOrgStructure';
import type { LineStatus } from '@/api/orgStructure';

export function LineStatusesList() {
  const { t } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const lineId = Number(id);
  const router = useRouter();

  const q = useLineStatuses(lineId);
  const del = useDeleteLineStatus();
  const rows = q.data ?? [];

  const onDelete = (status: LineStatus) =>
    Alert.alert(t('Delete status'), status.name, [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(status.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
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
        <View style={{ flex: 1 }} />
        <Button title={t('New status')} size="sm" onPress={() => router.push(`/structure/lines/${lineId}/statuses/new` as never)} />
      </View>

      <DataTable<LineStatus>
        data={rows as unknown as LineStatus[]}
        searchPlaceholder={t('Search…')}
        columnsLabel={t('Columns')}
        columnsMenuLabel={t('Toggle columns')}
        searchKeys={['name']}
        emptyText={t('No line statuses.')}
        columns={[
          {
            key: 'color',
            label: t('Color'),
            width: 56,
            render: (s) => <View style={[styles.swatch, { backgroundColor: s.color ?? colors.line }]} />,
          },
          {
            key: 'name',
            label: t('Name'),
            flex: 1.4,
            render: (s) => <Text numberOfLines={1} style={styles.name}>{s.name}</Text>,
          },
          { key: 'sort_order', label: t('Order'), width: 72, render: (s) => <Mono size={11} color={colors.muted}>{String(s.sort_order)}</Mono> },
          {
            key: 'is_default',
            label: t('Default'),
            width: 88,
            render: (s) => (s.is_default ? <Text style={styles.badge}>{t('Default')}</Text> : <Text style={styles.dash}>—</Text>),
          },
          {
            key: 'is_done_status',
            label: t('Done'),
            width: 72,
            render: (s) => (s.is_done_status ? <Text style={styles.badge}>{t('Done')}</Text> : <Text style={styles.dash}>—</Text>),
          },
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
  name: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  swatch: { width: 22, height: 22, borderRadius: 6, borderWidth: StyleSheet.hairlineWidth, borderColor: colors.line },
  badge: { fontSize: 11, fontFamily: fonts.sans.native.medium, color: colors.accent },
  dash: { fontSize: 13, color: colors.faint },
});
