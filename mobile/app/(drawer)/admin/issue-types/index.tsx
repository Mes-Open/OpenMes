/**
 * Issue types — 1:1 with the web admin issue-types table
 * (Pages/admin/issue-types/Index.jsx): the shared DataTable with the web's
 * column set (Code / Name / Severity / Blocking / Status) plus per-row actions
 * (Edit / Activate-Deactivate / Delete). Toggling active reuses the update
 * endpoint; Delete hits the REST destroy route, which deactivates the type
 * (backend soft-disable). The web's "Used" count has no REST counterpart, so
 * it's omitted. Data via REST useIssueTypes (all rows, incl. inactive).
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
  useDeleteIssueType,
  useIssueTypes,
  useUpdateIssueType,
} from '@/hooks/queries/useIssues';
import type { IssueType } from '@/types/api';

export default function IssueTypesPage() {
  const { t } = useTranslation();
  const router = useRouter();

  const q = useIssueTypes(true);
  const del = useDeleteIssueType();
  const update = useUpdateIssueType();
  const rows = q.data ?? [];

  const severityLabels: Record<string, string> = {
    LOW: t('Low'),
    MEDIUM: t('Medium'),
    HIGH: t('High'),
    CRITICAL: t('Critical'),
  };

  const onToggle = (it: IssueType) =>
    update.mutate(
      { id: it.id, input: { is_active: !(it.is_active ?? true) } },
      { onError: (e: Error) => Alert.alert(t('Could not update'), e.message) },
    );

  const onDelete = (it: IssueType) =>
    Alert.alert(t('Delete issue type'), t('Delete "{{name}}"?', { name: it.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(it.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
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
        <Text style={styles.h1}>{t('Issue Types')}</Text>
        <View style={{ flex: 1 }} />
        <Button title={t('+ New Issue Type')} size="sm" onPress={() => router.push('/admin/issue-types/new' as never)} />
      </View>

      <DataTable<IssueType>
        data={rows as IssueType[]}
        searchPlaceholder={t('Search…')}
        columnsLabel={t('Columns')}
        columnsMenuLabel={t('Toggle columns')}
        searchKeys={['code', 'name']}
        emptyText={t('No issue types yet.')}
        onRowPress={(it) => router.push(`/admin/issue-types/${it.id}` as never)}
        columns={[
          { key: 'code', label: t('Code'), width: 110, render: (it) => <Mono size={11} color={colors.muted}>{it.code ?? '—'}</Mono> },
          {
            key: 'name',
            label: t('Name'),
            flex: 1.2,
            render: (it) => <Text numberOfLines={1} style={styles.name}>{it.name}</Text>,
          },
          { key: 'severity', label: t('Severity'), width: 96, render: (it) => (it.severity ? severityLabels[it.severity] ?? it.severity : '—') },
          { key: 'is_blocking', label: t('Blocking'), width: 90, render: (it) => (it.is_blocking ? t('Yes') : t('No')) },
          {
            key: 'is_active',
            label: t('Status'),
            width: 90,
            render: (it) => (
              <StatusPill status={it.is_active === false ? 'CANCELLED' : 'IN_PROGRESS'} label={it.is_active === false ? t('Inactive') : t('Active')} />
            ),
          },
        ]}
        actions={(it) => [
          { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/admin/issue-types/${it.id}` as never) },
          {
            label: it.is_active === false ? t('Activate') : t('Deactivate'),
            icon: it.is_active === false ? 'activate' : 'deactivate',
            onPress: () => onToggle(it),
          },
          { label: t('Delete'), icon: 'delete', onPress: () => onDelete(it) },
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
